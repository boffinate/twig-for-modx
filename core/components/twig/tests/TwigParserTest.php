<?php
declare(strict_types=1);

namespace MODX\Revolution\Tests\Twig;

require_once __DIR__ . '/ParserTestCase.php';

use Boffinate\Twig\Twig;
use Boffinate\Twig\Support\ModxRuntime;
use Twig\Error\SyntaxError;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class TwigParserTest extends ParserTestCase
{
    protected function usesTwigParser(): bool
    {
        return true;
    }

    public function test_modx_template_without_twig_syntax_still_processes_modx_tags(): void
    {
        $this->modx->setPlaceholder('name', 'MODX');
        $content = 'Hello [[+name]]!';

        $this->assertSame('Hello MODX!', $this->processContent($content));
    }

    public function test_template_element_renders_twig_and_modx_tags(): void
    {
        $this->modx->setPlaceholder('name', 'MODX');

        $this->assertSame('Template 4 MODX', $this->renderTemplateContent('Template {{ 2 + 2 }} [[+name]]'));
    }

    public function test_resource_content_renders_twig_and_modx_tags(): void
    {
        $output = $this->renderResourceContent('Resource {{ 3 * 3 }} [[+name]]', ['name' => 'MODX']);

        $this->assertSame('Resource 9 MODX', $output);
    }

    public function test_modx_template_with_valid_twig_is_rendered(): void
    {
        $content = 'Sum: {{ 2 + 3 }}';

        $this->assertSame('Sum: 5', $this->processContent($content));
    }

    public function test_twig_can_render_chunk_via_builtin_function(): void
    {
        $this->registerChunk('FunctionChunk', 'Built {{ name|upper }}');

        $this->assertSame(
            'Built TWIG',
            $this->processContent('{{ chunk("FunctionChunk", {"name": "twig"}) }}')
        );
    }

    public function test_twig_can_run_snippet_via_builtin_function(): void
    {
        $this->registerSnippet('FunctionSnippet', 'return "Snippet " . strtoupper($name);');

        $this->assertSame(
            'Snippet TWIG',
            $this->processContent('{{ snippet("FunctionSnippet", {"name": "twig"}) }}')
        );
    }

    public function test_twig_can_access_placeholders_via_globals_and_helper_function(): void
    {
        $this->modx->setPlaceholder('name', 'MODX');
        $content = '{{ placeholders.name }} {{ placeholder("name") }} {{ placeholder("missing", "fallback") }}';

        $this->assertSame('MODX MODX fallback', $this->processContent($content));
    }

    public function test_twig_can_access_modx_options_and_lexicon_via_builtin_functions(): void
    {
        $this->modx->setOption('twig_option_test', 'configured');
        $this->modx->lexicon->load('en:setting');

        $content = '{{ option("twig_option_test") }} {{ lexicon("setting_site_name") }}';

        $this->assertSame('configured Site name', $this->processContent($content));
    }

    public function test_twig_compatibility_aliases_for_placeholder_config_and_translation_work(): void
    {
        $this->modx->setPlaceholder('name', 'MODX');
        $this->modx->setOption('twig_option_test', 'configured');

        $content = '{{ ph("name") }} {{ config("twig_option_test") }} {{ trans("setting_site_name", "en:setting") }}';

        $this->assertSame('MODX configured Site name', $this->processContent($content));
    }

    public function test_twig_can_generate_resource_links_via_compatibility_helper(): void
    {
        $resource = $this->registerResource([
            'pagetitle' => 'Linked Resource',
            'alias' => 'linked-resource',
        ]);
        $expected = $this->modx->makeUrl((int) $resource->get('id'));

        $this->assertSame($expected, $this->processContent('{{ link(' . (int) $resource->get('id') . ') }}'));
    }

    public function test_twig_field_helper_reads_resource_fields_and_template_variables(): void
    {
        $this->registerTemplateVar('HeroTitle');
        $resource = $this->registerResource([
            'pagetitle' => 'Field Resource',
            'content' => 'Body copy',
        ]);
        $this->assignTemplateVarValue($resource, 'HeroTitle', 'Twig Hero');
        $this->modx->resource = $this->modx->getObject(\MODX\Revolution\modResource::class, (int) $resource->get('id'));

        $content = '{{ field("pagetitle") }} | {{ field("HeroTitle") }} | {{ field({"name": "missing", "default": "fallback"}) }}';

        $this->assertSame('Field Resource | Twig Hero | fallback', $this->processContent($content));
    }

    public function test_modx_template_with_invalid_twig_syntax_throws(): void
    {
        $this->expectException(SyntaxError::class);

        $content = 'Broken {{ name ';
        $this->processContent($content);
    }

    public function test_template_calls_chunk_with_twig_content(): void
    {
        $this->registerChunk('TwigChunk', 'Hello {{ name }}');
        $content = '[[$TwigChunk? &name=`World`]]';

        $this->assertSame('Hello World', $this->processContent($content));
    }

    public function test_modx_chunk_renders_standard_placeholders(): void
    {
        $this->registerChunk('PlainChunk', 'Hi [[+subject]]');

        $output = $this->modx->getChunk('PlainChunk', ['subject' => 'MODX']);
        $this->assertSame('Hi MODX', $output);
    }

    public function test_modx_chunk_renders_twig_and_modx_placeholders(): void
    {
        $this->registerChunk('TwigDirectChunk', 'Hi [[+subject]] {{ subject|upper }}');

        $output = $this->modx->getChunk('TwigDirectChunk', ['subject' => 'modx']);
        $this->assertSame('Hi modx MODX', $output);
    }

    public function test_twig_can_call_modx_snippet(): void
    {
        $this->registerSnippet('TwiggedSnippet', 'return "Snippet output";');
        $content = 'Start {% if true %}[[TwiggedSnippet]]{% endif %} End';

        $this->assertSame('Start Snippet output End', $this->processContent($content));
    }

    public function test_snippet_output_twig_template_is_parsed(): void
    {
        $this->registerSnippet('TwigTemplateSnippet', 'return "Twig math {{ 3 * 3 }}";');

        $this->assertSame('Twig math 9', $this->processContent('[[TwigTemplateSnippet]]'));
    }

    public function test_snippet_output_with_twig_syntax_is_rendered(): void
    {
        $this->registerSnippet(
            'TwigFilterSnippet',
            'return "{% set word = \"twig\" %}Word {{ word|upper }}";'
        );

        $this->assertSame('Word TWIG', $this->processContent('[[TwigFilterSnippet]]'));
    }

    public function test_twigparser_service_resolves_parser_instance(): void
    {
        $service = $this->modx->services->get('twigparser');

        $this->assertInstanceOf(Twig::class, $service);
    }

    public function test_chunk_output_with_invalid_twig_syntax_throws(): void
    {
        $this->registerChunk('BrokenTwigChunk', 'Broken {{ name ');

        $this->expectException(SyntaxError::class);
        $this->processContent('[[$BrokenTwigChunk? &name=`World`]]');
    }

    public function test_snippet_output_with_invalid_twig_syntax_throws(): void
    {
        $this->registerSnippet('BrokenTwigSnippet', 'return "Broken {{ name ";');

        $this->expectException(SyntaxError::class);
        $this->processContent('[[BrokenTwigSnippet]]');
    }

    public function test_contentblocks_plugin_renders_twig_markup(): void
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

    public function test_custom_initializer_can_register_twig_function(): void
    {
        $parser = $this->modx->parser;
        $parser->registerInitializer(static function ($twig): void {
            $twig->addFunction(new TwigFunction('double_value', static fn ($value) => $value * 2));
        });

        $this->assertSame('10', $this->processContent('{{ double_value(5) }}'));
    }

    public function test_custom_extension_can_be_registered(): void
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

    public function test_custom_extension_can_use_shared_modx_runtime_helpers(): void
    {
        $this->registerChunk('RuntimeChunk', 'Chunk {{ name|upper }}');
        $this->registerSnippet('RuntimeSnippet', 'return "Snippet " . strtoupper($name);');

        $parser = $this->modx->parser;
        $runtime = $parser->getRuntime();
        $parser->registerExtension(new class($runtime) extends AbstractExtension {
            public function __construct(private ModxRuntime $runtime)
            {
            }

            public function getFunctions(): array
            {
                return [
                    new TwigFunction('runtime_chunk', fn (string $name) => $this->runtime->chunk('RuntimeChunk', ['name' => $name])),
                    new TwigFunction('runtime_snippet', fn (string $name) => $this->runtime->snippet('RuntimeSnippet', ['name' => $name])),
                ];
            }
        });

        $content = '{{ runtime_chunk("twig") }} | {{ runtime_snippet("twig") }}';

        $this->assertSame('Chunk TWIG | Snippet TWIG', $this->processContent($content));
    }
}
