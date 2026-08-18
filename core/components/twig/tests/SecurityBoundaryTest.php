<?php
declare(strict_types=1);

namespace MODX\Revolution\Tests\Twig;

require_once __DIR__ . '/ParserTestCase.php';

use MODX\Revolution\modResource;

/*
 * Where Twig is allowed to compile, and where it must not.
 *
 * Twig renders before the MODX parser, so any string it is handed becomes
 * executable template source with the `modx` object in scope. The whole
 * safety model of this addon is therefore about *provenance*: Twig compiles
 * elements — things an author with element access wrote — and nothing else.
 *
 * The tests below are the evidence for that claim, including the case that
 * motivated it: a query-string parameter echoed back into the page. That is
 * not an editor-trust question at all, because nobody authorised the input;
 * compiling the assembled document would be unauthenticated server-side
 * template injection reaching the full modx object.
 */
class SecurityBoundaryTest extends ParserTestCase
{
    /* An expression whose result cannot occur by accident in page markup. */
    private const PROBE = '{{ 31337 * 2 }}';
    private const PROBE_RESULT = '62674';

    protected function usesTwigParser(): bool
    {
        return true;
    }

    /**
     * @after
     */
    public function clearRequestState(): void
    {
        unset($_GET['twig_probe']);
    }

    private function registerQueryEchoSnippet(): void
    {
        /* The shape of any "you searched for X" / "no results for X" page. */
        $this->registerSnippet('twigSecurityEcho', 'return $_GET["twig_probe"] ?? "";');
    }

    // ---------------------------------------------------------------
    // Visitor input — never compiled
    // ---------------------------------------------------------------

    public function test_query_string_echoed_into_the_page_is_not_compiled(): void
    {
        $this->registerQueryEchoSnippet();
        $_GET['twig_probe'] = self::PROBE;

        $output = $this->processDocument('<p>You searched for [[!twigSecurityEcho]]</p>');

        $this->assertStringNotContainsString(self::PROBE_RESULT, $output);
        $this->assertStringContainsString(self::PROBE, $output);
    }

    /*
     * Not just arithmetic: the `modx` global is a live object, so compiling
     * visitor input means handing an anonymous request the MODX API.
     */
    public function test_visitor_input_cannot_reach_the_modx_object(): void
    {
        $this->registerQueryEchoSnippet();
        $_GET['twig_probe'] = '{{ modx.config.site_name }}';

        $output = $this->processDocument('<p>[[!twigSecurityEcho]]</p>');

        $this->assertStringNotContainsString((string) $this->modx->getOption('site_name'), $output);
        $this->assertStringContainsString('{{ modx.config.site_name }}', $output);
    }

    public function test_visitor_input_cannot_invoke_snippets(): void
    {
        $this->registerQueryEchoSnippet();
        $this->registerSnippet('twigSecurityMarker', 'return "SNIPPET-RAN";');
        $_GET['twig_probe'] = '{{ snippet("twigSecurityMarker") }}';

        $output = $this->processDocument('<p>[[!twigSecurityEcho]]</p>');

        $this->assertStringNotContainsString('SNIPPET-RAN', $output);
    }

    // ---------------------------------------------------------------
    // Editor-supplied content — never compiled
    // ---------------------------------------------------------------

    public function test_resource_content_is_not_compiled(): void
    {
        $output = $this->renderResourceContent('Editor typed ' . self::PROBE);

        $this->assertStringNotContainsString(self::PROBE_RESULT, $output);
    }

    /*
     * The provenance point behind rendering templates in getContent(): the
     * template's own Twig runs while the string is still template source.
     * A moment later [[*content]] has merged the resource body into it, and
     * compiling then would compile whatever the editor typed.
     */
    public function test_editor_content_merged_into_a_template_is_not_compiled(): void
    {
        $output = $this->renderResourceWithTemplate(
            '<h1>{{ 6 * 7 }}</h1><main>[[*content]]</main>',
            'Editor typed ' . self::PROBE
        );

        $this->assertStringContainsString('<h1>42</h1>', $output, 'template Twig must render');
        $this->assertStringNotContainsString(self::PROBE_RESULT, $output, 'editor content must not compile');
        $this->assertStringContainsString(self::PROBE, $output);
    }

    public function test_template_variable_values_are_not_compiled(): void
    {
        $this->registerTemplateVar('twigSecurityTv');
        $resource = $this->registerResource(['pagetitle' => 'TV probe']);
        $this->assignTemplateVarValue($resource, 'twigSecurityTv', self::PROBE);
        $this->modx->resource = $this->modx->getObject(modResource::class, (int) $resource->get('id'));

        $output = $this->processDocument('<p>[[*twigSecurityTv]]</p>');

        $this->assertStringNotContainsString(self::PROBE_RESULT, $output);
        $this->assertStringContainsString(self::PROBE, $output);
    }

    /*
     * Snippet output is not an element template. A third-party snippet that
     * happens to emit `{{ }}` — or that echoes data it was given — must not
     * have that compiled. Twig belongs in the tpl chunks a snippet renders,
     * where the chunk author decided it.
     */
    public function test_snippet_output_is_not_compiled(): void
    {
        $this->registerSnippet('twigSecurityEmitter', 'return "emitted ' . self::PROBE . '";');

        $output = $this->processDocument('<p>[[!twigSecurityEmitter]]</p>');

        $this->assertStringNotContainsString(self::PROBE_RESULT, $output);
    }

    // ---------------------------------------------------------------
    // Elements — compiled, by design
    // ---------------------------------------------------------------

    public function test_chunks_compile_their_own_twig(): void
    {
        $this->registerChunk('TwigSecurityChunk', 'Chunk {{ 6 * 7 }}');

        $output = $this->processDocument('[[$TwigSecurityChunk]]');

        $this->assertStringContainsString('Chunk 42', $output);
    }

    public function test_templates_compile_their_own_twig(): void
    {
        $output = $this->renderResourceWithTemplate('<h1>{{ 6 * 7 }}</h1>', 'plain body');

        $this->assertStringContainsString('<h1>42</h1>', $output);
    }

    // ---------------------------------------------------------------
    // The accepted boundary, stated as a test rather than left implicit
    // ---------------------------------------------------------------

    /*
     * modChunkTwig renders a chunk's OUTPUT — after the MODX parser has
     * substituted its tags — because that ordering is the integration seam
     * between the two templating systems ({{ '[[+date]]'|date(...) }} and
     * friends). The consequence is that a value interpolated into a chunk
     * becomes part of that chunk's Twig source.
     *
     * For editor-supplied values that is an accepted trade: the chunk author
     * chose what to pull in, and content authors are trusted to that degree.
     *
     * For REQUEST-DERIVED values it is not safe, and no configuration in
     * this addon makes it safe. Do not pass request data through a chunk
     * that Twig will then render — put it in a snippet's output, which is
     * never compiled (see test_snippet_output_is_not_compiled).
     *
     * This test exists so that boundary is visible and versioned. If chunk
     * ordering is ever revisited, this is the test that should change first.
     */
    public function test_values_interpolated_into_a_chunk_are_part_of_its_twig_source(): void
    {
        $this->registerChunk('TwigSecuritySeamChunk', '<h1>[[+seam_value]]</h1>');
        $this->modx->setPlaceholder('seam_value', self::PROBE);

        $output = $this->processDocument('[[$TwigSecuritySeamChunk]]');

        $this->assertStringContainsString(
            self::PROBE_RESULT,
            $output,
            'documented behaviour: a chunk Twig-renders what it interpolates'
        );
    }

    // ---------------------------------------------------------------
    // Failure modes
    // ---------------------------------------------------------------

    /*
     * A broken expression in a template must not blank it.
     * modResource::process() reads an empty template result as "no template"
     * and renders the raw resource content as the whole page — so an
     * empty-string fallback would strip the layout off a live site.
     */
    public function test_broken_template_twig_neutralizes_rather_than_blanking(): void
    {
        $this->modx->setOption('twig.debug', false);

        try {
            $output = $this->renderResourceWithTemplate('<h1>Kept</h1>{{ oops| }}', 'body');
        } finally {
            $this->modx->setOption('twig.debug', true);
        }

        $this->assertStringContainsString('<h1>Kept</h1>', $output);
        $this->assertStringNotContainsString('{{ oops| }}', $output, 'delimiters must be inert');
    }

    /*
     * Template source must never reach a visitor on a failed render: it can
     * contain logic and comments written on the assumption they stay server
     * side.
     */
    public function test_broken_chunk_twig_does_not_leak_source_in_production(): void
    {
        $this->modx->setOption('twig.debug', false);
        $this->registerChunk('TwigSecurityBrokenChunk', '{{ secret_internal_note| }}');

        try {
            $output = $this->processDocument('[[$TwigSecurityBrokenChunk]]');
        } finally {
            $this->modx->setOption('twig.debug', true);
        }

        $this->assertStringNotContainsString('secret_internal_note', $output);
    }
}
