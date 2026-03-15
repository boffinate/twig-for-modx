<?php
declare(strict_types=1);

namespace Boffinate\Twig;

use Boffinate\Twig\Extension\ModxExtension;
use Boffinate\Twig\Proxy\modChunkTwig;
use Boffinate\Twig\Proxy\ResourceAccessor;
use Boffinate\Twig\Support\ModxRuntime;
use MODX\Revolution\modChunk;
use MODX\Revolution\modParser;
use MODX\Revolution\modResource;
use MODX\Revolution\modX;
use Twig\Environment;
use Twig\Extension\DebugExtension;
use Twig\Extension\ExtensionInterface;
use xPDO\xPDO;

class Twig extends modParser
{
    /** @var modX $modx */
    public $modx;

    private Environment $twig;
    /** @var callable[] */
    private array $initializers = [];
    private ?ModxRuntime $runtime = null;

    public function __construct(modX &$modx)
    {
        parent::__construct($modx);
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
        if (is_string($content) && $processUncacheable
            && $this->modx->context->key !== 'mgr') {
            $this->init();
            $_processingUncacheable = $this->_processingUncacheable;
            $this->_processingUncacheable = true;
            $content = $this->renderString($content, []
//                array_merge(array_filter(
//                    $this->modx->placeholders,
//                    fn($v, $k) => !str_starts_with($k, '+'),
//                    ARRAY_FILTER_USE_BOTH
//                ), ['modx' => $this->modx])
            ); //
            $this->_processingUncacheable = $_processingUncacheable;
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
        $this->twig = new \Twig\Environment($loader, [
            'debug' => true,
            'cache' => $cachePath,
            'auto_reload' => true,
        ]);
        $this->twig->addExtension(new DebugExtension());
        $this->twig->addExtension(new ModxExtension($this->getRuntime()));
        $this->syncGlobals();
        $this->applyInitializers();
        $this->modx->invokeEvent('OnTwigInit', [
            'twig' => $this->twig,
            'parser' => $this,
            'modx' => $this->modx,
        ]);
    }

    public function renderString(string $content, array $placeholders)
    {
        $this->init();
        $this->syncGlobals();
        return $this->twig->render(
            $this->twig->createTemplate($content),
            $placeholders
        );
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
        $this->twig->addGlobal('modx_runtime', $this->getRuntime());
    }

    private function wrapResource(): ?ResourceAccessor
    {
        $resource = $this->modx->resource;

        return $resource instanceof modResource ? new ResourceAccessor($resource) : null;
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
