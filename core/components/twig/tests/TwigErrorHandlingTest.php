<?php
declare(strict_types=1);

namespace MODX\Revolution\Tests\Twig;

require_once __DIR__ . '/ParserTestCase.php';

use Boffinate\Twig\Twig;

class TwigErrorHandlingTest extends ParserTestCase
{
    protected function usesTwigParser(): bool
    {
        return true;
    }

    /**
     * @after
     */
    public function restoreDebugOption(): void
    {
        $this->modx->setOption('twig.debug', true);
    }

    public function test_debug_mode_returns_source_on_error(): void
    {
        $this->modx->setOption('twig.debug', true);

        $content = 'Broken {{ name| }}';
        $this->assertSame($content, $this->twigParser()->renderString($content, []));
    }

    public function test_production_mode_returns_empty_string_on_element_error(): void
    {
        $this->modx->setOption('twig.debug', false);

        $this->assertSame('', $this->twigParser()->renderString('Broken {{ name| }}', []));
    }

    public function test_production_mode_neutralizes_document_pass_errors(): void
    {
        $this->modx->setOption('twig.debug', false);

        $content = 'Before {{ name| }} after';
        $output = $this->processContent($content);

        $this->assertStringNotContainsString('{{', $output);
        $this->assertStringContainsString('Before', $output);
        $this->assertStringContainsString('after', $output);
    }

    public function test_production_mode_blanks_broken_chunk_but_keeps_page(): void
    {
        $this->modx->setOption('twig.debug', false);
        $this->registerChunk('BrokenChunk', 'Broken {{ name| }}');

        $this->assertSame('AB', $this->processContent('A[[$BrokenChunk]]B'));
    }

    public function test_neutralize_makes_delimiters_inert(): void
    {
        $this->assertSame(
            '&#123;{ x }} &#123;% if %} &#123;# c #}',
            Twig::neutralizeTwigSyntax('{{ x }} {% if %} {# c #}')
        );
    }

    public function test_valid_twig_unaffected_in_production_mode(): void
    {
        $this->modx->setOption('twig.debug', false);

        $this->assertSame('4', $this->twigParser()->renderString('{{ 2 + 2 }}', []));
    }
}
