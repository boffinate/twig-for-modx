# Twig for MODX

The Twig for MODX Extra adds Twig template support to your normal MODX workflow. It does not replace MODX rendering. Instead it runs a Twig pass inside the existing MODX parser cycle, so you can mix Twig expressions with standard MODX tags in templates, chunks, resources, and snippet output.

```twig
<h1>{{ resource.pagetitle|upper }}</h1>
{% if resource.HeroImage %}
    <img src="{{ resource.HeroImage }}">
{% endif %}
{{ chunk('HeroCta', {'label': 'Buy now'}) }}
```

Twig is evaluated before the final MODX tag pass. After Twig renders, MODX still processes its own tags (`[[*pagetitle]]`, `[[+placeholder]]`, `[[Snippet]]`, `[[$Chunk]]`).

## Documentation

**For site builders and template authors:**

- [Getting Started](usage.md) -- installation, built-in functions, globals, escaping, filters
- [ContentBlocks](contentblocks.md) -- field templates, repeaters, the `row_data` variable
- [Coming from Fenom](coming-from-fenom.md) -- Fenom-to-Twig syntax mapping, user/profile access, what changes
- [Troubleshooting](troubleshooting.md) -- common mistakes, error handling, MODX-to-Twig cheat sheet

**For PHP developers building extras:**

- [Developer Guide](developer-guide.md) -- rendering from PHP, custom functions, the shared runtime, event integration, testing, API reference
- [Releasing](releasing.md) -- version bumps, changelog updates, transport package build and verification

## Requirements

- MODX 3
- PHP 8.1+
- Twig 3 (bundled with the extra)

## Optional Extras

- [pdoTools](https://docs.modx.pro/components/pdotools/) -- if installed, its services (chunk rendering, Fenom, pdoFetch) continue to work alongside Twig. Not required.

## Supported Extras

- [ContentBlocks](https://www.modmore.com/contentblocks/) -- use Twig syntax in field and repeater templates. See the [ContentBlocks guide](contentblocks.md).
