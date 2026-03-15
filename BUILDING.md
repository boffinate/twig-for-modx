# Building The Twig Transport Package

This note captures build and packaging pitfalls already hit in this repo so they do not get reintroduced.

For the release checklist and version bump flow, see [docs/releasing.md](./docs/releasing.md).

## Working Build Flow

Use the DDEV-backed MODX install for builds, not the host PHP environment.

Preferred workflow:

```bash
cd extras/twig-extra
./bin/dev-rebuild.sh
```

That script:

- installs component Composer dependencies
- builds the transport package with `_build/build.transport.php`
- reinstalls the package into the local MODX database

If you only need the zip:

```bash
ddev exec php /var/www/html/extras/twig-extra/_build/build.transport.php
```

The built artifact is written to:

```text
/var/www/html/core/packages/twig-<version>-pl.transport.zip
```

In this repo that is also visible at:

```text
core/packages/twig-<version>-pl.transport.zip
```

## Why Host Builds Fail

`extras/twig-extra/_build/includes/functions.php` loads:

- `config.core.php` from the MODX base path
- `MODX_CORE_PATH . 'vendor/autoload.php'`

The build config resolves the MODX base path relative to the extra so it expects the DDEV container layout under `/var/www/html/`.

If you run the build directly on the host, it can fail with errors like:

```text
require_once(/var/www/html/core/vendor/autoload.php): Failed to open stream
```

That is an environment mismatch, not a package bug.

## Critical Resolver Rule

Do not package the parent `core/components/` or `assets/components/` directories.

Correct:

- source: the component directory itself, for example `.../core/components/twig`
- target: `MODX_CORE_PATH . 'components/'`

Wrong:

- source: `.../core/components/`
- target: `MODX_CORE_PATH . 'components/'`

The wrong combination installs files into:

```text
core/components/components/twig
assets/components/components/twig
```

The helper functions that enforce the correct behavior are:

- `twigBuildCoreResolverDefinition()`
- `twigBuildAssetsResolverDefinition()`

## README / Install Screen Caveat

If the package README does not show during install, do not assume the package metadata is missing.

The package manifest already includes `license`, `readme`, and `changelog`. Check the extracted package:

```text
core/packages/twig-<version>-pl/manifest.php
```

One failure already seen was an older MODX manager JS bug in the target site:

- browser console error: `Cannot read properties of undefined (reading 'id')`
- file involved: `manager/assets/modext/widgets/core/modx.tabs.js`

That breaks the package-before-install tabs before the README can render. It is a host MODX manager issue, not a Twig transport metadata issue.

## Package Sanity Checks

If a build or install looks wrong, check:

1. `core/packages/twig-<version>-pl.transport.zip` exists.
2. `core/packages/twig-<version>-pl/manifest.php` contains `readme`, `license`, and `changelog`.
3. The category vehicle uses `"name":"twig"`, not `"name":"components"`, in the file resolver definitions.
4. The targeted regression test still passes:

```bash
./core/vendor/bin/phpunit -c _build/test/phpunit.xml _build/test/Tests/Transport/TwigExtraBuildConfigTest.php
```
