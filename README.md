# Twig for MODX

Twig template syntax for MODX 3. Conditionals, loops, filters, and auto-escaping in your templates, chunks, and resources.

```twig
<h1>{{ resource.pagetitle|upper }}</h1>
{% if resource.HeroImage %}
    <img src="{{ resource.HeroImage }}">
{% endif %}
{{ chunk('HeroCta', {'label': 'Buy now'}) }}
```

### Why Twig

MODX output modifiers and nested tags get the job done, but they were never designed for template logic. Ternary conditionals chained across output modifiers are hard to read. Looping over data requires a snippet. Testing for empty values means memorising which modifier does what.

Twig has proper `if`/`else`, `for` loops, and filters for formatting. Output is HTML-escaped by default, which prevents XSS without you having to think about it. If you have worked with Symfony, Craft CMS, or Drupal, you already know the syntax. IDE plugins for PhpStorm and VS Code give you autocomplete and syntax highlighting.

Unlike Fenom, Twig's `{{ }}` delimiters do not clash with JSON or JavaScript. You can put inline `<script>` blocks and JSON-LD in your templates without the parser trying to interpret every `{` as a variable.

### How it works

The extra does not replace the MODX parser. It adds a Twig rendering pass before the standard MODX tag cycle, so `{{ twig }}` and `[[modx]]` syntax work side by side in the same template. You can adopt it one template at a time without rewriting anything that already works.

Twig syntax is processed in templates, chunks, resource content, snippet output, and ContentBlocks field templates. It is not processed in the MODX manager.

### What you get

Resource fields and TVs are accessible as properties on the `resource` global. `{{ resource.pagetitle }}` and `{{ resource.MyCustomTV }}` both work the same way.

Helper functions -- `chunk()`, `snippet()`, `link()`, `option()`, `placeholder()`, `lexicon()` -- call the corresponding MODX features from inside a template.

ContentBlocks is supported. Use Twig syntax in field templates and repeater wrappers, with access to field data and the `row_data` variable.

All output is HTML-escaped by default. Use `|raw` when you know the content is safe.

If you are building an extra, you can register your own Twig functions, filters, and globals from PHP, either directly or through the `OnTwigInit` system event.

Twig and Fenom coexist without conflict. If you use PdoTools, it keeps working.

### Requirements

- MODX 3
- PHP 8.1+

Twig 3 is bundled with the extra.

## Documentation

**For template authors and site builders:**

- [Using the Twig Extra](./docs/usage.md) -- setup, built-in functions, globals, escaping, custom extensions
- [Using Twig with ContentBlocks](./docs/contentblocks.md) -- field templates, repeaters, the `row_data` variable
- [Coming from Fenom](./docs/coming-from-fenom.md) -- Fenom-to-Twig syntax mapping, user/profile access, what changes
- [Troubleshooting](./docs/troubleshooting.md) -- common mistakes, error handling, MODX-to-Twig migration cheat sheet

**For PHP developers building extras:**

- [Developer Guide](./docs/developer-guide.md) -- rendering Twig from PHP, registering functions and extensions, the shared runtime, event integration, testing, API reference

**For developers of this extension**

- [Releasing](./docs/releasing.md) -- incrementing the package version, updating the changelog, building the transport zip

## Development

See [BUILDING.md](./BUILDING.md) for the transport-package workflow and the packaging pitfalls already discovered in this repo.

## Layout

- `core/components/twig/` contains the runtime component that is installed into MODX.
- `_build/` contains the transport package build scripts.
- `bin/` contains local development helpers for this MODX instance.
- `docs/` contains user documentation.

## Local Development In This Environment

1. Link the live MODX component paths to this repo once:

```bash
./bin/dev-link.sh
```

2. Install component dependencies:

```bash
composer install --working-dir=core/components/twig
```

3. Rebuild and reinstall the package during development:

```bash
./bin/dev-rebuild.sh
```

That command:

- builds `twig-<version>-pl.transport.zip`
- writes it to `/var/www/html/core/packages/`
- reinstalls the package into the local MODX database
- skips file copying when the live component path is symlinked to this repo

## Packaging Rules Implemented Here

- chunks and templates are packaged as static elements, with files on disk as the source of truth
- snippets and plugins are packaged as database elements whose code is a thin `require` wrapper to a PHP file on disk
