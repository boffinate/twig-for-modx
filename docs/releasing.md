# Releasing

This project keeps the package version in the transport build config. A release is just:

1. update the version metadata
2. update the changelog
3. build the transport package
4. verify the generated zip

## Increment The Version Number

Edit `_build/build.config.php` and change the `version` value:

```php
return [
    'name' => 'twig',
    'display_name' => 'Twig',
    'namespace' => 'twig',
    'version' => '0.2.0',
    'release' => 'pl',
    // ...
];
```

For a normal release, increment `version` and leave `release` as `pl` unless you intentionally need a different MODX release channel.

## Update The Changelog

Add the new release at the top of `_build/docs/changelog.txt`:

```text
Twig 0.2.1-pl
====================

- Describe the release here
```

Keep the newest version first so the package metadata shows the latest notes during install.

## Build The Package

Use the DDEV-backed MODX install, not the host PHP environment.

To rebuild and reinstall locally:

```bash
cd extras/twig-extra
./bin/dev-rebuild.sh
```

That script:

- runs `composer install --working-dir=core/components/twig`
- builds the transport package with `_build/build.transport.php`
- reinstalls the package into the local MODX database with `bin/install-dev-package.php`

If you only need the zip and do not want to reinstall it:

```bash
ddev exec php /var/www/html/extras/twig-extra/_build/build.transport.php
```

## Output

The build writes the artifact to:

```text
core/packages/twig-<version>-pl.transport.zip
```

The extracted package is also available in:

```text
core/packages/twig-<version>-pl/
```

## Verify The Release

After building, check:

1. `_build/build.config.php` has the version you meant to release.
2. `core/packages/twig-<version>-pl.transport.zip` exists.
3. `core/packages/twig-<version>-pl/manifest.php` contains `readme`, `license`, and `changelog`.
4. The changelog entry in `_build/docs/changelog.txt` matches the package version.

For the targeted packaging regression test, run:

```bash
./core/vendor/bin/phpunit -c _build/test/phpunit.xml _build/test/Tests/Transport/TwigExtraBuildConfigTest.php
```
