# Changelog

## 0.3.0-pl

- Add `resource` global for unified access to resource fields and Template Variables
- TVs accessible as properties: `{{ resource.MyTV }}` returns the processed value
- Add `tvRawValue()` method for raw TV access without output rendering
- TV lookups cached per-request to avoid redundant queries
- Add "Coming from Fenom" migration guide
- Update all documentation to use the resource global
- Rewrite README with clearer introduction

## 0.2.0-pl

- Remove hard dependency on PDOTools; Twig now extends modParser directly
- Twig extra works whether PDOTools is installed or not
- PDOTools services and Fenom processing remain unaffected
- Add how-it-works documentation explaining the architecture

## 0.1.2-pl

- Stop packaging placeholder `.gitignore` files and empty transport directories
- Skip the assets file resolver when the assets tree contains no packageable files

## 0.1.1-pl

- Fix file vehicle resolver paths so the component installs into `core/components/twig` and `assets/components/twig`
- Keep package metadata and README available during install

## 0.1.0-pl

- Initial MODX Extra packaging for the Twig component
- Packages namespace, custom system event, plugins, and component files
- Add local development scripts for build and reinstall
