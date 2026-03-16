# Changelog

## 0.4.0-pl

- Install Twig as a parser decorator so Twig syntax renders automatically in templates, chunks, resource content, and snippet output — no longer limited to ContentBlocks
- Twig renders before MODX tags and Fenom, so `{{ }}` and `{% %}` do not conflict with pdoTools
- Custom `dump()` that filters globals from no-arg and `_context` dumps, showing only template-specific variables
- VarDumper casters for modX and xPDO: useful properties (config, resource, request, response, user, placeholders) are expandable, framework internals shown as collapsed stubs
- Dump output rendered in an iframe to isolate VarDumper JS/CSS from Fenom parsing
- Safety guards: skip Twig pass on content without Twig syntax, recursion depth limit, 5 MB output size limit, graceful handling of invalid Twig syntax
- Fix plugin output to use `$modx->event->_output` (correct MODX convention)

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
