# Twig for MODX

Use Twig template syntax inside MODX templates, chunks, resources, and ContentBlocks fields -- without replacing the MODX parser.

```twig
<h1>{{ resource.pagetitle|upper }}</h1>
{% if resource.HeroImage %}
    <img src="{{ resource.HeroImage }}">
{% endif %}
{{ chunk('HeroCta', {'label': 'Buy now'}) }}
```

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
- Twig 3 (bundled with the extra)

## Optional Extras

- [pdoTools](https://docs.modx.pro/components/pdotools/) -- if installed, its services (chunk rendering, Fenom, pdoFetch) continue to work alongside Twig. Not required.

## Supported Extras

- [ContentBlocks](https://www.modmore.com/contentblocks/) -- use Twig syntax in field and repeater templates. See the [ContentBlocks guide](contentblocks.md).
