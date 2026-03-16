# How It Works

This page explains how the Twig extra integrates with the MODX parser system and how it coexists with other extras like pdoTools and ContentBlocks.

## Architecture overview

The Twig extra installs itself as a **decorator** around the existing MODX parser. During bootstrap, it saves a reference to whatever parser is currently active (core `modParser`, or pdoTools' `Parser`), then sets itself as `$modx->parser`. All calls to `processElementTags()` flow through Twig first, then delegate to the original parser.

```
$modx->parser          → Twig (decorator) → original parser (modParser or pdoTools)
$modx->services        → 'twigparser' service (same Twig instance)
```

### What Twig does

When `processElementTags()` is called on content outside the manager context, Twig:

1. Checks if the content contains Twig syntax (`{{ }}`, `{% %}`, `{# #}`)
2. If it does, renders Twig syntax via `renderString()`
3. Delegates to the original parser for MODX tags (`[[...]]`) and Fenom (if pdoTools is active)

This means Twig and MODX tags coexist in the same content. Twig renders first, then MODX and Fenom process the remaining tags.

To avoid re-rendering content that has already been assembled (e.g. ContentBlocks output containing dump results), the Twig pass is skipped when content exceeds 5 MB. Invalid Twig syntax is caught gracefully and the content is passed through unchanged.

### How Twig is invoked

Twig renders automatically during page rendering via the parser decorator. It is also invoked explicitly at specific integration points:

- **Parser decorator**: All content processed by `$modx->parser->processElementTags()` goes through a Twig rendering pass. This covers templates, resource content, chunk output, and snippet output.

- **ContentBlocks plugin**: The `TwigContentBlocks` plugin listens to the `ContentBlocks_BeforeParse` event. When ContentBlocks renders a field, the plugin calls `renderString()` on the template, passing the field's placeholders as Twig variables.

- **Direct API use**: Any extra can retrieve the service and render templates:

  ```php
  $twig = $modx->services->get('twigparser');
  $output = $twig->renderString('Hello {{ name }}', ['name' => 'World']);
  ```

- **Custom extensions**: Plugins listening to the `OnTwigInit` event can register custom Twig functions, filters, and globals.

### Chunk rendering

When the Twig parser processes a chunk (via `getElement()`), it wraps the chunk in a `modChunkTwig` proxy. This proxy renders the chunk's content through Twig after normal MODX processing, so chunks can contain Twig syntax alongside standard MODX placeholders.

## Relationship with pdoTools

The Twig extra does **not** depend on pdoTools. It works whether pdoTools is installed or not.

When pdoTools is installed:

- pdoTools registers its own parser as `$modx->parser`, adding Fenom template support and FastField tags (`[[#123.pagetitle]]`).
- Twig wraps the pdoTools parser via the decorator pattern. Twig renders `{{ }}` and `{% %}` syntax first, then delegates to pdoTools for MODX tags and Fenom processing.
- pdoTools' other services (`pdoFetch`, `CoreTools`, `getChunk()`) continue to work normally.

When pdoTools is not installed:

- Twig wraps the core `modParser` instead.
- Everything works the same way, just without Fenom.

### Processing order

Understanding the order prevents surprises:

1. **Twig runs first.** All `{{ }}`, `{% %}`, and `{# #}` blocks are evaluated and replaced with their output.
2. **MODX runs second.** The result from step 1 is processed by the MODX parser, which handles `[[tags]]`.
3. **Fenom runs last** (only if pdoTools is installed and the `pdotools_fenom_parser` setting is enabled). Any `{$var}` or other Fenom syntax in the output is processed after both Twig and MODX tags have been resolved.

This means Twig syntax is always resolved before Fenom sees the content, so `{{` and `{%` do not conflict with Fenom's `{` parser.

## Relationship with ContentBlocks

ContentBlocks is an optional integration. The `TwigContentBlocks` plugin only runs when ContentBlocks fires its `ContentBlocks_BeforeParse` event. If ContentBlocks is not installed, the plugin is never triggered.

When active, the plugin passes the ContentBlocks template and all field placeholders to the Twig renderer. This allows field templates to use Twig syntax:

```twig
{% if url %}
  <a href="{{ url }}">{{ title }}</a>
{% else %}
  <span>{{ title }}</span>
{% endif %}
```

## Bootstrap and service registration

The Twig bootstrap (`bootstrap.php`) does three things:

1. Registers `Boffinate\Twig\Twig::class` as a factory that creates the parser instance
2. Registers `'twigparser'` as a service alias that resolves to the same instance
3. Calls `decorateParser()` to install Twig as `$modx->parser`, wrapping whatever parser was previously active

The factory supports class overrides via the `modxTwig.class` system setting, following the same pattern used by other MODX extras.

## Cache management

Twig compiles templates to PHP files for performance. The compiled templates are stored in the MODX cache directory under `cache/twig/`. The `TwigCacheClear` plugin listens to the `OnSiteRefresh` event and clears compiled templates when the site cache is refreshed.

You can also clear compiled templates programmatically:

```php
$twig = $modx->services->get('twigparser');
$twig->clearCompiledTemplates();
```
