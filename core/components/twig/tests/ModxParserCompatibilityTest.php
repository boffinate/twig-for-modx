<?php
declare(strict_types=1);

namespace MODX\Revolution\Tests\Twig;

require_once __DIR__ . '/ParserTestCase.php';

class ModxParserCompatibilityTest extends ParserTestCase
{
    public function test_modx_tags_still_process_normally(): void
    {
        $this->modx->setPlaceholder('name', 'MODX');
        $content = 'Hello [[+name]]!';

        $this->assertSame('Hello MODX!', $this->processContent($content));
    }

    public function test_twig_expressions_render_in_templates(): void
    {
        $this->modx->setPlaceholder('name', 'MODX');

        $this->assertSame(
            'Template 4 MODX',
            $this->renderTemplateContent('Template {{ 2 + 2 }} [[+name]]')
        );
    }

    public function test_twig_expressions_render_in_resource_content(): void
    {
        $output = $this->renderResourceContent('Resource {{ 3 * 3 }} [[+name]]', ['name' => 'MODX']);

        $this->assertSame('Resource 9 MODX', $output);
    }

    public function test_twig_expressions_evaluate(): void
    {
        $content = 'Sum: {{ 2 + 3 }}';

        $this->assertSame('Sum: 5', $this->processContent($content));
    }

    public function test_invalid_twig_syntax_passes_through_gracefully(): void
    {
        $content = 'Broken {{ name';

        $this->assertSame('Broken {{ name', $this->processContent($content));
    }

    public function test_chunk_with_twig_content_renders_twig(): void
    {
        $this->registerChunk('TwigChunk', 'Hello {{ name }}');
        $content = '[[$TwigChunk? &name=`World`]]';

        $this->assertSame('Hello World', $this->processContent($content));
    }

    public function test_chunk_renders_modx_placeholders_and_twig(): void
    {
        $this->registerChunk('MixedChunk', 'Hi [[+subject]] {{ subject|upper }}');

        $output = $this->modx->getChunk('MixedChunk', ['subject' => 'modx']);
        $this->assertSame('Hi modx MODX', $output);
    }

    public function test_twig_conditionals_wrap_modx_tags(): void
    {
        $this->registerSnippet('PlainSnippet', 'return "Snippet output";');
        $content = 'Start {% if true %}[[PlainSnippet]]{% endif %} End';

        $this->assertSame('Start Snippet output End', $this->processContent($content));
    }

    public function test_snippet_output_with_twig_expression_renders(): void
    {
        $this->registerSnippet('TwigSnippet', 'return "Twig math {{ 3 * 3 }}";');

        $this->assertSame('Twig math 9', $this->processContent('[[TwigSnippet]]'));
    }

    public function test_snippet_output_with_twig_template_syntax_renders(): void
    {
        $this->registerSnippet(
            'TwigTemplateSnippet',
            'return "{% set word = \"twig\" %}Word {{ word|upper }}";'
        );

        $this->assertSame(
            'Word TWIG',
            $this->processContent('[[TwigTemplateSnippet]]')
        );
    }
}
