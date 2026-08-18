<?php
declare(strict_types=1);

namespace MODX\Revolution\Tests\Twig;

require_once __DIR__ . '/ParserTestCase.php';

use Boffinate\Twig\PdoTools\CoreToolsTwig;
use Boffinate\Twig\PdoTools\FetchTwig;
use MODX\Revolution\modChunk;

/*
 * pdoTools-based snippets fetch tpl chunks through CoreTools::getChunk()
 * and Fetch, bypassing the MODX parser's getElement() and therefore the
 * modChunkTwig proxy. These tests cover the Twig-aware subclasses that
 * close that gap when registered via the pdotools_pdotools_class and
 * pdotools_fetch_class system settings.
 */
class PdoToolsTwigChunkTest extends ParserTestCase
{
    /** @var modChunk[] */
    private array $dbChunks = [];

    protected function usesTwigParser(): bool
    {
        return true;
    }

    /**
     * @after
     */
    public function cleanupDbChunks(): void
    {
        foreach ($this->dbChunks as $chunk) {
            $chunk->remove();
        }
        $this->dbChunks = [];
    }

    /*
     * CoreTools::_loadElement() resolves chunks with $modx->getObject(), so
     * unlike the parser tests these fixtures must be real database rows.
     */
    private function createDbChunk(string $name, string $content): void
    {
        $existing = $this->modx->getObject(modChunk::class, ['name' => $name]);
        if ($existing instanceof modChunk) {
            $existing->remove();
        }

        $chunk = $this->modx->newObject(modChunk::class);
        $chunk->set('name', $name);
        $chunk->setContent($content);
        $this->assertTrue((bool) $chunk->save(), 'Could not create Chunk fixture: `' . $name . '`.');

        $this->dbChunks[] = $chunk;
    }

    public function test_coretools_subclass_renders_twig_in_chunk(): void
    {
        $this->createDbChunk('TwigPdoChunk', 'Hello {{ name }}');
        $pdoTools = new CoreToolsTwig($this->modx);

        $this->assertSame('Hello World', $pdoTools->getChunk('TwigPdoChunk', ['name' => 'World']));
    }

    public function test_fetch_subclass_renders_twig_in_chunk(): void
    {
        $this->createDbChunk('TwigPdoFetchChunk', 'Hello {{ name }}');
        $pdoFetch = new FetchTwig($this->modx);

        $this->assertSame('Hello World', $pdoFetch->getChunk('TwigPdoFetchChunk', ['name' => 'World']));
    }

    public function test_coretools_subclass_renders_twig_alongside_modx_placeholders_in_fast_mode(): void
    {
        $this->createDbChunk('TwigPdoMixedChunk', '{{ greeting|capitalize }} [[+name]]');
        $pdoTools = new CoreToolsTwig($this->modx);

        $this->assertSame(
            'Hello World',
            $pdoTools->getChunk('TwigPdoMixedChunk', ['greeting' => 'hello', 'name' => 'World'], true)
        );
    }

    public function test_inline_chunk_renders_twig(): void
    {
        $pdoTools = new CoreToolsTwig($this->modx);

        $this->assertSame(
            'Hello World',
            $pdoTools->getChunk('@INLINE Hello {{ name }}', ['name' => 'World'])
        );
    }

    /*
     * pdoTools' own long-standing convention is that `{{ ... }}` inside an
     * @INLINE body is MODX-tag shorthand — `{{~{{+id}}}}` is a link tag, not
     * a Twig expression — and _loadElement() rewrites those braces to `[[`
     * before parsing. Sites have tpl chunks written that way, so a body that
     * is not valid Twig has to come back untouched for pdoTools to convert
     * as before. This is the compatibility promise that makes the subclasses
     * safe to switch on, and since the document is never Twig-compiled they
     * are the only thing rendering these chunks.
     */
    public function test_inline_chunk_keeps_legacy_modx_tag_shorthand(): void
    {
        $pdoTools = new CoreToolsTwig($this->modx);

        $output = (string) $pdoTools->getChunk(
            '@INLINE <a href="{{~{{+id}}}}">{{+label}}</a>',
            ['id' => 1, 'label' => 'Home']
        );

        $this->assertStringNotContainsString('{{', $output, 'shorthand must be converted, not left raw');
        $this->assertStringContainsString('>Home<', $output);
    }

    /*
     * Invalid Twig in an inline body must not be swallowed: the source is
     * kept so pdoTools still gets its chance at it.
     */
    public function test_inline_chunk_with_invalid_twig_falls_back_to_source(): void
    {
        $pdoTools = new CoreToolsTwig($this->modx);

        $output = (string) $pdoTools->getChunk('@INLINE {{ label| }}', ['label' => 'Home']);

        $this->assertStringNotContainsString('Home', $output);
    }

    public function test_parse_chunk_renders_twig(): void
    {
        $this->createDbChunk('TwigPdoParseChunk', 'Hello {{ name }}');
        $pdoTools = new CoreToolsTwig($this->modx);

        $this->assertSame('Hello World', $pdoTools->parseChunk('TwigPdoParseChunk', ['name' => 'World']));
    }

    public function test_subclass_falls_back_to_plain_pdotools_without_twig_service(): void
    {
        $this->createDbChunk('TwigPdoPlainChunk', 'Hello {{ name }}');
        $twig = $this->modx->services->get('twigparser');
        unset($this->modx->services['twigparser']);

        try {
            $pdoTools = new CoreToolsTwig($this->modx);

            $this->assertSame(
                'Hello {{ name }}',
                $pdoTools->getChunk('TwigPdoPlainChunk', ['name' => 'World'])
            );
        } finally {
            $this->modx->services->add('twigparser', $twig);
        }
    }

    /*
     * pdoTools' bootstrap resolves its service classes from the
     * pdotools_pdotools_class / pdotools_fetch_class system settings each
     * time the factories run, which is how the subclasses activate on a
     * real site.
     */
    public function test_subclasses_activate_via_pdotools_class_settings(): void
    {
        $this->createDbChunk('TwigPdoServiceChunk', 'Hello {{ name }}');
        $this->modx->setOption('pdotools_pdotools_class', CoreToolsTwig::class);
        $this->modx->setOption('pdotools_fetch_class', FetchTwig::class);

        try {
            $pdoTools = $this->modx->services->get(\ModxPro\PdoTools\CoreTools::class);
            $pdoFetch = $this->modx->services->get(\ModxPro\PdoTools\Fetch::class);

            $this->assertInstanceOf(CoreToolsTwig::class, $pdoTools);
            $this->assertInstanceOf(FetchTwig::class, $pdoFetch);
            $this->assertSame('Hello World', $pdoTools->getChunk('TwigPdoServiceChunk', ['name' => 'World']));
            $this->assertSame('Hello World', $pdoFetch->getChunk('TwigPdoServiceChunk', ['name' => 'World']));
        } finally {
            $this->modx->setOption('pdotools_pdotools_class', \ModxPro\PdoTools\CoreTools::class);
            $this->modx->setOption('pdotools_fetch_class', \ModxPro\PdoTools\Fetch::class);
        }
    }
}
