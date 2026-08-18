<?php
declare(strict_types=1);

namespace Boffinate\Twig;

use Boffinate\Twig\Component\UxComponentSupport;
use Boffinate\Twig\Extension\ModxDebugExtension;
use Boffinate\Twig\Extension\ModxExtension;
use Boffinate\Twig\Proxy\modChunkTwig;
use Boffinate\Twig\Proxy\ResourceAccessor;
use Boffinate\Twig\Support\ModxRuntime;
use MODX\Revolution\modChunk;
use MODX\Revolution\modResource;
use MODX\Revolution\modX;
use Twig\Environment;
use Twig\Extension\ExtensionInterface;
use Twig\Loader\ArrayLoader;
use Twig\Loader\ChainLoader;
use Twig\Loader\FilesystemLoader;
use Twig\Loader\LoaderInterface;
use xPDO\xPDO;

class Twig extends ParserBase
{
    /** @var modX $modx */
    public $modx;

    private Environment $twig;
    /** @var callable[] */
    private array $initializers = [];
    private ?ModxRuntime $runtime = null;
    private int $renderDepth = 0;
    private const MAX_RENDER_DEPTH = 5;
    private const MAX_OUTPUT_SIZE = 5_242_880; // 5MB
    private ?ResourceAccessor $resourceAccessor = null;
    private ?modResource $lastResource = null;
    /** @var array<string, string[]> namespace => template directories */
    private array $templatePaths = [];

    public const GLOBAL_KEYS = ['modx', 'resource', 'placeholders'];

    /** On render errors, return an empty string (production default). */
    public const ERROR_FALLBACK_EMPTY = 'empty';
    /** On render errors, return the source with Twig delimiters made inert. */
    public const ERROR_FALLBACK_NEUTRALIZE = 'neutralize';
    /** On render errors, return the source untouched (debug behaviour). */
    public const ERROR_FALLBACK_SOURCE = 'source';

    public function __construct(modX &$modx)
    {
        if ($this instanceof \ModxPro\PdoTools\Parsing\Parser) {
            parent::__construct($modx, $modx->services->get('pdotools'));
        } else {
            parent::__construct($modx);
        }
    }

    /**
     * The registered `twigparser` service, or null when this addon is not
     * bootstrapped (or something else claimed the service name). Callers hold
     * an xPDO instance rather than a modX in places — an element's $xpdo, a
     * pdoTools service's $modx — so the guard lives here rather than being
     * restated by each of them.
     */
    public static function fromServices(xPDO $xpdo): ?self
    {
        if (!$xpdo->services->has('twigparser')) {
            return null;
        }

        $twig = $xpdo->services->get('twigparser');

        return $twig instanceof self ? $twig : null;
    }

    /**
     * fromServices() with the failure path handled once for callers that
     * degrade gracefully: when the service is missing (or resolving it
     * throws), log a single error per request — repeat calls are silenced
     * through $optionFlag, an option set on first failure — and return null
     * so the caller can produce its own fallback output. Callers must still
     * guard `class_exists(Twig::class)` themselves: when this class is not
     * autoloadable there is nothing here left to run.
     */
    public static function fromServicesOrLogOnce(xPDO $xpdo, string $optionFlag, string $unavailableMessage): ?self
    {
        if ($xpdo->getOption($optionFlag, null, false)) {
            return null;
        }

        $failure = null;
        try {
            $twig = self::fromServices($xpdo);
        } catch (\Throwable $failure) {
            $twig = null;
        }

        if ($twig instanceof self) {
            return $twig;
        }

        if ($failure !== null) {
            $unavailableMessage .= sprintf(' (%s: %s)', $failure::class, $failure->getMessage());
        }

        $xpdo->log(xPDO::LOG_LEVEL_ERROR, $unavailableMessage);
        $xpdo->setOption($optionFlag, true);

        return null;
    }

    /**
     * Install this parser as $modx->parser so that Twig renders first
     * and the parent parser (pdoTools or core) handles MODX tags and
     * Fenom afterwards.
     */
    public function decorateParser(): void
    {
        $this->modx->parser = $this;
    }

    public function getElement($class, $name)
    {
        $obj = parent::getElement($class, $name);

        if ($obj instanceof modChunk) {
            return new modChunkTwig($obj, $this);
        }
        return $obj;
    }

    private function init()
    {
        if (isset($this->twig)) return;

        $cachePath = self::getCompiledTemplatesPath($this->modx);
        $loader = $this->buildLoader();
        $debug = (bool) $this->modx->getOption('twig.debug', null, false);
        /*
         * auto_reload follows debug: in production it costs a filemtime()
         * stat per template per request, to catch edits that only happen at
         * deploy time. Deploy-time invalidation is already covered — the
         * TwigCacheClear plugin calls clearCompiledTemplatesForModx() on
         * OnSiteRefresh, so the full Clear Cache that ends a deploy empties
         * {cache_path}/twig/ with it. Chunk and template sources are
         * compiled from strings, not files, so they are keyed by content and
         * cannot go stale in the first place; auto_reload only ever mattered
         * for templates loaded from disk.
         */
        $this->twig = new \Twig\Environment($loader, [
            'debug' => $debug,
            'cache' => $cachePath,
            'auto_reload' => $debug,
        ]);
        if ($debug) {
            $this->twig->addExtension(new ModxDebugExtension($this->modx));
        }
        $this->twig->addExtension(new ModxExtension($this->getRuntime()));
        if ((bool) $this->modx->getOption('twig.components', null, true)
            && class_exists(\Symfony\UX\TwigComponent\Twig\ComponentExtension::class)) {
            UxComponentSupport::register(
                $this->twig,
                (string) $this->modx->getOption('twig.components_dir', null, 'components')
            );
        }
        $this->applyInitializers();
        $this->modx->invokeEvent('OnTwigInit', [
            'twig' => $this->twig,
            'parser' => $this,
            'modx' => $this->modx,
        ]);
    }

    public function renderString(string $content, array $placeholders, string $errorFallback = self::ERROR_FALLBACK_EMPTY)
    {
        if (!self::containsTwigSyntax($content)) {
            return $content;
        }

        if ($this->renderDepth >= self::MAX_RENDER_DEPTH) {
            $this->modx->log(xPDO::LOG_LEVEL_WARN, '[Twig] Maximum render depth (' . self::MAX_RENDER_DEPTH . ') reached, skipping Twig rendering to prevent recursion.');
            return $content;
        }

        $this->renderDepth++;
        try {
            $this->init();
            $this->syncGlobals();
            $result = $this->twig->render(
                $this->twig->createTemplate($content),
                $placeholders
            );

            if (strlen($result) > self::MAX_OUTPUT_SIZE) {
                $this->modx->log(xPDO::LOG_LEVEL_ERROR, '[Twig] Rendered output (' . number_format(strlen($result)) . ' bytes) exceeds ' . number_format(self::MAX_OUTPUT_SIZE) . ' byte limit. This is usually caused by {{ dump(_context) }} or similar calls that serialize large objects. Use {{ dump(variable_name) }} instead.');
                return $content;
            }

            return $result;
        } catch (\Twig\Error\Error $e) {
            return $this->handleRenderError($e, $content, $errorFallback);
        } finally {
            $this->renderDepth--;
        }
    }

    /**
     * Template source must never reach visitors on a failed render: it can
     * contain logic, comments, and expressions that were written on the
     * assumption they stay server-side. In debug mode the source is returned
     * so developers see what failed in place.
     */
    private function handleRenderError(\Twig\Error\Error $e, string $content, string $errorFallback): string
    {
        $source = $e->getSourceContext();
        $this->modx->log(xPDO::LOG_LEVEL_ERROR, sprintf(
            '[Twig] Render error%s at line %d: %s',
            $source && $source->getName() !== '' ? ' in ' . $source->getName() : '',
            $e->getTemplateLine(),
            $e->getRawMessage()
        ));

        if ((bool) $this->modx->getOption('twig.debug', null, false)) {
            return $content;
        }

        return match ($errorFallback) {
            self::ERROR_FALLBACK_SOURCE => $content,
            self::ERROR_FALLBACK_NEUTRALIZE => self::neutralizeTwigSyntax($content),
            default => '',
        };
    }

    /**
     * Make Twig delimiters inert so the surrounding markup still renders but
     * nothing can execute (and the MODX parser pass cannot revive them).
     */
    public static function neutralizeTwigSyntax(string $content): string
    {
        return str_replace(['{{', '{%', '{#'], ['&#123;{', '&#123;%', '&#123;#'], $content);
    }

    public static function containsTwigSyntax(string $content): bool
    {
        return str_contains($content, '{{')
            || str_contains($content, '{%')
            || str_contains($content, '{#')
            || str_contains($content, '<twig:');
    }

    public function getEnvironment(): Environment
    {
        $this->init();

        return $this->twig;
    }

    public function getRuntime(): ModxRuntime
    {
        if ($this->runtime === null) {
            $this->runtime = new ModxRuntime($this, $this->modx);
        }

        return $this->runtime;
    }

    /**
     * Register a directory of Twig template files, optionally under a
     * namespace: registerTemplatePath('/path/to/components', 'components')
     * makes `{% include '@components/button/button.twig' %}` work.
     */
    public function registerTemplatePath(string $path, string $namespace = FilesystemLoader::MAIN_NAMESPACE): void
    {
        $this->templatePaths[$namespace][] = $path;

        /* Drop any built environment so init() rebuilds it with the new path. */
        unset($this->twig);
    }

    public function registerInitializer(callable $initializer): void
    {
        $this->initializers[] = $initializer;

        unset($this->twig);
    }

    public function registerExtension(ExtensionInterface $extension): void
    {
        $this->registerInitializer(static function (Environment $twig) use ($extension): void {
            $twig->addExtension($extension);
        });
    }

    public function clearCompiledTemplates(): void
    {
        self::clearCompiledTemplatesForModx($this->modx);
    }

    public static function clearCompiledTemplatesForModx(modX $modx): void
    {
        $cachePath = self::resolveCachePath($modx);
        if (is_dir($cachePath)) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($cachePath, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );

            foreach ($iterator as $item) {
                if ($item->isDir()) {
                    @rmdir($item->getPathname());
                } else {
                    @unlink($item->getPathname());
                }
            }
        }

        if (!is_dir($cachePath)) {
            mkdir($cachePath, 0777, true);
        }
    }

    public static function getCompiledTemplatesPath(modX $modx): string
    {
        $cachePath = self::resolveCachePath($modx);
        if (!is_dir($cachePath)) {
            $modx->getCacheManager();
            $modx->cacheManager->writeTree($cachePath);
        }

        return $cachePath;
    }

    /**
     * Chain an ArrayLoader (createTemplate strings) with a FilesystemLoader
     * fed from the `twig.template_paths` system setting and any paths
     * registered through registerTemplatePath(). The setting is a JSON
     * object of namespace => path; use "main" for the root namespace.
     * Relative paths resolve against MODX_BASE_PATH.
     */
    private function buildLoader(): LoaderInterface
    {
        $filesystem = new FilesystemLoader();

        foreach ($this->resolveTemplatePaths() as $namespace => $paths) {
            foreach ($paths as $path) {
                if (!is_dir($path)) {
                    $this->modx->log(xPDO::LOG_LEVEL_WARN, '[Twig] Template path does not exist: ' . $path);
                    continue;
                }
                $filesystem->addPath($path, $namespace);
            }
        }

        return new ChainLoader([new ArrayLoader([]), $filesystem]);
    }

    /**
     * @return array<string, string[]>
     */
    private function resolveTemplatePaths(): array
    {
        $paths = $this->templatePaths;

        $setting = trim((string) $this->modx->getOption('twig.template_paths', null, ''));
        if ($setting !== '') {
            $decoded = json_decode($setting, true);
            if (!is_array($decoded)) {
                $this->modx->log(xPDO::LOG_LEVEL_ERROR, '[Twig] The twig.template_paths setting must be a JSON object of namespace => path.');
                $decoded = [];
            }
            foreach ($decoded as $namespace => $path) {
                $namespace = is_string($namespace) && $namespace !== '' && $namespace !== 'main'
                    ? $namespace
                    : FilesystemLoader::MAIN_NAMESPACE;
                foreach ((array) $path as $single) {
                    $single = (string) $single;
                    if (!str_starts_with($single, '/')) {
                        $single = MODX_BASE_PATH . $single;
                    }
                    $paths[$namespace][] = $single;
                }
            }
        }

        return $paths;
    }

    private function syncGlobals(): void
    {
        $this->twig->addGlobal('modx', $this->modx);
        $this->twig->addGlobal('resource', $this->wrapResource());
        $this->twig->addGlobal('placeholders', $this->modx->placeholders ?? []);

    }

    private function wrapResource(): ?ResourceAccessor
    {
        $resource = $this->modx->resource;
        if (!$resource instanceof modResource) {
            return null;
        }
        if ($this->resourceAccessor === null || $resource !== $this->lastResource) {
            $this->lastResource = $resource;
            $this->resourceAccessor = new ResourceAccessor($resource);
        }
        return $this->resourceAccessor;
    }

    private function applyInitializers(): void
    {
        foreach ($this->initializers as $initializer) {
            $initializer($this->twig, $this, $this->modx);
        }
    }

    private static function resolveCachePath(modX $modx): string
    {
        $cacheBase = $modx->getOption(xPDO::OPT_CACHE_PATH, null, MODX_CORE_PATH . 'cache/');
        return rtrim($cacheBase, '/\\') . '/twig/';
    }
}
