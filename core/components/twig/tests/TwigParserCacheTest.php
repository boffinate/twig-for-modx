<?php
declare(strict_types=1);

namespace MODX\Revolution\Tests\Twig;

require_once __DIR__ . '/ParserTestCase.php';

use Boffinate\Twig\Twig;
use MODX\Revolution\Processors\System\ClearCache;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use xPDO\xPDO;

class TwigParserCacheTest extends ParserTestCase
{
    protected function usesTwigParser(): bool
    {
        return true;
    }

    private function clearTwigCache(): void
    {
        Twig::clearCompiledTemplatesForModx($this->modx);
    }

    private function getTwigCachePath(): string
    {
        return Twig::getCompiledTemplatesPath($this->modx);
    }

    private function getTwigEnvironment(): Environment
    {
        return $this->modx->parser->getEnvironment();
    }

    private function countCacheFiles(): int
    {
        $path = $this->getTwigCachePath();
        if (!is_dir($path)) {
            return 0;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)
        );

        $count = 0;
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $count++;
            }
        }

        return $count;
    }

    private function latestCacheMTime(): int
    {
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->getTwigCachePath(), \FilesystemIterator::SKIP_DOTS));
        $latest = 0;
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $latest = max($latest, $file->getMTime());
            }
        }
        return $latest;
    }

    private function getReloadTemplatePath(): string
    {
        $base = rtrim($this->modx->getOption(xPDO::OPT_CACHE_PATH, null, MODX_CORE_PATH . 'cache/'), '/\\');
        return $base . '/twig-tests/reload.twig';
    }

    public function test_base_template_parses_twig_tags(): void
    {
        $this->registerChunk('SimpleChunk', 'Chunk content');
        $content = 'Twig sum {{ 6 / 2 }} and chunk [[$SimpleChunk]]';

        $this->assertSame('Twig sum 3 and chunk Chunk content', $this->processContent($content));
    }

    public function test_chunk_twig_is_rendered_before_being_injected(): void
    {
        $this->registerChunk('TwiggyChunk', 'Chunk twig value {{ value|upper }}');
        $content = 'Base math {{ 1 + 1 }} | [[$TwiggyChunk? &value=`chunked`]]';

        $this->assertSame('Base math 2 | Chunk twig value CHUNKED', $this->processContent($content));
    }

    public function test_chunk_outputting_raw_twig_tags_is_parsed_once_in_base_template_cycle(): void
    {
        $this->registerChunk('RawTwigChunk', '{{ "{{ 5 + 5 }}" }}');
        $content = 'Base math {{ 2 + 2 }} + [[$RawTwigChunk]]';

        $this->assertSame('Base math 4 + 10', $this->processContent($content));
    }

    public function test_compiled_templates_are_cached_on_disk(): void
    {
        $this->clearTwigCache();
        $baselineCount = $this->countCacheFiles();

        $this->processContent('Caching check {{ 7 * 3 }}');

        $this->assertGreaterThan($baselineCount, $this->countCacheFiles());
    }

    public function test_auto_reload_recompiles_when_source_changes(): void
    {
        $this->clearTwigCache();

        $templateFile = $this->getReloadTemplatePath();
        $this->modx->cacheManager->writeTree(dirname($templateFile) . '/');
        file_put_contents($templateFile, 'First {{ value }}');

        $env = $this->getTwigEnvironment();
        $env->setLoader(new FilesystemLoader(dirname($templateFile)));

        $env->render('reload.twig', ['value' => 'first']);
        $mtimeBefore = filemtime($templateFile);
        $this->assertTrue($env->isAutoReload());

        file_put_contents($templateFile, 'Second {{ value }}');
        touch($templateFile, time() + 2);

        $this->assertFalse($env->isTemplateFresh('reload.twig', $mtimeBefore));

        $this->modx->cacheManager->deleteTree(dirname($templateFile), ['deleteTop' => true, 'skipDirs' => false, 'extensions' => []]);

        $this->assertTrue(true); // explicit assertion to satisfy PHPUnit when only freshness checks run
    }

    public function test_on_cache_update_plugin_clears_twig_cache_during_modx_refresh(): void
    {
        $this->clearTwigCache();
        $this->registerPlugin(
            'TwigCacheUpdatePlugin',
            file_get_contents(MODX_CORE_PATH . 'components/twig/elements/plugins/TwigCacheClear.php'),
            [['name' => 'OnCacheUpdate', 'enabled' => true]]
        );

        $this->processContent('Cached {{ 8 * 8 }}');
        $this->assertGreaterThan(0, $this->countCacheFiles());

        $results = [];
        $this->modx->cacheManager->refresh(['default' => []], $results);

        $this->assertSame(0, $this->countCacheFiles());
    }

    public function test_clear_cache_processor_clears_twig_cache_and_allows_updated_chunk_output(): void
    {
        $this->clearTwigCache();
        $this->registerPlugin(
            'TwigSiteRefreshPlugin',
            file_get_contents(MODX_CORE_PATH . 'components/twig/elements/plugins/TwigCacheClear.php'),
            [
                ['name' => 'OnCacheUpdate', 'enabled' => true],
                ['name' => 'OnSiteRefresh', 'enabled' => true],
            ]
        );

        $this->registerChunk('CacheSensitiveChunk', 'Before {{ 2 + 2 }}');
        $this->assertSame('Before 4', $this->processContent('[[$CacheSensitiveChunk]]'));
        $this->assertGreaterThan(0, $this->countCacheFiles());

        $this->registerChunk('CacheSensitiveChunk', 'After {{ 3 + 3 }}');

        $processor = ClearCache::getInstance($this->modx, ClearCache::class, [
            'publishing' => false,
            'lexicons' => false,
            'elements' => false,
        ]);
        $processor->process();

        $this->assertSame(0, $this->countCacheFiles());
        $this->assertSame('After 6', $this->processContent('[[$CacheSensitiveChunk]]'));
    }
}
