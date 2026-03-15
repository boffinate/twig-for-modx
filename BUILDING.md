# Building The Twig Transport Package

This note captures the packaging issues already hit in this repo so they do not get reintroduced.

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

## Quick Verification Checklist

After building:

1. Confirm the zip exists in `core/packages/`.
2. Confirm the extracted package exists in `core/packages/twig-<version>-pl/`.
3. Inspect the category vehicle and ensure the file resolvers use `"name":"twig"`, not `"name":"components"`.
4. Confirm `manifest.php` contains `readme`, `license`, and `changelog`.
5. Run the targeted regression test:

```bash
./core/vendor/bin/phpunit -c _build/test/phpunit.xml _build/test/Tests/Transport/TwigExtraBuildConfigTest.php
```

## Version Bump Checklist

When releasing a new package version, update:

- `extras/twig-extra/_build/build.config.php`
- `extras/twig-extra/_build/docs/changelog.txt`
- `extras/twig-extra/README.md`

Then rebuild the transport package.
