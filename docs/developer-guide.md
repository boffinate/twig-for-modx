# Developer Guide

This guide is for PHP developers building MODX extras that want to render Twig templates, register custom Twig functions, or integrate with the Twig parser from their own code.

## Getting the Parser

The Twig parser is registered as a service called `twigparser`:

```php
/** @var \Boffinate\Twig\Twig $twig */
$twig = $modx->services->get('twigparser');
```

The service is a singleton. Every call to `get('twigparser')` returns the same instance, so extensions and initializers registered anywhere are available everywhere.

### Checking if Twig is available

If your extra should work with or without the Twig extra installed, check before using it:

```php
if ($modx->services->has('twigparser')) {
    $twig = $modx->services->get('twigparser');
    // Use Twig rendering
} else {
    // Fall back to MODX chunk/placeholder rendering
}
```

This lets you ship an extra that takes advantage of Twig when it is present but does not require it.

## Rendering Twig Templates

### renderString()

The main method for rendering Twig markup from PHP:

```php
$twig = $modx->services->get('twigparser');

$html = $twig->renderString('<h1>{{ title }}</h1><p>{{ body }}</p>', [
    'title' => 'Hello',
    'body' => 'World',
]);
// $html = '<h1>Hello</h1><p>World</p>'
```

The second argument is an array of variables that become available in the template. These are the template's local context -- the same as ContentBlocks' `$phs` or the properties you would pass to a chunk.

The three globals (`modx`, `placeholders`, `modx_runtime`) are always available in addition to the variables you pass.

### Rendering with data from your extra

A typical pattern is to fetch data in PHP, then pass it to a Twig template string that the user configures:

```php
// Your extra fetches some data
$products = $this->getProducts($categoryId);

// The user provides a template (from a system setting, chunk, TV, etc.)
$template = $modx->getOption('myextra.product_tpl', null,
    '{% for product in products %}<div>{{ product.name }}</div>{% endfor %}'
);

// Render it
$twig = $modx->services->get('twigparser');
$output = $twig->renderString($template, [
    'products' => $products,
    'category_id' => $categoryId,
    'total' => count($products),
]);
```

### Rendering a MODX chunk through Twig

If you call `$modx->getChunk()`, the chunk content is automatically passed through Twig when the Twig parser is active. Chunk properties become Twig variables:

```php
// The chunk "ProductCard" can contain Twig syntax:
//   <div class="card"><h3>{{ name|upper }}</h3><p>{{ price }}</p></div>

$html = $modx->getChunk('ProductCard', [
    'name' => 'Widget',
    'price' => '£9.99',
]);
```

This works because the Twig parser wraps MODX chunks in a proxy (`modChunkTwig`) that renders the chunk output through Twig after MODX processes it. The chunk's MODX placeholders (`[[+name]]`) and Twig variables (`{{ name }}`) both work.

## Registering Custom Twig Functions

There are three ways to add functions, filters, or other Twig features. Choose whichever fits your use case.

### 1. Initializers

An initializer is a callable that receives the Twig `Environment` when it is first created. Use this for quick, one-off additions:

```php
$twig = $modx->services->get('twigparser');

$twig->registerInitializer(function (\Twig\Environment $env, \Boffinate\Twig\Twig $parser, \MODX\Revolution\modX $modx) {
    $env->addFunction(new \Twig\TwigFunction('product_url', function (int $id) use ($modx) {
        return $modx->makeUrl($id) . '?view=product';
    }));

    $env->addFilter(new \Twig\TwigFilter('currency', function (float $amount) {
        return '£' . number_format($amount, 2);
    }));

    $env->addGlobal('app_version', '2.1.0');
});
```

The initializer receives three arguments:

| Argument | Type | Description |
|----------|------|-------------|
| `$env` | `Twig\Environment` | The Twig environment -- add functions, filters, globals, tests |
| `$parser` | `Boffinate\Twig\Twig` | The Twig parser instance |
| `$modx` | `MODX\Revolution\modX` | The MODX instance |

Initializers run once when the Twig environment is first set up.

### 2. Extensions

A Twig extension is a class that bundles related functions, filters, and tests together. Use this when you have several related additions:

```php
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
use Twig\TwigFilter;

class MyExtraExtension extends AbstractExtension
{
    public function __construct(private \MODX\Revolution\modX $modx) {}

    public function getFunctions(): array
    {
        return [
            new TwigFunction('myextra_items', [$this, 'getItems']),
            new TwigFunction('myextra_count', [$this, 'getCount']),
        ];
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('myextra_format', [$this, 'format']),
        ];
    }

    public function getItems(int $categoryId): array
    {
        // Your data fetching logic
        return [];
    }

    public function getCount(int $categoryId): int
    {
        return count($this->getItems($categoryId));
    }

    public function format(string $value): string
    {
        return strtoupper(trim($value));
    }
}

// Register it
$twig = $modx->services->get('twigparser');
$twig->registerExtension(new MyExtraExtension($modx));
```

Templates can then use:

```twig
{% set items = myextra_items(5) %}
<p>{{ myextra_count(5) }} items</p>
{% for item in items %}
    <div>{{ item.name|myextra_format }}</div>
{% endfor %}
```

### 3. The OnTwigInit system event

If your extra is installed as a MODX package, you can register a plugin that listens to the `OnTwigInit` event. This runs when the Twig environment is created, before any templates are rendered:

```php
// Plugin code, listening to OnTwigInit
// Available variables: $twig, $parser, $modx

$twig->addFunction(new \Twig\TwigFunction('myextra_version', fn () => '1.0.0'));
$twig->addGlobal('myextra_config', [
    'api_url' => $modx->getOption('myextra.api_url'),
    'enabled' => (bool) $modx->getOption('myextra.enabled'),
]);

return '';
```

| Variable | Type | Description |
|----------|------|-------------|
| `$twig` | `Twig\Environment` | The Twig environment |
| `$parser` | `Boffinate\Twig\Twig` | The Twig parser instance |
| `$modx` | `MODX\Revolution\modX` | The MODX instance |

Use `OnTwigInit` when your extra is a standalone package and you want its Twig functions available automatically on every site that has both your extra and the Twig extra installed.

To register the event in your transport package:

```php
// In your _build/data/transport.plugins.php or equivalent
[
    'name' => 'MyExtraTwigPlugin',
    'description' => 'Registers Twig functions for MyExtra.',
    'file' => 'elements/plugins/MyExtraTwig.php',
    'events' => [
        ['event' => 'OnTwigInit', 'priority' => 0, 'propertyset' => 0],
    ],
]
```

### When to use which

| Approach | Best for |
|----------|----------|
| Initializer | Quick additions in a snippet, plugin, or bootstrap file |
| Extension class | Bundling several related functions together with clean class structure |
| OnTwigInit event | Package-level registration that activates automatically when your extra is installed |

All three approaches have the same end result -- the functions are available in every Twig template rendered after registration.

## The Shared Runtime

If your Twig functions need to call MODX features (render chunks, run snippets, generate URLs, read settings), use the shared `ModxRuntime` instead of reimplementing those operations:

```php
use Boffinate\Twig\Support\ModxRuntime;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class CatalogExtension extends AbstractExtension
{
    public function __construct(private ModxRuntime $runtime) {}

    public function getFunctions(): array
    {
        return [
            new TwigFunction('catalog_card', function (array $product) {
                return $this->runtime->chunk('CatalogCard', $product);
            }, ['is_safe' => ['html']]),

            new TwigFunction('catalog_url', function (int $resourceId) {
                return $this->runtime->link($resourceId, ['view' => 'product']);
            }),

            new TwigFunction('catalog_setting', function (string $key) {
                return $this->runtime->option('catalog.' . $key);
            }),
        ];
    }
}

$twig = $modx->services->get('twigparser');
$twig->registerExtension(new CatalogExtension($twig->getRuntime()));
```

### Runtime methods

| Method | Equivalent MODX call | Description |
|--------|---------------------|-------------|
| `chunk($name, $props)` | `$modx->getChunk()` | Render a MODX chunk with properties |
| `snippet($name, $props)` | `$modx->runSnippet()` | Run a MODX snippet |
| `placeholder($key, $default)` | `$modx->getPlaceholder()` | Read a placeholder |
| `option($key, $default)` | `$modx->getOption()` | Read a system setting |
| `lexicon($key, $params, $lang)` | `$modx->lexicon()` | Translate a lexicon key |
| `translate($key, $topic, $params, $lang)` | load topic + `$modx->lexicon()` | Load a lexicon topic and translate |
| `link($id, $params, $ctx, $scheme, $opts)` | `$modx->makeUrl()` | Generate a resource URL |
| `field($name, $default, $resource)` | `$resource->get()` / `->getTVValue()` | Read a resource field or TV |
| `getModx()` | -- | Get the raw `modX` instance |
| `getParser()` | -- | Get the Twig parser instance |

### Why use the runtime instead of $modx directly?

- The runtime methods handle edge cases (property encoding, parser iteration limits, field/TV fallback logic).
- If the internal implementation changes, the runtime API stays stable.
- Your extension stays decoupled from MODX internals.

You can still access `$modx` directly through `$runtime->getModx()` when you need something the runtime does not cover.

## Accessing the Twig Environment

If you need the raw `Twig\Environment` instance (for example, to check which extensions are loaded or to configure loader paths):

```php
$twig = $modx->services->get('twigparser');
$env = $twig->getEnvironment();

// Check if a function exists
$env->getFunction('myextra_items'); // returns TwigFunction or false

// Add a global at any time
$env->addGlobal('build_time', time());
```

## Integrating with a Custom Event

If your extra fires its own rendering event (like ContentBlocks fires `ContentBlocks_BeforeParse`), you can pass templates through Twig before your extra processes them:

```php
// Inside your extra's rendering pipeline
$template = $this->getFieldTemplate();
$placeholders = $this->getFieldData();

// Pass through Twig if available
if ($this->modx->services->has('twigparser')) {
    $twig = $this->modx->services->get('twigparser');
    $template = $twig->renderString($template, $placeholders);
}

// Continue with your extra's own rendering
$output = $this->processPlaceholders($template, $placeholders);
```

This is the pattern the ContentBlocks plugin uses. The key points:

- Call `renderString()` with the template and the data your extra would normally pass as placeholders.
- The data array becomes the Twig template's local variables.
- Do this before your extra processes its own placeholder syntax, so users can mix Twig and your extra's syntax.
- Check `services->has('twigparser')` first so your extra still works without the Twig extra installed.

### Firing a system event for other extras

If you want other extras to be able to hook into your rendering, fire a system event and let plugins handle the Twig integration:

```php
// In your extra's rendering code
$result = $this->modx->invokeEvent('MyExtraBeforeParse', [
    'tpl' => $template,
    'phs' => $placeholders,
]);

if (is_string($result) && $result !== '') {
    $template = $result;
}
```

Then a Twig plugin can listen to `MyExtraBeforeParse`:

```php
// Plugin listening to MyExtraBeforeParse
$twig = $this->modx->services->get('twigparser');
return $twig->renderString($tpl, $phs);
```

This is the approach ContentBlocks uses with `ContentBlocks_BeforeParse`. It keeps the Twig dependency optional -- sites without the Twig extra are unaffected.

## Providing Configurable Templates

A common pattern for extras is to let users configure templates through system settings or chunk names. With Twig available, you can offer Twig syntax in those templates:

```php
class MyExtra
{
    public function render(array $data): string
    {
        $tplSetting = $this->modx->getOption('myextra.item_tpl');

        // If the setting looks like a chunk name, use the chunk
        // If it contains Twig/HTML, render it directly
        if ($tplSetting && !str_contains($tplSetting, '{{') && !str_contains($tplSetting, '<')) {
            return $this->modx->getChunk($tplSetting, $data);
        }

        // Default template with Twig syntax
        $template = $tplSetting ?: '<div class="item"><h3>{{ title }}</h3></div>';

        if ($this->modx->services->has('twigparser')) {
            $twig = $this->modx->services->get('twigparser');
            return $twig->renderString($template, $data);
        }

        // Fallback: simple placeholder replacement
        $output = $template;
        foreach ($data as $key => $value) {
            $output = str_replace('[[+' . $key . ']]', (string) $value, $output);
        }
        return $output;
    }
}
```

## Passing Complex Data to Templates

`renderString()` accepts any value that Twig can handle. You can pass nested arrays, objects, and callables:

```php
$twig->renderString($template, [
    // Simple values
    'title' => 'Hello',
    'count' => 42,
    'published' => true,

    // Arrays (loopable in Twig)
    'items' => [
        ['name' => 'Alpha', 'price' => 10],
        ['name' => 'Beta', 'price' => 20],
    ],

    // Nested arrays (accessible via dot notation in Twig)
    'config' => [
        'show_images' => true,
        'columns' => 3,
    ],

    // Objects (Twig accesses public properties and getters)
    'resource' => $modx->resource,
]);
```

In the template:

```twig
{{ title }}
{% for item in items %}
    {{ item.name }}: {{ item.price }}
{% endfor %}
{% if config.show_images %}...{% endif %}
{{ resource.pagetitle }}
```

## Error Handling

`renderString()` throws `Twig\Error\SyntaxError` if the template has invalid Twig syntax. If your extra should handle this gracefully instead of crashing the page:

```php
use Twig\Error\SyntaxError;
use Twig\Error\RuntimeError;

try {
    $output = $twig->renderString($template, $data);
} catch (SyntaxError $e) {
    $this->modx->log(\modX::LOG_LEVEL_ERROR,
        'MyExtra: Twig syntax error in template: ' . $e->getMessage()
    );
    $output = '<!-- Template error: ' . htmlspecialchars($e->getMessage()) . ' -->';
} catch (RuntimeError $e) {
    $this->modx->log(\modX::LOG_LEVEL_ERROR,
        'MyExtra: Twig runtime error: ' . $e->getMessage()
    );
    $output = '';
}
```

## Cache Management

Compiled Twig templates are cached at `{core_cache_path}/twig/`. If your extra generates templates dynamically and you need to clear the cache:

```php
// Instance method
$twig = $modx->services->get('twigparser');
$twig->clearCompiledTemplates();

// Static method (no parser instance needed)
\Boffinate\Twig\Twig::clearCompiledTemplatesForModx($modx);
```

The TwigCacheClear plugin already clears the cache on `OnSiteRefresh` (MODX cache clear). You only need to call these methods if your extra needs to invalidate the cache at other times.

## Testing

The Twig extra includes a `ParserTestCase` base class that sets up a working MODX + Twig environment for integration tests. If your extra depends on the Twig parser, you can extend this class:

```php
use MODX\Revolution\Tests\Twig\ParserTestCase;

class MyExtraTest extends ParserTestCase
{
    protected function usesTwigParser(): bool
    {
        return true;
    }

    public function test_my_custom_function_works(): void
    {
        $parser = $this->modx->parser;
        $parser->registerInitializer(function ($twig) {
            $twig->addFunction(new \Twig\TwigFunction('greet', fn ($name) => "Hello $name"));
        });

        $this->assertSame('Hello World', $this->processContent('{{ greet("World") }}'));
    }

    public function test_my_extension_renders_chunk(): void
    {
        $this->registerChunk('TestChunk', '<p>{{ message }}</p>');

        $twig = $this->modx->services->get('twigparser');
        $output = $twig->renderString('{{ chunk("TestChunk", {"message": "Hi"}) }}', []);

        $this->assertSame('<p>Hi</p>', $output);
    }
}
```

### Available test helpers

The `ParserTestCase` provides:

| Method | Description |
|--------|-------------|
| `processContent($content)` | Run content through the full MODX + Twig parser pipeline |
| `renderTemplateContent($content, $props)` | Render content as if it were a MODX template |
| `renderResourceContent($content, $props)` | Render content as if it were resource content |
| `registerChunk($name, $content)` | Create an in-memory chunk (no database write) |
| `registerSnippet($name, $code)` | Create a snippet in the database (cleaned up after test) |
| `registerResource($fields)` | Create a resource in the database (cleaned up after test) |
| `registerTemplateVar($name, $fields)` | Create a TV in the database (cleaned up after test) |
| `assignTemplateVarValue($resource, $name, $value)` | Set a TV value on a resource |
| `executePluginFile($path, $variables)` | Execute a plugin file with injected variables |

All database fixtures are automatically cleaned up in `tearDown`.

### Running tests

Tests run against a live MODX installation through DDEV:

```bash
ddev exec ./core/vendor/bin/phpunit -c _build/test/phpunit.xml path/to/your/tests/
```

## API Summary

### Boffinate\Twig\Twig

| Method | Description |
|--------|-------------|
| `renderString(string $content, array $placeholders): string` | Render a Twig template string with variables |
| `getEnvironment(): Environment` | Get the raw Twig Environment |
| `getRuntime(): ModxRuntime` | Get the shared MODX runtime helper |
| `registerInitializer(callable $fn): void` | Register a function to run on environment setup |
| `registerExtension(ExtensionInterface $ext): void` | Register a Twig extension |
| `clearCompiledTemplates(): void` | Clear the compiled template cache |

### Boffinate\Twig\Support\ModxRuntime

| Method | Description |
|--------|-------------|
| `chunk(string $name, array $props = []): string` | Render a MODX chunk |
| `snippet(string $name, array $props = []): string` | Run a MODX snippet |
| `placeholder(string $key, $default = null): mixed` | Read a placeholder |
| `option(string $key, $default = null): mixed` | Read a system setting |
| `lexicon(string $key, array $params = [], string $lang = ''): ?string` | Translate a lexicon key |
| `translate(string $key, string $topic = '', array $params = [], string $lang = ''): ?string` | Load topic and translate |
| `link(int\|string $id, ...): string` | Generate a resource URL |
| `field(mixed $name, $default = null, $resource = null): mixed` | Read a resource field or TV |
| `getModx(): modX` | Get the MODX instance |
| `getParser(): Twig` | Get the Twig parser instance |
