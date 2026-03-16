<?php
declare(strict_types=1);

namespace Boffinate\Twig;

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

    public const GLOBAL_KEYS = ['modx', 'resource', 'placeholders'];

    public function __construct(modX &$modx)
    {
        if ($this instanceof \ModxPro\PdoTools\Parsing\Parser) {
            parent::__construct($modx, $modx->services->get('pdotools'));
        } else {
            parent::__construct($modx);
        }
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

    /**
     * Process MODX content with Twig template engine
     *
     * @param string $parentTag
     * @param string $content
     * @param bool $processUncacheable
     * @param bool $removeUnprocessed
     * @param string $prefix
     * @param string $suffix
     * @param array $tokens
     * @param int $depth
     *
     * @return int
     */
    public function processElementTags(
        $parentTag,
        & $content,
        $processUncacheable = false,
        $removeUnprocessed = false,
        $prefix = "[[",
        $suffix = "]]",
        $tokens = array(),
        $depth = 0
    ) {
        // Render Twig on uncacheable content outside the manager, but only
        // when the content is small enough to be a raw template or chunk.
        // Assembled page content (with ContentBlocks dump output etc.) is
        // much larger and would cause double-rendering or OOM.
        if (is_string($content) && $processUncacheable
            && $this->modx->context->key !== 'mgr'
            && strlen($content) <= self::MAX_OUTPUT_SIZE
            && self::containsTwigSyntax($content)) {
            $content = $this->renderString($content, []);
        }

        return parent::processElementTags($parentTag, $content, $processUncacheable, $removeUnprocessed, $prefix,
            $suffix, $tokens, $depth
        );
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

        $cachePath = $this->getCachePath();
        $loader = new \Twig\Loader\ArrayLoader([
        ]);
        $debug = (bool) $this->modx->getOption('twig.debug', null, true);
        $this->twig = new \Twig\Environment($loader, [
            'debug' => $debug,
            'cache' => $cachePath,
            'auto_reload' => true,
        ]);
        if ($debug) {
            $this->twig->addExtension(new ModxDebugExtension($this->modx));
        }
        $this->twig->addExtension(new ModxExtension($this->getRuntime()));
        $this->applyInitializers();
        $this->modx->invokeEvent('OnTwigInit', [
            'twig' => $this->twig,
            'parser' => $this,
            'modx' => $this->modx,
        ]);
    }

    public function renderString(string $content, array $placeholders)
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
            $this->modx->log(xPDO::LOG_LEVEL_ERROR, '[Twig] ' . $e->getMessage());
            return $content;
        } finally {
            $this->renderDepth--;
        }
    }

    public static function containsTwigSyntax(string $content): bool
    {
        return str_contains($content, '{{') || str_contains($content, '{%') || str_contains($content, '{#');
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

    public function registerInitializer(callable $initializer): void
    {
        $this->initializers[] = $initializer;

        if (isset($this->twig)) {
            unset($this->twig);
        }
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
                $pathname = $item->getPathname();
                if ($item->isDir()) {
                    if (is_dir($pathname)) {
                        @rmdir($pathname);
                    }
                    continue;
                }

                if (is_file($pathname)) {
                    @unlink($pathname);
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

    private function getCachePath(): string
    {
        return self::getCompiledTemplatesPath($this->modx);
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
