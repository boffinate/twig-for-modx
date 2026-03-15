<?php
declare(strict_types=1);

namespace MODX\Revolution\Tests\Twig;

require_once __DIR__ . '/ParserTestCase.php';

use Boffinate\Twig\Twig;
use Boffinate\Twig\Support\ModxRuntime;
use Twig\Error\SyntaxError;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Tests that the Twig parser works correctly without PDOTools installed.
 *
 * This class overrides loadPdoTools() to a no-op, simulating an environment
 * where PDOTools is not installed. All core Twig functionality should work
 * without PDOTools.
 */
class TwigParserWithoutPdoToolsTest extends ParserTestCase
{
    protected function usesTwigParser(): bool
    {
        return true;
    }

    protected function loadPdoTools(): void
    {
        // No-op: simulate PDOTools not being installed
    }

    public function test_twig_parser_extends_mod_parser_not_pdotools(): void
    {
        $this->assertInstanceOf(Twig::class, $this->modx->parser);
        $this->assertInstanceOf(\MODX\Revolution\modParser::class, $this->modx->parser);
    }

    public function test_modx_tags_process_without_pdotools(): void
    {
        $this->modx->setPlaceholder('name', 'MODX');
        $content = 'Hello [[+name]]!';

        $this->assertSame('Hello MODX!', $this->processContent($content));
    }

    public function test_twig_expression_renders_without_pdotools(): void
    {
        $this->assertSame('Sum: 5', $this->processContent('Sum: {{ 2 + 3 }}'));
    }

    public function test_twig_and_modx_tags_coexist_without_pdotools(): void
    {
        $this->modx->setPlaceholder('name', 'MODX');

        $this->assertSame('Template 4 MODX', $this->renderTemplateContent('Template {{ 2 + 2 }} [[+name]]'));
    }

    public function test_resource_content_renders_without_pdotools(): void
    {
        $output = $this->renderResourceContent('Resource {{ 3 * 3 }} [[+name]]', ['name' => 'MODX']);

        $this->assertSame('Resource 9 MODX', $output);
    }

    public function test_chunk_with_twig_content_renders_without_pdotools(): void
    {
        $this->registerChunk('TwigChunk', 'Hello {{ name }}');
        $content = '[[$TwigChunk? &name=`World`]]';

        $this->assertSame('Hello World', $this->processContent($content));
    }

    public function test_chunk_function_works_without_pdotools(): void
    {
        $this->registerChunk('FunctionChunk', 'Built {{ name|upper }}');

        $this->assertSame(
            'Built TWIG',
            $this->processContent('{{ chunk("FunctionChunk", {"name": "twig"}) }}')
        );
    }

    public function test_snippet_function_works_without_pdotools(): void
    {
        $this->registerSnippet('FunctionSnippet', 'return "Snippet " . strtoupper($name);');

        $this->assertSame(
            'Snippet TWIG',
            $this->processContent('{{ snippet("FunctionSnippet", {"name": "twig"}) }}')
        );
    }

    public function test_placeholders_accessible_without_pdotools(): void
    {
        $this->modx->setPlaceholder('name', 'MODX');
        $content = '{{ placeholders.name }} {{ placeholder("name") }} {{ placeholder("missing", "fallback") }}';

        $this->assertSame('MODX MODX fallback', $this->processContent($content));
    }

    public function test_options_and_lexicon_work_without_pdotools(): void
    {
        $this->modx->setOption('twig_option_test', 'configured');
        $this->modx->lexicon->load('en:setting');

        $content = '{{ option("twig_option_test") }} {{ lexicon("setting_site_name") }}';

        $this->assertSame('configured Site name', $this->processContent($content));
    }

    public function test_invalid_twig_syntax_throws_without_pdotools(): void
    {
        $this->expectException(SyntaxError::class);

        $this->processContent('Broken {{ name ');
    }

    public function test_snippet_output_with_twig_is_rendered_without_pdotools(): void
    {
        $this->registerSnippet('TwigTemplateSnippet', 'return "Twig math {{ 3 * 3 }}";');

        $this->assertSame('Twig math 9', $this->processContent('[[TwigTemplateSnippet]]'));
    }

    public function test_custom_extension_works_without_pdotools(): void
    {
        $parser = $this->modx->parser;
        $parser->registerExtension(new class() extends AbstractExtension {
            public function getFunctions(): array
            {
                return [
                    new TwigFunction('triple_value', static fn ($value) => $value * 3),
                ];
            }
        });

        $this->assertSame('15', $this->processContent('{{ triple_value(5) }}'));
    }

    public function test_twigparser_service_resolves_without_pdotools(): void
    {
        $service = $this->modx->services->get('twigparser');

        $this->assertInstanceOf(Twig::class, $service);
    }

    public function test_cache_clearing_works_without_pdotools(): void
    {
        $parser = $this->modx->parser;
        $this->assertInstanceOf(Twig::class, $parser);

        // Should not throw
        $parser->clearCompiledTemplates();

        $cachePath = Twig::getCompiledTemplatesPath($this->modx);
        $this->assertDirectoryExists($cachePath);
    }

    public function test_contentblocks_plugin_renders_without_pdotools(): void
    {
        $output = $this->executePluginFile(
            MODX_CORE_PATH . 'components/twig/elements/plugins/TwigContentBlocks.php',
            [
                'tpl' => 'ContentBlocks {{ value|upper }}',
                'phs' => ['value' => 'twig'],
            ]
        );

        $this->assertSame('ContentBlocks TWIG', $output);
    }
}
