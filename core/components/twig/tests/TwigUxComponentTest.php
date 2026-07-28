<?php
declare(strict_types=1);

namespace MODX\Revolution\Tests\Twig;

require_once __DIR__ . '/ParserTestCase.php';

use Boffinate\Twig\Twig;

class TwigUxComponentTest extends ParserTestCase
{
    protected function usesTwigParser(): bool
    {
        return true;
    }

    private function registerComponents(array $templates): void
    {
        $files = [];
        foreach ($templates as $name => $content) {
            $files['components/' . $name . '.html.twig'] = $content;
        }

        $this->twigParser()->registerTemplatePath($this->writeTemplateFiles($files, 'twig-ux-'));
    }

    private const BUTTON_TEMPLATE = <<<'TWIG'
        {% props label, variant = 'primary' %}
        <a{{ attributes.defaults({class: 'a-btn a-btn--' ~ variant}) }}>{{ label }}</a>
        TWIG;

    public function test_component_html_syntax_renders_with_props(): void
    {
        $this->registerComponents(['Button' => self::BUTTON_TEMPLATE]);

        $output = trim($this->twigParser()->renderString('<twig:Button label="Book now" />', []));

        $this->assertSame('<a class="a-btn a-btn--primary">Book now</a>', $output);
    }

    public function test_component_prop_expressions_and_extra_attributes(): void
    {
        $this->registerComponents(['Button' => self::BUTTON_TEMPLATE]);

        $output = trim($this->twigParser()->renderString(
            '<twig:Button :label="\'Book \' ~ what" variant="secondary" data-tracking="cta" />',
            ['what' => 'tickets']
        ));

        $this->assertSame(
            '<a class="a-btn a-btn--secondary" data-tracking="cta">Book tickets</a>',
            $output
        );
    }

    public function test_component_function_syntax_renders(): void
    {
        $this->registerComponents(['Button' => self::BUTTON_TEMPLATE]);

        $output = trim($this->twigParser()->renderString(
            "{{ component('Button', { label: 'Go' }) }}",
            []
        ));

        $this->assertSame('<a class="a-btn a-btn--primary">Go</a>', $output);
    }

    public function test_component_with_slot_content(): void
    {
        $this->registerComponents([
            'Card' => '<div class="card"><h2>{{ title }}</h2>{% block content %}{% endblock %}</div>',
        ]);

        $output = trim($this->twigParser()->renderString(
            '<twig:Card title="Hello"><p>Rich {{ word }} content</p></twig:Card>',
            ['word' => 'slot']
        ));

        $this->assertSame('<div class="card"><h2>Hello</h2><p>Rich slot content</p></div>', $output);
    }

    public function test_component_composes_other_components(): void
    {
        $this->registerComponents([
            'Button' => self::BUTTON_TEMPLATE,
            'Hero' => '<section><twig:Button :label="cta" /></section>',
        ]);

        $output = trim($this->twigParser()->renderString('<twig:Hero cta="Visit" />', []));

        $this->assertSame('<section><a class="a-btn a-btn--primary">Visit</a></section>', $output);
    }

    public function test_component_renders_from_chunk_through_modx_parser(): void
    {
        $this->registerComponents(['Button' => self::BUTTON_TEMPLATE]);
        $this->registerChunk('UxButtonChunk', '<twig:Button :label="name" />');

        $this->assertSame(
            '<a class="a-btn a-btn--primary">World</a>',
            trim($this->processContent('[[$UxButtonChunk? &name=`World`]]'))
        );
    }

    public function test_component_syntax_detected_without_twig_delimiters(): void
    {
        $this->assertTrue(Twig::containsTwigSyntax('<twig:Button label="x" />'));
    }

    public function test_missing_required_prop_is_handled_as_render_error(): void
    {
        $this->modx->setOption('twig.debug', false);
        $this->registerComponents(['Button' => self::BUTTON_TEMPLATE]);

        $this->assertSame('', $this->twigParser()->renderString('<twig:Button variant="ghost" />', []));

        $this->modx->setOption('twig.debug', true);
    }

    public function test_autoescape_applies_to_component_props(): void
    {
        $this->registerComponents(['Button' => self::BUTTON_TEMPLATE]);

        $output = trim($this->twigParser()->renderString(
            '<twig:Button :label="evil" />',
            ['evil' => '<script>alert(1)</script>']
        ));

        $this->assertStringNotContainsString('<script>', $output);
        $this->assertStringContainsString('&lt;script&gt;', $output);
    }

    public function test_components_can_be_disabled_by_setting(): void
    {
        $this->modx->setOption('twig.components', false);
        $parser = new Twig($this->modx);

        $env = $parser->getEnvironment();
        $this->assertFalse($env->hasExtension(\Symfony\UX\TwigComponent\Twig\ComponentExtension::class));

        $this->modx->setOption('twig.components', true);
    }
}
