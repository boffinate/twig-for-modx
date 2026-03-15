<?php
declare(strict_types=1);

namespace MODX\Revolution\Tests\Twig;

require_once __DIR__ . '/ParserTestCase.php';

class ModxParserCompatibilityTest extends ParserTestCase
{
    public function test_modx_template_without_twig_syntax_still_processes_modx_tags(): void
    {
        $this->modx->setPlaceholder('name', 'MODX');
        $content = 'Hello [[+name]]!';

        $this->assertSame('Hello MODX!', $this->processContent($content));
    }

    public function test_template_element_leaves_twig_syntax_literal_without_twig_parser(): void
    {
        $this->modx->setPlaceholder('name', 'MODX');

        $this->assertSame(
            'Template {{ 2 + 2 }} MODX',
            $this->renderTemplateContent('Template {{ 2 + 2 }} [[+name]]')
        );
    }

    public function test_resource_content_leaves_twig_syntax_literal_without_twig_parser(): void
    {
        $output = $this->renderResourceContent('Resource {{ 3 * 3 }} [[+name]]', ['name' => 'MODX']);

        $this->assertSame('Resource {{ 3 * 3 }} MODX', $output);
    }

    public function test_valid_twig_syntax_remains_literal_without_twig_parser(): void
    {
        $content = 'Sum: {{ 2 + 3 }}';

        $this->assertSame('Sum: {{ 2 + 3 }}', $this->processContent($content));
    }

    public function test_invalid_twig_syntax_remains_literal_without_twig_parser(): void
    {
        $content = 'Broken {{ name ';

        $this->assertSame('Broken {{ name', $this->processContent($content));
    }

    public function test_template_calls_chunk_with_twig_content_without_rendering_twig(): void
    {
        $this->registerChunk('PlainTwigChunk', 'Hello {{ name }}');
        $content = '[[$PlainTwigChunk? &name=`World`]]';

        $this->assertSame('Hello {{ name }}', $this->processContent($content));
    }

    public function test_modx_chunk_leaves_twig_syntax_literal_without_twig_parser(): void
    {
        $this->registerChunk('LiteralTwigChunk', 'Hi [[+subject]] {{ subject|upper }}');

        $output = $this->modx->getChunk('LiteralTwigChunk', ['subject' => 'modx']);
        $this->assertSame('Hi modx {{ subject|upper }}', $output);
    }

    public function test_modx_content_can_call_snippet_inside_twig_like_text_without_twig_parser(): void
    {
        $this->registerSnippet('PlainSnippet', 'return "Snippet output";');
        $content = 'Start {% if true %}[[PlainSnippet]]{% endif %} End';

        $this->assertSame('Start {% if true %}Snippet output{% endif %} End', $this->processContent($content));
    }

    public function test_snippet_output_with_twig_expression_remains_literal_without_twig_parser(): void
    {
        $this->registerSnippet('LiteralTwigSnippet', 'return "Twig math {{ 3 * 3 }}";');

        $this->assertSame('Twig math {{ 3 * 3 }}', $this->processContent('[[LiteralTwigSnippet]]'));
    }

    public function test_snippet_output_with_twig_template_syntax_remains_literal_without_twig_parser(): void
    {
        $this->registerSnippet(
            'LiteralTwigTemplateSnippet',
            'return "{% set word = \"twig\" %}Word {{ word|upper }}";'
        );

        $this->assertSame(
            '{% set word = "twig" %}Word {{ word|upper }}',
            $this->processContent('[[LiteralTwigTemplateSnippet]]')
        );
    }
}
