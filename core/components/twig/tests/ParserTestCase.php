<?php
declare(strict_types=1);

namespace MODX\Revolution\Tests\Twig;

use Boffinate\Twig\Twig;
use MODX\Revolution\MODxTestCase;
use MODX\Revolution\modChunk;
use MODX\Revolution\modPlugin;
use MODX\Revolution\modPluginEvent;
use MODX\Revolution\modResource;
use MODX\Revolution\modSnippet;
use Boffinate\Twig\Proxy\modTemplateTwig;
use MODX\Revolution\modTemplate;
use MODX\Revolution\modTemplateVar;
use MODX\Revolution\Processors\Element\Plugin\Create as PluginCreate;
use MODX\Revolution\Processors\Element\Snippet\Create;

require_once __DIR__ . '/WritesTemplateFiles.php';

abstract class ParserTestCase extends MODxTestCase
{
    use WritesTemplateFiles;

    /** @var string[] */
    private array $registeredSnippets = [];
    /** @var string[] */
    private array $registeredPlugins = [];
    /** @var string[] */
    private array $registeredTemplateVars = [];
    /** @var int[] */
    private array $registeredResourceIds = [];
    /** @var int[] */
    private array $registeredTemplateIds = [];
    private ?modResource $originalResource = null;
    private bool $hasPdoToolsConfigSnapshot = false;
    private mixed $originalUseFenomParser = null;

    /**
     * @before
     */
    public function setUpFixtures(): void
    {
        parent::setUpFixtures();
        $this->originalResource = $this->modx->resource instanceof modResource ? $this->modx->resource : null;

        $this->snapshotPdoToolsConfig();
        $this->loadPdoTools();
        if ($this->usesTwigParser()) {
            $this->loadTwig();
            $this->useTwigParser();
        } else {
            $this->modx->getParser();
        }

        $this->resetRuntimeState();
        $this->modx->setOption('parser_max_iterations', 10);
    }

    /**
     * @after
     */
    public function tearDownFixtures(): void
    {
        $this->restorePdoToolsConfig();
        $this->cleanupSnippets();
        $this->cleanupPlugins();
        $this->cleanupResources();
        $this->cleanupTemplates();
        $this->cleanupTemplateVars();
        $this->resetRuntimeState();
        $this->modx->resource = $this->originalResource;
        if (!empty($this->modx->placeholders)) {
            $this->modx->unsetPlaceholders(array_keys($this->modx->placeholders));
        }

        parent::tearDownFixtures();
    }

    protected function usesTwigParser(): bool
    {
        return false;
    }

    protected function loadPdoTools(): void
    {
        $bootstrapPath = MODX_CORE_PATH . 'components/pdotools/bootstrap.php';
        if (!$this->modx->services->has('pdotools') && file_exists($bootstrapPath)) {
            $modx = $this->modx;
            require_once $bootstrapPath;
        }
    }

    /** Temporarily change Fenom parsing on the shared pdoTools service. */
    protected function withFenomParser(bool $enabled, callable $callback): mixed
    {
        $pdoTools = $this->modx->services->get('pdotools');
        $previous = $pdoTools->config('useFenomParser');

        if ($previous !== $enabled) {
            $pdoTools->config(['useFenomParser' => $enabled]);
        }

        try {
            return $callback();
        } finally {
            if ($pdoTools->config('useFenomParser') !== $previous) {
                $pdoTools->config(['useFenomParser' => $previous]);
            }
        }
    }

    private function snapshotPdoToolsConfig(): void
    {
        $this->originalUseFenomParser = $this->modx->services->has('pdotools')
            ? $this->modx->services->get('pdotools')->config('useFenomParser')
            : $this->modx->getOption('pdotools_fenom_parser', null, false);
        $this->hasPdoToolsConfigSnapshot = true;
    }

    private function restorePdoToolsConfig(): void
    {
        if (!$this->hasPdoToolsConfigSnapshot) {
            return;
        }

        $this->hasPdoToolsConfigSnapshot = false;

        if (!$this->modx->services->has('pdotools')) {
            return;
        }

        $pdoTools = $this->modx->services->get('pdotools');
        if ($pdoTools->config('useFenomParser') !== $this->originalUseFenomParser) {
            $pdoTools->config(['useFenomParser' => $this->originalUseFenomParser]);
        }
    }

    protected function loadTwig(): void
    {
        if (
            !$this->modx->services->has('twigparser')
            && !$this->modx->services->has(Twig::class)
        ) {
            $modx = $this->modx;
            require_once MODX_CORE_PATH . 'components/twig/bootstrap.php';
        }
    }

    /**
     * The installed parser, asserted to be ours — the entry point for tests
     * that drive Twig directly rather than through processContent().
     */
    protected function twigParser(): Twig
    {
        $parser = $this->modx->parser;
        $this->assertInstanceOf(Twig::class, $parser);

        return $parser;
    }

    protected function useTwigParser(): void
    {
        $parser = new Twig($this->modx);
        $this->modx->parser = $parser;
        if (!$this->modx->services->has('twigparser')) {
            $this->modx->services->add('twigparser', $parser);
        }
    }

    protected function registerChunk(string $name, string $content, array $fields = []): void
    {
        $classKey = $this->modx->loadClass(modChunk::class) ?: modChunk::class;
        $chunk = $this->modx->newObject(modChunk::class);
        $chunk->fromArray(
            array_merge([
                'id' => count($this->modx->sourceCache[modChunk::class] ?? []) + 1,
                'name' => $name,
                'content' => $content,
                'properties' => [],
                'static' => false,
            ], $fields),
            '',
            true,
            true
        );

        $cacheEntry = [
            'fields' => $chunk->toArray(),
            'policies' => [],
            'source' => [],
        ];
        $this->modx->sourceCache[$classKey][$name] = $cacheEntry;
        $this->modx->sourceCache[modChunk::class][$name] = $cacheEntry;
        $this->modx->elementCache = [];
    }

    protected function registerSnippet(string $name, string $code): void
    {
        $existingSnippet = $this->modx->getObject(modSnippet::class, ['name' => $name]);
        if ($existingSnippet instanceof modSnippet) {
            $existingSnippet->remove();
        }

        $result = $this->modx->runProcessor(Create::class, [
            'name' => $name,
            'snippet' => $code,
            'content' => $code,
            'properties' => [],
            'static' => false,
        ]);

        $this->assertTrue(
            $result && !$result->isError(),
            'Could not create Snippet: `' . $name . '`: ' . implode("\n", $result->getAllErrors())
        );

        $this->registeredSnippets[] = $name;
    }

    /*
     * Renders a string as element source: Twig first, then the MODX tag pass
     * over the result — the flow the modChunkTwig/modTemplateTwig proxies
     * give real elements. Use this to exercise Twig features (helpers,
     * extensions, error fallbacks) in one call without registering an
     * element fixture.
     */
    protected function processContent(string $content, int $depth = 10): string
    {
        $content = (string) $this->twigParser()->renderString($content, []);

        return $this->processDocument($content, $depth);
    }

    /*
     * Runs the parser over a raw string the way MODX processes the assembled
     * uncacheable document. The document never reaches Twig — elements
     * resolved during the tag pass still render their own Twig via the
     * proxies — so this is the helper for asserting what must NOT compile.
     */
    protected function processDocument(string $content, int $depth = 10): string
    {
        $this->modx->elementCache = [];
        $this->modx->parser->processElementTags('', $content, true, true, '[[', ']]', [], $depth);

        return $content;
    }

    /*
     * Renders a template the way MODX does: the element's own pass, then the
     * MODX tag pass over the result.
     *
     * Instantiates the Twig proxy directly, which is what bootstrap.php
     * makes modResource's Template relation resolve to. Tests that need to
     * prove the relation itself is wired use renderResourceWithTemplate().
     */
    protected function renderTemplateContent(string $content, array $properties = []): string
    {
        return $this->renderThroughTemplateClass(modTemplateTwig::class, 'TwigTestTemplate', $content, $properties);
    }

    /**
     * Renders content through an unproxied core modTemplate, for asserting
     * what MODX does without this addon's Template decoration.
     */
    protected function renderPlainTemplateContent(string $content, array $properties = []): string
    {
        return $this->renderThroughTemplateClass(modTemplate::class, 'TwigTestPlainTemplate', $content, $properties);
    }

    /**
     * @param class-string<modTemplate> $class
     */
    private function renderThroughTemplateClass(
        string $class,
        string $name,
        string $content,
        array $properties
    ): string {
        $template = $this->modx->newObject($class);
        $template->fromArray([
            'id' => 1,
            'templatename' => $name,
            'content' => $content,
            'properties' => [],
            'static' => false,
        ], '', true, true);

        $output = $template->process($properties);
        $this->modx->parser->processElementTags('', $output, true, true, '[[', ']]', [], 10);

        return $output;
    }

    protected function registerTemplate(string $content, array $fields = []): modTemplate
    {
        $template = $this->modx->newObject(modTemplate::class);
        $template->fromArray(array_merge([
            'templatename' => 'TwigTestTemplate' . bin2hex(random_bytes(4)),
            'content' => $content,
        ], $fields), '', true, true);

        $this->assertTrue((bool) $template->save(), 'Could not create Template fixture.');
        $this->registeredTemplateIds[] = (int) $template->get('id');

        return $template;
    }

    /**
     * Renders a real resource through a real template, via the xPDO relation
     * — the full path a web request takes, including the Template proxy.
     */
    protected function renderResourceWithTemplate(string $templateContent, string $resourceContent): string
    {
        $template = $this->registerTemplate($templateContent);
        $resource = $this->registerResource([
            'template' => (int) $template->get('id'),
            'content' => $resourceContent,
        ]);

        /* Re-fetch so the relation resolves fresh rather than from _relatedObjects. */
        $fetched = $this->modx->getObject(modResource::class, (int) $resource->get('id'));
        $previousResource = $this->modx->resource;
        $this->modx->resource = $fetched;

        try {
            $fetched->prepare();

            return (string) $fetched->_output;
        } finally {
            $this->modx->resource = $previousResource;
        }
    }

    protected function renderResourceContent(string $content, array $properties = []): string
    {
        $resource = $this->modx->newObject(modResource::class);
        $resource->fromArray([
            'id' => 1,
            'pagetitle' => 'Twig Test Resource',
            'content' => $content,
            'cacheable' => false,
            'context_key' => 'web',
        ], '', true, true);

        return $resource->parseContent($properties);
    }

    protected function registerResource(array $fields = []): modResource
    {
        $resource = $this->modx->newObject(modResource::class);
        $resource->fromArray(array_merge([
            'pagetitle' => 'Twig Test Resource ' . bin2hex(random_bytes(4)),
            'alias' => 'twig-test-' . bin2hex(random_bytes(4)),
            'content' => '',
            'template' => 0,
            'published' => true,
            'cacheable' => false,
            'context_key' => 'web',
        ], $fields), '', true, true);

        $this->assertTrue((bool) $resource->save(), 'Could not create Resource fixture.');
        $this->registeredResourceIds[] = (int) $resource->get('id');

        return $resource;
    }

    protected function registerTemplateVar(string $name, array $fields = []): modTemplateVar
    {
        $existingTemplateVar = $this->modx->getObject(modTemplateVar::class, ['name' => $name]);
        if ($existingTemplateVar instanceof modTemplateVar) {
            $existingTemplateVar->remove();
        }

        $templateVar = $this->modx->newObject(modTemplateVar::class);
        $templateVar->fromArray(array_merge([
            'name' => $name,
            'caption' => $name,
            'type' => 'text',
            'default_text' => '',
        ], $fields), '', true, true);

        $this->assertTrue((bool) $templateVar->save(), 'Could not create Template Variable fixture: `' . $name . '`.');
        $this->registeredTemplateVars[] = $name;

        return $templateVar;
    }

    protected function assignTemplateVarValue(modResource $resource, string $name, string $value): void
    {
        $this->assertTrue(
            $resource->setTVValue($name, $value),
            'Could not assign Template Variable `' . $name . '` to Resource `' . $resource->get('id') . '`.'
        );
    }

    protected function registerPlugin(string $name, string $code, array $events): void
    {
        $name .= '_' . bin2hex(random_bytes(4));

        $existingPlugin = $this->modx->getObject(modPlugin::class, ['name' => $name]);
        if ($existingPlugin instanceof modPlugin) {
            $this->removePluginEvents($existingPlugin);
            $existingPlugin->remove();
        }

        $result = $this->modx->runProcessor(PluginCreate::class, [
            'name' => $name,
            'plugincode' => $code,
            'content' => $code,
            'properties' => [],
            'disabled' => false,
            'events' => $events,
        ]);

        $this->assertTrue(
            $result && !$result->isError(),
            'Could not create Plugin: `' . $name . '`: ' . implode("\n", $result->getAllErrors())
        );

        $this->registeredPlugins[] = $name;
        $this->resetPluginRuntimeState();
    }

    protected function executePluginFile(string $path, array $variables = [])
    {
        $event = new \stdClass();
        $event->_output = null;
        $previousEvent = $this->modx->event ?? null;
        $this->modx->event = $event;

        $plugin = new class($this->modx) {
            public function __construct(public $modx)
            {
            }
        };

        $runner = \Closure::bind(function (string $path, array $variables) {
            extract($variables, EXTR_SKIP);
            $modx = $this->modx;

            return require $path;
        }, $plugin, $plugin);

        $returned = $runner($path, $variables);
        /*
         * Read back through $modx->event the way modX::invokeEvent() does: a
         * nested invokeEvent() inside the plugin (Twig::init() firing
         * OnTwigInit on the first render of the process) replaces
         * $modx->event, and the plugin writes to whichever object is current.
         */
        $output = $this->modx->event->_output ?? $returned;
        $this->modx->event = $previousEvent;

        return $output;
    }

    protected function resetPluginRuntimeState(): void
    {
        $this->modx->eventMap = null;
        $this->modx->pluginCache = [];
    }

    protected function resetRuntimeState(): void
    {
        $this->modx->elementCache = [];
        $this->modx->sourceCache = [];
        $this->resetPluginRuntimeState();
    }

    private function cleanupSnippets(): void
    {
        if (empty($this->registeredSnippets)) {
            return;
        }

        $snippets = $this->modx->getCollection(modSnippet::class, ['name:IN' => $this->registeredSnippets]);
        foreach ($snippets as $snippet) {
            $snippet->remove();
        }

        $this->registeredSnippets = [];
    }

    private function cleanupPlugins(): void
    {
        if (empty($this->registeredPlugins)) {
            return;
        }

        $plugins = $this->modx->getCollection(modPlugin::class, ['name:IN' => $this->registeredPlugins]);
        foreach ($plugins as $plugin) {
            $this->removePluginEvents($plugin);
            $plugin->remove();
        }

        $this->registeredPlugins = [];
    }

    private function cleanupResources(): void
    {
        if (empty($this->registeredResourceIds)) {
            return;
        }

        $resources = $this->modx->getCollection(modResource::class, ['id:IN' => $this->registeredResourceIds]);
        foreach ($resources as $resource) {
            $resource->remove();
        }

        $this->registeredResourceIds = [];
    }

    private function cleanupTemplates(): void
    {
        if (empty($this->registeredTemplateIds)) {
            return;
        }

        $templates = $this->modx->getCollection(modTemplate::class, ['id:IN' => $this->registeredTemplateIds]);
        foreach ($templates as $template) {
            $template->remove();
        }

        $this->registeredTemplateIds = [];
    }

    private function cleanupTemplateVars(): void
    {
        if (empty($this->registeredTemplateVars)) {
            return;
        }

        $templateVars = $this->modx->getCollection(modTemplateVar::class, ['name:IN' => $this->registeredTemplateVars]);
        foreach ($templateVars as $templateVar) {
            $templateVar->remove();
        }

        $this->registeredTemplateVars = [];
    }

    private function removePluginEvents(modPlugin $plugin): void
    {
        $events = $this->modx->getCollection(modPluginEvent::class, ['pluginid' => $plugin->get('id')]);
        foreach ($events as $event) {
            $event->remove();
        }
    }
}
