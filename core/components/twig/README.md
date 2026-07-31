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

- you want to hide MODX completely and treat Twig as a separate frontend layer

File-based templates are supported alongside inline Twig: register template
directories (optionally namespaced) and use `{% include %}`, `{% embed %}`,
and `{% extends %}` as in any Twig project. See "Template Directories" below.

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

## Template Directories

Two ways to make `.twig` files loadable:

1. The `twig.template_paths` system setting — a JSON object mapping loader
   namespaces to directories (relative paths resolve against `MODX_BASE_PATH`):

   ```json
   {"components": "core/components/mysite/templates/components", "main": "core/components/mysite/templates"}
   ```

2. Programmatically, e.g. from a plugin or your own bootstrap:

   ```php
   $twig = $modx->services->get('twigparser');
   $twig->registerTemplatePath('/path/to/components', 'components');
   ```

Then, anywhere Twig renders:

```twig
{% include '@components/button/button.twig' with { label: 'Book now' } only %}
```

The `main` namespace serves bare paths like `{% extends 'layout.twig' %}`.

## Components (Symfony UX TwigComponent)

The addon wires Symfony UX TwigComponent's anonymous components onto the
environment (no Symfony framework involved). Drop a template into a
`components/` directory under any registered template path:

```twig
{# components/Button.html.twig #}
{% props label, variant = 'primary' %}
<a{{ attributes.defaults({class: 'a-btn a-btn--' ~ variant}) }}>{{ label }}</a>
```

and use it anywhere Twig renders — templates, chunks, ContentBlocks templates:

```twig
<twig:Button label="Book now" variant="secondary" data-tracking="cta" />
<twig:Button :label="'Book ' ~ what" />
{{ component('Button', { label: 'Go' }) }}
```

`{% props %}` enforces required props, `attributes` carries extra HTML
attributes (with class merging via `attributes.defaults`), and component
content becomes the `content` block for slot-style composition. Set
`twig.components` to `0` to disable, or `twig.components_dir` to rename the
`components/` directory prefix.

Standalone environments get the same behaviour with:

```php
\Boffinate\Twig\Component\UxComponentSupport::register($twigEnvironment);
```

## Error Handling

When a render fails, the error is logged to the MODX error log. What the
visitor sees depends on `twig.debug`:

- debug on (default): the original source is returned unrendered, so you can
  see what failed in place.
- debug off (production): element renders (chunks, ContentBlocks templates)
  return an empty string; the document-level pass returns the content with
  Twig delimiters made inert (`&#123;`) so the page still renders but nothing
  executes and no template logic leaks to visitors.

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
- `resource_url(value)` resolves positive integer IDs and digit-only string IDs through MODX. Digit-only strings retain the legacy ContentBlocks behavior, including `"0"`; typed integers must be positive. Other strings pass through unchanged; null, arrays, and non-stringable objects become `''`; floats, booleans, and `Stringable` objects are stringified without being reinterpreted as IDs
- `field(name, default = null, resource = null)`

Examples:

```twig
{{ chunk('HeroCta', {'label': 'Buy now'}) }}
{{ snippet('SiteNav', {'depth': 2}) }}
{{ placeholder('hero_title', 'Default title') }}
{{ option('site_name') }}
{{ trans('setting_site_name', 'en:setting') }}
{{ link(12) }}
{{ resource_url(content_blocks_link_value) }}
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

Examples:

```twig
Current resource id: {{ modx.resource.id }}
Current placeholder: {{ placeholders.hero_title|default('None') }}
Current site URL: {{ option('site_url') }}
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

## pdoTools Snippets (pdoMenu, pdoResources, ...)

pdoTools-based snippets fetch their tpl chunks through pdoTools' own
`getChunk()` machinery, which bypasses the MODX parser's `getElement()` —
and with it the proxy that normally renders Twig in chunks. To close that
gap, the addon ships Twig-aware subclasses of pdoTools' service classes.
Point pdoTools at them via two system settings:

| Setting | Value |
|---------|-------|
| `pdotools_pdotools_class` | `Boffinate\Twig\PdoTools\CoreToolsTwig` |
| `pdotools_fetch_class` | `Boffinate\Twig\PdoTools\FetchTwig` |

When pdoTools is present, the package installer offers to save these settings for you. The option is checked by default unless either setting already has a custom value; in that case it is left unchecked so installation does not silently replace the site's configuration.

With these registered, Twig renders in tpl chunks (including `@INLINE`
bindings and `&fastMode`) with the row placeholders pdoTools resolved for
the chunk, so `{{ pagetitle }}` works in a pdoResources tpl. The classes
are inert when the `twigparser` service is not registered, so plain
pdoTools behaviour is unchanged if the addon is disabled.

The bootstrap sets both options itself (guarded by `class_exists()`) right
before it resolves the `twigparser` service, so the shared `pdotools`
service gets the Twig-aware class even on a site that has not configured
anything.

**Keep them as real system settings anyway.** This addon's namespace
bootstrap resolves the shared `pdotools` service while installing itself as
the parser, so the class is chosen during `_initNamespaces()` — before
`OnMODXInit`, and before `_initContext()` rebuilds `$modx->config` and drops
anything the bootstrap set. Services resolved after that point — `pdofetch`
is the usual one — read the stored setting, not the bootstrap's value. If you apply system settings from a plugin on that event
(ClientConfig and similar settings-file extras do exactly this), you get a
half-configured state that is easy to miss: `pdofetch` is resolved lazily
and picks up your value, `pdotools` was resolved early and does not.
Database settings are read into `$modx->config` before any namespace
bootstrap runs, so they always win. If you must drive these from a file,
apply them from a namespace bootstrap that sorts before `pdotools` — and
guard with `class_exists()`, because pdoTools does `new $class(...)`
unguarded and an unloadable class is a fatal on every request.

Note on `@INLINE` chunks: pdoTools historically converts `{{ ... }}` to
`[[ ... ]]` inside inline chunk bodies. The subclasses render valid Twig in
the inline body first; content that is not valid Twig (i.e. the legacy
MODX-tag shorthand) is left for pdoTools to convert as before.

## What Twig Compiles (and what it must not)

Twig runs before the MODX parser, so anything handed to it becomes template
source with the `modx` object in scope. The rule this addon follows is
therefore about **provenance**: Twig compiles *elements* — things an author
with element access wrote — and the values those elements interpolate into
themselves. It does not compile the document.

| Source | Compiled? | Mechanism |
|---|---|---|
| Templates | yes | `modTemplateTwig` proxy on the Template relation |
| Chunks (`[[$chunk]]`, `$modx->getChunk()`) | yes | `modChunkTwig` proxy via `getElement()` |
| ContentBlocks field templates | yes | `ContentBlocks_BeforeParse` hook |
| pdoTools tpl chunks, `@INLINE`, fast mode | yes | the subclasses above |
| String templates, standalone environments | yes | `renderString()` / your own `Environment` |
| Resource content, TV values | **no** | — |
| Snippet output | **no** | put Twig in the tpl chunks a snippet renders |
| The assembled document | **no** | unless you enable the document pass |

`tests/SecurityBoundaryTest.php` is the executable version of this table.

### Templates render before the resource is merged

The Template proxy renders Twig in `getContent()` — after properties
resolve, before `modTemplate::process()` runs the tag pass. That is the last
moment the string is purely template source; one step later `[[*content]]`
has merged the resource body into it. So template Twig is evaluated **before**
MODX tags, the opposite of chunks, and deliberately so.

Template Twig is part of the *cacheable* pass: it is evaluated when the
resource cache is generated, not on every request. Emit `[[!snippet]]` tags
from your Twig for anything that must vary per request — they pass through
untouched and are processed later, exactly like MODX tags written by hand.
This differs from pre-1.0 behaviour, where template Twig re-ran per request
as a side effect of the document pass.

### The chunk seam

`modChunkTwig` renders a chunk's *output*, after its MODX tags are
substituted. That ordering is the integration seam between the two
templating systems — it is what makes `{{ '[[+date]]'|date('Y') }}` and
feeding pdoTools or ContentBlocks placeholder output into Twig filters work.

The consequence is that **a value interpolated into a chunk becomes part of
that chunk's Twig source**. For editor-supplied values that is an accepted
trade: the chunk's author chose what it pulls in. For **request-derived**
values it is not safe and no setting makes it safe — do not route query
strings, form input or other visitor data through a chunk that Twig then
renders. Snippet output is never compiled, so that is the safe channel.

## The Document Pass (`twig.document_pass`, default off)

The document pass Twig-renders the whole assembled uncacheable document.
It is **off by default**, and should stay off.

It has no provenance at all. By the time it runs, template output, snippet
output, editor content and anything echoed back from the request are one
string. The last of those is the sharp end: a search page that prints "no
results for X" will compile X, so with this pass on a query string like
`?q={{ modx.config.site_name }}` is server-side template injection reaching
the full MODX API — **unauthenticated**, no editor trust involved. It is
also a footgun with no attacker at all: a literal `{{` in a code sample or
pasted text breaks the page. A Twig sandbox is not a fix, because to disarm
`{{ modx.runSnippet(…) }}` in a blog post it would also have to disarm the
element-generated Twig the pass exists to serve.

### When you still need it

Turn it on with `twig.document_pass` = `1` only for chunk-fetching paths
element-level rendering does not reach: Alpacka-based extras that
`getObject(modChunk…)` with their own cache, extras that build inline chunk
objects (Tagger), and snippets that emit Twig in their own output rather
than in a tpl chunk.

If you do, audit every place request data is echoed into a page first.
Prefer moving the Twig into a chunk instead.

## Practical Guidance

Good default rules:

- use Twig for presentation logic
- use MODX for content, resources, chunks, and snippets
- prefer helper functions over raw `modx` access
- prefer custom Twig extensions over repeated expensive snippet calls
- use `field()` mainly for TVs or fallback lookups

If you stay within those boundaries, this addon works well and remains readable for MODX developers.
