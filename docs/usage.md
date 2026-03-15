# Using the Twig Extra

## What It Does

The Twig Extra adds Twig template syntax to your normal MODX workflow. It does not replace MODX rendering. Instead it runs a Twig pass inside the existing MODX parser cycle, so you can mix Twig expressions with standard MODX tags in templates, chunks, resources, and snippet output.

```html
<h1>{{ field("pagetitle")|upper }}</h1>
<p>[[*introtext]]</p>
{{ chunk('HeroCta', {'label': 'Buy now'}) }}
```

Twig is evaluated before the final MODX tag pass. After Twig renders, MODX still processes its own tags (`[[*pagetitle]]`, `[[+placeholder]]`, `[[Snippet]]`, `[[$Chunk]]`).

## Installation

1. Install the Twig transport package through the MODX package manager.
3. The installer creates a `twig` namespace pointing to `{core_path}components/twig/`.
4. The `twigparser` service is now available site-wide.

No other configuration is needed. The extra activates automatically.

This extra uses **Twig 3** (the [Twig 3.x documentation](https://twig.symfony.com/doc/3.x/) is the right reference for syntax, filters, and functions).

## Where Twig Syntax Works

Twig syntax is processed in:

- **Templates** - your MODX page templates
- **Chunks** - both when called via `[[$ChunkName]]` and via the `chunk()` Twig function
- **Resource content** - the content field of any resource
- **Snippet output** - if a snippet returns a string containing Twig syntax, it gets rendered
- **ContentBlocks fields** - when the ContentBlocks plugin is enabled (see the [ContentBlocks guide](./contentblocks.md))

Twig is not processed in the MODX manager interface.

## Built-in Functions

The extra provides functions that bridge Twig templates to MODX features.

### chunk(name, properties)

Renders a MODX chunk. Properties are passed as chunk placeholders.

```twig
{{ chunk('CardTpl', {'title': 'My Card', 'image': '/img/card.jpg'}) }}
```

The chunk itself can also contain Twig syntax.

### snippet(name, properties)

Runs a MODX snippet and outputs the result.

```twig
{{ snippet('SiteNav', {'depth': 2, 'startId': 0}) }}
```

### placeholder(key, default) / ph(key, default)

Reads a MODX placeholder. Returns the default if the placeholder is not set.

```twig
{{ placeholder('page_header', 'Welcome') }}
{{ ph('page_header') }}
```

### option(key, default) / config(key, default)

Reads a MODX system setting.

```twig
{{ option('site_name') }}
{{ config('site_url') }}
```

### lexicon(key, params, language)

Returns a lexicon string. The lexicon topic must already be loaded.

```twig
{{ lexicon('setting_site_name') }}
```

### trans(key, topic, params, language)

Loads a lexicon topic and translates in one call.

```twig
{{ trans('setting_site_name', 'en:setting') }}
```

### link(id, params, context, scheme, options)

Generates a URL for a MODX resource.

```twig
<a href="{{ link(12) }}">About Us</a>
<a href="{{ link(5, {'sort': 'date'}) }}">Blog</a>
```

### field(name, default, resource)

Reads a resource field or Template Variable from the current resource. Falls back to the default if the field is empty.

```twig
{{ field('pagetitle') }}
{{ field('HeroImage', '/images/fallback.jpg') }}
```

You can also pass a hash for named parameters:

```twig
{{ field({'name': 'CustomTV', 'default': 'none', 'resource': 42}) }}
```

## Global Variables

Three globals are available in every Twig template:

### modx

The MODX instance. Use it to access resource fields directly.

```twig
{{ modx.resource.id }}
{{ modx.resource.pagetitle }}
{{ modx.resource.parent }}
```

Use `modx` sparingly. The helper functions are usually clearer.

### placeholders

An array of all currently set MODX placeholders.

```twig
{{ placeholders.hero_title|default('No title') }}
```

### modx_runtime

The shared runtime helper. Gives access to all the built-in functions as methods. Useful in edge cases but rarely needed in templates since the standalone functions are more readable.

```twig
{{ modx_runtime.option('site_url') }}
```

## Twig Filters

All standard Twig filters work. Some commonly useful ones:

```twig
{{ title|upper }}
{{ title|lower }}
{{ title|capitalize }}
{{ description|default('No description available') }}
{{ content|raw }}
{{ price|number_format(2, '.', ',') }}
{{ items|length }}
{{ html_content|striptags }}
{{ name|trim }}
{{ list|join(', ') }}
{{ date_string|date('d/m/Y') }}
```

## HTML Escaping

Twig auto-escapes all output by default. This is a security feature that prevents XSS attacks, but it means HTML content comes out as visible tags if you are not expecting it.

```twig
{# Variable contains: <p>Hello <strong>world</strong></p> #}

{{ value }}       {# outputs: &lt;p&gt;Hello &lt;strong&gt;world&lt;/strong&gt;&lt;/p&gt; #}
{{ value|raw }}   {# outputs: <p>Hello <strong>world</strong></p> #}
```

Use the `|raw` filter when you know the content is safe HTML that should be rendered as markup. This is common with:

- richtext field content from ContentBlocks
- chunk output that contains HTML
- snippet output that returns HTML
- MODX resource fields like `content` or `introtext`

```twig
{{ field('content')|raw }}
{{ chunk('HeroBanner', {'title': 'Welcome'})|raw }}
```

Do not use `|raw` on user-supplied input that has not been sanitised.

When in doubt, leave auto-escaping on. Only add `|raw` when you see escaped HTML appearing as text on the page.

## Twig Control Structures

### Conditionals

```twig
{% if field('HeroImage') %}
    <img src="{{ field('HeroImage') }}" alt="{{ field('pagetitle') }}">
{% else %}
    <div class="placeholder">No image</div>
{% endif %}
```

### Loops

```twig
{% set items = ['Home', 'About', 'Contact'] %}
<nav>
{% for item in items %}
    <a href="#">{{ item }}</a>
{% endfor %}
</nav>
```

### Setting Variables

```twig
{% set site = option('site_name') %}
{% set year = 'now'|date('Y') %}
<footer>&copy; {{ year }} {{ site }}</footer>
```

## Mixing MODX Tags and Twig

Twig and MODX tags work together in the same template. Twig runs first, then MODX processes its tags in the output.

```twig
{# Twig handles the logic #}
{% if modx.resource.parent == 5 %}
    <nav>[[!SiteNav? &startId=`5`]]</nav>
{% endif %}

{# MODX handles the content #}
<h1>[[*pagetitle]]</h1>
<div>[[*content]]</div>
```

You can also call MODX elements through the Twig functions instead of tags:

```twig
{% if modx.resource.parent == 5 %}
    <nav>{{ snippet('SiteNav', {'startId': 5}) }}</nav>
{% endif %}

<h1>{{ field('pagetitle') }}</h1>
```

Both approaches work. Choose whichever is clearer for your template.

### Processing order

Understanding the order of operations prevents surprises:

1. **Twig runs first.** All `{{ }}`, `{% %}`, and `{# #}` blocks are evaluated and replaced with their output.
2. **MODX runs second.** The result from step 1 is then processed by the MODX parser, which handles `[[tags]]`.

This means:

- **Twig can wrap MODX tags in conditionals.** The MODX tag is only present in the output if the condition is true, so MODX only processes it when needed.
- **Twig cannot read MODX tag output.** By the time MODX processes `[[*pagetitle]]`, Twig has already finished. You cannot use `{% if [[*pagetitle]] == 'Home' %}` -- use `{% if field('pagetitle') == 'Home' %}` instead.
- **MODX snippet output containing Twig is rendered.** If a snippet returns a string with `{{ }}` syntax, it goes through the Twig pass. This is intentional and useful for snippets that return Twig-powered markup.
- **Twig variables cannot be interpolated into MODX tags.** `[[*{{ fieldname }}]]` does not work because Twig processes `{{ fieldname }}` first, but the result is just text that gets concatenated into the MODX tag string. Use the `field()` helper instead.

### JavaScript frameworks and verbatim

Vue, Angular, Alpine.js, Handlebars, and other frontend frameworks also use `{{ }}` syntax. Twig will try to parse those expressions and throw an error.

Wrap frontend template blocks in `{% verbatim %}` to tell Twig to leave them alone:

```twig
{% verbatim %}
<div id="app">
    <p>{{ message }}</p>
    <span v-if="show">{{ count }} items</span>
</div>
{% endverbatim %}
```

Everything inside `{% verbatim %}...{% endverbatim %}` is output as-is without Twig processing.

## Custom Twig Functions

### Initializers

Register a function directly on the Twig environment:

```php
$twigParser = $modx->services->get('twigparser');
$twigParser->registerInitializer(function (\Twig\Environment $twig) {
    $twig->addFunction(new \Twig\TwigFunction('price', fn ($cents) => number_format($cents / 100, 2)));
});
```

Then in your template:

```twig
{{ price(1999) }} {# outputs 19.99 #}
```

### Extensions

Register a full Twig extension class:

```php
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class PriceExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('price', fn ($cents) => number_format($cents / 100, 2)),
            new TwigFunction('vat', fn ($cents, $rate = 0.2) => number_format($cents * $rate / 100, 2)),
        ];
    }
}

$twigParser = $modx->services->get('twigparser');
$twigParser->registerExtension(new PriceExtension());
```

### Shared Runtime

If your extension needs to call MODX features (chunks, snippets, URLs), use the shared runtime instead of reimplementing MODX calls:

```php
use Boffinate\Twig\Support\ModxRuntime;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class CardsExtension extends AbstractExtension
{
    public function __construct(private ModxRuntime $runtime) {}

    public function getFunctions(): array
    {
        return [
            new TwigFunction('card', fn (string $title) =>
                $this->runtime->chunk('CardTpl', ['title' => $title])
            ),
            new TwigFunction('card_url', fn (int $id) =>
                $this->runtime->link($id)
            ),
        ];
    }
}

$twigParser = $modx->services->get('twigparser');
$twigParser->registerExtension(new CardsExtension($twigParser->getRuntime()));
```

The runtime provides: `chunk()`, `snippet()`, `placeholder()`, `option()`, `lexicon()`, `translate()`, `link()`, `field()`.

### OnTwigInit Event

MODX plugins can listen to the `OnTwigInit` system event to register functions or globals when the Twig environment starts up:

```php
// Plugin code listening to OnTwigInit
$twig->addFunction(new \Twig\TwigFunction('build_id', fn () => 'v2.1'));
$twig->addGlobal('release', '2026.03');
return '';
```

The event receives `$twig` (the Twig Environment), `$parser` (the Twig parser instance), and `$modx`.

## Caching

Compiled Twig templates are cached under `{core_cache_path}/twig/`. When you clear the MODX cache, the Twig cache is also cleared automatically by the TwigCacheClear plugin.

If template changes do not appear after editing, clear the MODX cache. Make sure the TwigCacheClear plugin is enabled.

## Debugging with dump()

The Twig debug extension is enabled by default. The `dump()` function outputs a `var_dump` of any variable, or of the entire template context when called with no arguments.

### Dump a single variable

```twig
{{ dump(placeholders) }}
{{ dump(modx.resource) }}
```

### Dump everything available in the template

```twig
{{ dump() }}
```

With no arguments, `dump()` shows every variable in the current template context. This is the quickest way to find out what data you have to work with.

**Caution in normal templates:** In a standard MODX template or chunk, the context includes the `modx` global (the full MODX instance) and `modx_runtime`. Dumping these produces a very large output that can hang the page or exhaust memory. In normal templates, dump specific variables instead:

```twig
{{ dump(placeholders) }}
{{ dump(field('pagetitle')) }}
```

**In ContentBlocks templates** this is not a problem. The ContentBlocks plugin calls `renderString()` directly with just the field placeholders, so `dump()` only shows the ContentBlocks data. See the [ContentBlocks guide](./contentblocks.md#inspecting-available-variables) for details and example output.

### Block form

The block form writes output to the Symfony dump collector if available, but in a MODX context it works the same as the function form:

```twig
{% dump value %}
{% dump row_data %}
```

### Remove dump() before going live

`dump()` only works when debug mode is enabled (it is by default in this extra). Remove all `dump()` calls before deploying to production.
