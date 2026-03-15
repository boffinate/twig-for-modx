# How It Works

This page explains how the Twig extra integrates with the MODX parser system and how it coexists with other extras like pdoTools and ContentBlocks.

## Architecture overview

The Twig extra does **not** replace the MODX parser. It registers a separate service called `twigparser` that other code can use to render Twig templates. The global `$modx->parser` stays as whatever was configured (core `modParser`, or pdoTools' `Parser` if pdoTools is installed).

```
$modx->parser          → modParser (or pdoTools Parser)   — handles MODX tags
$modx->services        → 'twigparser' service             — handles Twig rendering
```

### What Twig does

The `Twig` class extends `modParser` and adds a Twig rendering pass inside `processElementTags()`. When processing uncacheable content outside the manager context, it:

1. Initialises the Twig environment (lazy, once per request)
2. Renders Twig syntax (`{{ ... }}`, `{% ... %}`) via `renderString()`
3. Calls `parent::processElementTags()` so MODX tags (`[[...]]`) are still processed

This means Twig and MODX tags coexist in the same content. Twig renders first, then MODX processes the remaining tags.

### How Twig is invoked

Twig is not called automatically during page rendering. It is invoked explicitly:

- **ContentBlocks plugin**: The `TwigContentBlocks` plugin listens to the `ContentBlocks_BeforeParse` event. When ContentBlocks renders a field, the plugin retrieves the `twigparser` service and calls `renderString()` on the template, passing the field's placeholders as Twig variables.

- **Direct API use**: Any extra can retrieve the service and render templates:

  ```php
  $twig = $modx->services->get('twigparser');
  $output = $twig->renderString('Hello {{ name }}', ['name' => 'World']);
  ```

- **Custom extensions**: Plugins listening to the `OnTwigInit` event can register custom Twig functions, filters, and globals.

### Chunk rendering

When the Twig parser processes a chunk (via `getElement()`), it wraps the chunk in a `modChunkTwig` proxy. This proxy renders the chunk's content through Twig before returning output, so chunks can contain Twig syntax alongside standard MODX placeholders.

## Relationship with pdoTools

The Twig extra does **not** depend on pdoTools. It works whether pdoTools is installed or not.

When pdoTools is installed:

- pdoTools may register its own parser as `$modx->parser`, adding Fenom template support and FastField tags (`[[#123.pagetitle]]`).
- The Twig `twigparser` service operates independently. It does not interfere with pdoTools' parser or its Fenom processing.
- pdoTools' other services (`pdoFetch`, `CoreTools`, `getChunk()`) continue to work normally.

When pdoTools is not installed:

- The core `modParser` handles MODX tags.
- The Twig `twigparser` service works the same way.

### Twig vs Fenom

Twig and Fenom are both template engines, but they serve the Twig extra differently:

- **Fenom** (via pdoTools) processes templates during the main MODX parser cycle. It is active when pdoTools is installed and the `pdotools_fenom_parser` setting is enabled.
- **Twig** processes templates when explicitly invoked via the `twigparser` service. It does not replace or interfere with Fenom.

If a site uses both pdoTools and Twig, they operate in separate contexts. The global parser handles Fenom (if enabled), while the `twigparser` service handles Twig rendering where it is called.

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

The Twig bootstrap (`bootstrap.php`) registers two services:

1. `Boffinate\Twig\Twig::class` — a factory that creates the parser instance
2. `'twigparser'` — an alias that resolves to the same instance

The factory supports class overrides via the `modxTwig.class` system setting, following the same pattern used by other MODX extras.

## Cache management

Twig compiles templates to PHP files for performance. The compiled templates are stored in the MODX cache directory under `cache/twig/`. The `TwigCacheClear` plugin listens to the `OnSiteRefresh` event and clears compiled templates when the site cache is refreshed.

You can also clear compiled templates programmatically:

```php
$twig = $modx->services->get('twigparser');
$twig->clearCompiledTemplates();
```
