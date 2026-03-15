# Twig Extra

Standalone MODX Extra repository for the Twig integration component.

## Documentation

**For template authors and site builders:**

- [Using the Twig Extra](./docs/usage.md) -- setup, built-in functions, globals, escaping, custom extensions
- [Using Twig with ContentBlocks](./docs/contentblocks.md) -- field templates, repeaters, the `row_data` variable
- [Troubleshooting](./docs/troubleshooting.md) -- common mistakes, error handling, MODX-to-Twig migration cheat sheet

**For PHP developers building extras:**

- [Developer Guide](./docs/developer-guide.md) -- rendering Twig from PHP, registering functions and extensions, the shared runtime, event integration, testing, API reference
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
