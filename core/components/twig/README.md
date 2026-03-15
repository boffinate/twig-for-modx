# Twig for MODX

## What This Extra Does

This extra lets you use Twig syntax inside normal MODX content flows.

It does not replace MODX rendering with a separate file-based Twig application. Instead, it adds a Twig pass inside the existing MODX parser cycle, so you can mix Twig with normal MODX tags in:

- templates
- resources
- chunks
- snippet output
- supported plugin integrations such as ContentBlocks

That means all of these can live together in the same markup:

```html
<h1>{{ page_title|upper }}</h1>
<p>[[*longtitle]]</p>
{{ chunk('HeroCta', {'label': 'Buy now'}) }}
```

## How It Works

Twig is evaluated before the final MODX tag pass. After Twig renders, MODX still processes tags such as:

- `[[*pagetitle]]`
- `[[+placeholder]]`
- `[[Snippet]]`
- `[[$Chunk]]`

This is why the extra works well for mixed MODX and Twig templates.

## When To Use It

Use this extra when:

- you want Twig syntax in normal MODX templates or chunks
- you want to keep using MODX chunks, snippets, placeholders, and resources
- you are building a MODX extra and want to expose Twig helpers cleanly
- you want Twig logic without rewriting your site around file-based Twig templates

Do not use it when:

- you want Twig to be the primary application templating system
- you need native Twig template loaders, file trees, or heavy `{% extends %}` / `{% include %}` workflows across many files
- you want to hide MODX completely and treat Twig as a separate frontend layer

If your goal is "Twig-first, file-first, loader-first", a loader-based approach is a better fit than this addon.

## Setup

Add a MODX namespace so MODX can load the addon bootstrap:

- Name: `twig`
- Path: `{core_path}components/twig/`

Once the addon is installed and bootstrapped, the parser service is available as `twigparser`.

## Basic Usage

Twig works in normal template or chunk content:

```html
<section class="hero">
    <h1>{{ title|upper }}</h1>
    <p>[[*introtext]]</p>
</section>
```

Twig also works in content returned by snippets:

```php
return '<div class="card">{{ product_name }}</div>';
```

And that can still be processed through the addon parser.

## Built-in Twig Helpers

The addon ships with MODX-aware helpers:

- `chunk(name, properties = {})`
- `snippet(name, properties = {})`
- `placeholder(name, default = null)`
- `ph(name, default = null)` as a compatibility alias
- `option(key, default = null)`
- `config(key, default = null)` as a compatibility alias
- `lexicon(key, params = {}, language = '')`
- `trans(key, topic = '', params = {}, language = '')`
- `link(id, params = '', context = '', scheme = -1, options = {})`
- `field(name, default = null, resource = null)`

Examples:

```twig
{{ chunk('HeroCta', {'label': 'Buy now'}) }}
{{ snippet('SiteNav', {'depth': 2}) }}
{{ placeholder('hero_title', 'Default title') }}
{{ option('site_name') }}
{{ trans('setting_site_name', 'en:setting') }}
{{ link(12) }}
{{ field('pagetitle') }}
{{ field('HeroImage', '/fallback.jpg') }}
```

## When To Use Each Helper

Use `chunk()` when:

- you want to render a MODX chunk from Twig
- you want the chunk to keep behaving like a MODX chunk

Do not use `chunk()` when:

- you are trying to build a large native Twig include tree

Use `snippet()` when:

- you need existing MODX snippet output inside Twig
- you are wrapping legacy MODX functionality in a Twig-friendly template

Do not use `snippet()` when:

- you are inside a large loop and the snippet is expensive
- the same logic belongs in PHP or a custom Twig extension instead

Use `field()` when:

- you want a simple "resource field or TV" lookup
- you want a fallback value
- you want compatibility with older Twig-for-MODX patterns

Do not use `field()` when:

- you only need a normal resource field and `modx.resource.pagetitle` is already clearer

Use `trans()` when:

- you need to load a lexicon topic and translate in one call

Use `lexicon()` when:

- the topic is already loaded

## Globals

The addon exposes these globals:

- `modx`
- `placeholders`
- `modx_runtime`

Examples:

```twig
Current resource id: {{ modx.resource.id }}
Current placeholder: {{ placeholders.hero_title|default('None') }}
Current site URL: {{ modx_runtime.option('site_url') }}
```

Use `modx` sparingly. It is powerful, but it also couples templates tightly to raw MODX internals. In most templates, the helper functions are a better default.

## Writing Mixed MODX + Twig Templates

This extra is strongest when you use Twig for presentation logic and MODX for content or data lookup.

Good pattern:

```twig
{% set cards = ['One', 'Two', 'Three'] %}
<ul>
{% for card in cards %}
    <li>{{ card }}</li>
{% endfor %}
</ul>
[[!RecentArticles]]
```

Less good pattern:

```twig
{% for i in 1..100 %}
    {{ snippet('HeavySnippet', {'i': i}) }}
{% endfor %}
```

If you find yourself calling many expensive snippets from Twig, move that logic into PHP or a custom Twig extension and pass a prepared dataset into the template.

## Custom Twig Features

You can register custom Twig functions, filters, tests, globals, or full extensions.

Get the parser service:

```php
/** @var \Boffinate\Twig\Twig $twigParser */
$twigParser = $modx->services->get('twigparser');
```

Register a one-off initializer:

```php
$twigParser->registerInitializer(function (\Twig\Environment $twig) {
    $twig->addFunction(new \Twig\TwigFunction('double_value', fn ($value) => $value * 2));
});
```

Register a full Twig extension:

```php
$twigParser->registerExtension(new MyTwigExtension());
```

## Shared Runtime For Twig-native Extras

If you are building a MODX extra that adds its own Twig extension, use the shared runtime helper instead of copying parser internals.

The runtime is available from PHP:

```php
$runtime = $twigParser->getRuntime();
```

It gives your extension a clean way to:

- render chunks
- run snippets
- read placeholders or options
- translate lexicon strings
- build MODX URLs
- read resource fields and TVs

Example:

```php
use Boffinate\Twig\Support\ModxRuntime;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class CardsExtension extends AbstractExtension
{
    public function __construct(private ModxRuntime $runtime)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('card_chunk', fn (string $title) => $this->runtime->chunk('CardTpl', ['title' => $title])),
            new TwigFunction('card_data', fn (string $name) => $this->runtime->snippet('CardData', ['name' => $name])),
            new TwigFunction('card_url', fn (int $id) => $this->runtime->link($id)),
        ];
    }
}

$twigParser->registerExtension(new CardsExtension($twigParser->getRuntime()));
```

Use the runtime when:

- you are writing a reusable Twig extension for MODX
- you want to keep MODX-specific behavior in one place
- you do not want each extension to reimplement tag building and parser calls

Do not use the runtime when:

- plain Twig helpers in the template are already enough
- the feature belongs in normal PHP service code rather than the template layer

## OnTwigInit Event

If your package installs a MODX `OnTwigInit` system event, plugin code receives:

- `$twig` as the `\Twig\Environment`
- `$parser` as the addon parser instance
- `$modx` as the active MODX instance

Example:

```php
$twig->addFunction(new \Twig\TwigFunction('build_id', fn () => 'dev'));
$twig->addGlobal('release', '2026.03');
return '';
```

Use this when you want package-level customization at environment startup.

## Caching And Cache Clearing

Compiled Twig templates are cached under the MODX cache path. When MODX cache is cleared, the addon also clears its compiled Twig cache so changed chunks, templates, and resources do not keep serving stale compiled output.

If you change template logic and do not see the update:

1. Clear MODX cache.
2. Make sure the addon's cache-clear plugin is installed and enabled.

## ContentBlocks

The addon includes a ContentBlocks integration plugin so ContentBlocks template output can be passed through Twig.

Use this when:

- your ContentBlocks field markup needs Twig expressions or filters

Do not use it when:

- plain ContentBlocks placeholders already solve the problem more simply

## Practical Guidance

Good default rules:

- use Twig for presentation logic
- use MODX for content, resources, chunks, and snippets
- prefer helper functions over raw `modx` access
- prefer custom Twig extensions over repeated expensive snippet calls
- use `field()` mainly for TVs or fallback lookups

If you stay within those boundaries, this addon works well and remains readable for MODX developers.
