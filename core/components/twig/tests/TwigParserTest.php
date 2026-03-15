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

    public function test_contentblocks_plugin_receives_all_field_placeholders(): void
    {
        $output = $this->executePluginFile(
            MODX_CORE_PATH . 'components/twig/elements/plugins/TwigContentBlocks.php',
            [
                'tpl' => '<img src="{{ url }}" alt="{{ title }}" class="{{ setting }}">',
                'phs' => [
                    'url' => '/images/hero.jpg',
                    'title' => 'Hero Image',
                    'setting' => 'full-width',
                    'idx' => 1,
                ],
            ]
        );

        $this->assertSame('<img src="/images/hero.jpg" alt="Hero Image" class="full-width">', $output);
    }

    public function test_contentblocks_plugin_supports_twig_conditionals_on_field_data(): void
    {
        $output = $this->executePluginFile(
            MODX_CORE_PATH . 'components/twig/elements/plugins/TwigContentBlocks.php',
            [
                'tpl' => '{% if url %}<a href="{{ url }}">{{ title }}</a>{% else %}<span>{{ title }}</span>{% endif %}',
                'phs' => [
                    'url' => 'https://example.com',
                    'title' => 'Click here',
                ],
            ]
        );

        $this->assertSame('<a href="https://example.com">Click here</a>', $output);
    }

    public function test_contentblocks_plugin_supports_twig_conditionals_with_empty_value(): void
    {
        $output = $this->executePluginFile(
            MODX_CORE_PATH . 'components/twig/elements/plugins/TwigContentBlocks.php',
            [
                'tpl' => '{% if url %}<a href="{{ url }}">{{ title }}</a>{% else %}<span>{{ title }}</span>{% endif %}',
                'phs' => [
                    'url' => '',
                    'title' => 'No link',
                ],
            ]
        );

        $this->assertSame('<span>No link</span>', $output);
    }

    public function test_contentblocks_plugin_supports_twig_filters_on_field_data(): void
    {
        $output = $this->executePluginFile(
            MODX_CORE_PATH . 'components/twig/elements/plugins/TwigContentBlocks.php',
            [
                'tpl' => '{{ title|upper }} {{ description|default("No description") }}',
                'phs' => [
                    'title' => 'hello world',
                    'description' => '',
                ],
            ]
        );

        $this->assertSame('HELLO WORLD No description', $output);
    }

    public function test_contentblocks_repeater_wrapper_receives_rows_and_row_data(): void
    {
        $rowData = [
            ['heading' => 'First', 'body' => 'Content one', 'idx' => 1],
            ['heading' => 'Second', 'body' => 'Content two', 'idx' => 2],
            ['heading' => 'Third', 'body' => 'Content three', 'idx' => 3],
        ];

        $output = $this->executePluginFile(
            MODX_CORE_PATH . 'components/twig/elements/plugins/TwigContentBlocks.php',
            [
                'tpl' => '<ul>{% for row in row_data %}<li>{{ row.heading }}: {{ row.body }}</li>{% endfor %}</ul>',
                'phs' => [
                    'rows' => '<li>First: Content one</li><li>Second: Content two</li><li>Third: Content three</li>',
                    'row_data' => $rowData,
                    'idx' => 1,
                ],
            ]
        );

        $this->assertSame(
            '<ul><li>First: Content one</li><li>Second: Content two</li><li>Third: Content three</li></ul>',
            $output
        );
    }

    public function test_contentblocks_repeater_row_data_supports_loop_index(): void
    {
        $rowData = [
            ['title' => 'Alpha'],
            ['title' => 'Beta'],
        ];

        $output = $this->executePluginFile(
            MODX_CORE_PATH . 'components/twig/elements/plugins/TwigContentBlocks.php',
            [
                'tpl' => '{% for row in row_data %}{{ loop.index }}.{{ row.title }} {% endfor %}',
                'phs' => [
                    'rows' => '',
                    'row_data' => $rowData,
                ],
            ]
        );

        $this->assertSame('1.Alpha 2.Beta ', $output);
    }

    public function test_contentblocks_repeater_row_data_supports_conditional_rendering(): void
    {
        $rowData = [
            ['title' => 'Visible', 'hidden' => ''],
            ['title' => 'Hidden', 'hidden' => '1'],
            ['title' => 'Also visible', 'hidden' => ''],
        ];

        $output = $this->executePluginFile(
            MODX_CORE_PATH . 'components/twig/elements/plugins/TwigContentBlocks.php',
            [
                'tpl' => '{% for row in row_data %}{% if not row.hidden %}{{ row.title }} {% endif %}{% endfor %}',
                'phs' => [
                    'rows' => '',
                    'row_data' => $rowData,
                ],
            ]
        );

        $this->assertSame('Visible Also visible ', $output);
    }

    public function test_contentblocks_repeater_row_data_supports_first_last_checks(): void
    {
        $rowData = [
            ['title' => 'One'],
            ['title' => 'Two'],
            ['title' => 'Three'],
        ];

        $output = $this->executePluginFile(
            MODX_CORE_PATH . 'components/twig/elements/plugins/TwigContentBlocks.php',
            [
                'tpl' => '{% for row in row_data %}{% if loop.first %}[{% endif %}{{ row.title }}{% if not loop.last %}, {% endif %}{% if loop.last %}]{% endif %}{% endfor %}',
                'phs' => [
                    'rows' => '',
                    'row_data' => $rowData,
                ],
            ]
        );

        $this->assertSame('[One, Two, Three]', $output);
    }

    public function test_contentblocks_repeater_wrapper_can_count_rows(): void
    {
        $rowData = [
            ['title' => 'A'],
            ['title' => 'B'],
            ['title' => 'C'],
        ];

        $output = $this->executePluginFile(
            MODX_CORE_PATH . 'components/twig/elements/plugins/TwigContentBlocks.php',
            [
                'tpl' => '{{ row_data|length }} items',
                'phs' => [
                    'rows' => '',
                    'row_data' => $rowData,
                ],
            ]
        );

        $this->assertSame('3 items', $output);
    }

    public function test_contentblocks_repeater_row_template_renders_individual_fields(): void
    {
        $output = $this->executePluginFile(
            MODX_CORE_PATH . 'components/twig/elements/plugins/TwigContentBlocks.php',
            [
                'tpl' => '<div class="card"><h3>{{ heading }}</h3><p>{{ body }}</p>{% if image %}<img src="{{ image }}">{% endif %}</div>',
                'phs' => [
                    'heading' => 'Card Title',
                    'body' => 'Card content goes here',
                    'image' => '/images/card.jpg',
                    'idx' => 1,
                ],
            ]
        );

        $this->assertSame(
            '<div class="card"><h3>Card Title</h3><p>Card content goes here</p><img src="/images/card.jpg"></div>',
            $output
        );
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
