# Changelog

## Unreleased

- Let the ContentBlocks bridge degrade to unchanged Twig placeholders, with a once-per-request error log, when the `twigparser` service is unavailable
- Stop Twig parser tests leaking pdoTools' shared `useFenomParser` config between tests, so the suite is reliable under random execution order
- Add a pdoTools-aware package setup option: clean installs detect pdoTools and offer to persist the two Twig service-class settings, while preserving existing custom values unless the installer explicitly opts in
- Add Twig-aware pdoTools subclasses (`Boffinate\Twig\PdoTools\CoreToolsTwig` and `FetchTwig`, registered via the `pdotools_pdotools_class` and `pdotools_fetch_class` system settings) so tpl chunks fetched by pdoTools-based snippets (pdoMenu, pdoResources, ...) render Twig with the chunk's resolved placeholders — including `@INLINE` bindings and fast mode. These chunks bypass the MODX parser's `getElement()`, so the modChunkTwig proxy never saw them before. The namespace bootstrap now sets both options itself immediately before it resolves the shared `pdotools` service, which is the only point early enough to win the race — keep them as real system settings as well, for the services resolved after `_initContext()` rebuilds the config
- **BREAKING: Twig no longer compiles the assembled document by default.** The catch-all pass over the uncacheable document is now gated behind a new `twig.document_pass` system setting, **off by default**. That pass has no provenance: template output, snippet output, editor content and anything echoed back from the request are one string by the time it runs. The last of those is the sharp end — a search page printing "no results for X" compiles X, making `?q={{ modx.config.site_name }}` unauthenticated server-side template injection with the full MODX API in scope. It also broke pages on an innocent literal `{{` in a code sample. A sandbox cannot fix it: stopping `{{ modx.runSnippet(…) }}` in a blog post would also disarm the element-generated Twig the pass exists to serve. Element-level rendering now covers that ground — templates, chunks, ContentBlocks field templates and pdoTools tpl chunks each render Twig where the element is resolved

- **Twig now renders in template content at element level**, via a `modTemplateTwig` proxy that bootstrap.php installs on modResource's `Template` relation. Templates are the one element type the parser cannot reach — `modResource::process()` resolves them through an xPDO relation, never `getElement()` — so this decorates the relation instead. Rendering happens in `getContent()`, before `modTemplate::process()` runs the tag pass, which means template Twig is evaluated while the string is still template source rather than after `[[*content]]` has merged the resource body in.

  Two behaviour notes: template Twig now evaluates **Twig first, MODX tags second** (the reverse of chunks, which render after their tags are substituted); and it is part of the *cacheable* pass, so it is evaluated at cache generation rather than per request as it was when the document pass drove it. Emit `[[!snippet]]` tags from Twig for per-request data

- Compiled templates are no longer auto-reloaded outside debug mode, so production stops stat-ing every template file per request. The TwigCacheClear plugin now also listens on `OnCacheUpdate` (not just `OnSiteRefresh`), so `modCacheManager::refresh()` — what deploy scripts call — clears the compiled templates too

- Add `tests/SecurityBoundaryTest.php`: executable coverage of what may and may not be compiled — query-string input echoed into a page, editor content merged through `[[*content]]`, TV values, snippet output — each paired with the case showing what the boundary moving would look like

  **Upgrading from 0.7.0:** register the two pdoTools class settings if you use pdoMenu/pdoResources tpls with Twig in them. Templates keep working with no action. Set `twig.document_pass` = `1` to restore the old behaviour — you need it only for chunks fetched by extras the subclasses do not cover (Alpacka-based extras, Tagger) or Twig emitted directly in snippet output; audit anywhere request data is echoed into a page first

- Integrate Symfony UX TwigComponent (anonymous components), hand-wired so no Symfony framework is required. `<twig:Button label="…" />` syntax, `{% props %}` validation, the `attributes` bag, slots via component content, and the `component()` function all work anywhere Twig renders — including chunks and ContentBlocks templates. Component templates are `.html.twig` files in a `components/` directory under any registered template path (`twig.components_dir` overrides the directory name; `twig.components` disables the integration). `containsTwigSyntax()` now also detects `<twig:` so component-only content triggers the Twig pass

- Add file-based template loading: a namespaced `FilesystemLoader` is chained with the string loader, fed from the new `twig.template_paths` system setting (JSON object of namespace => directory) and the new `registerTemplatePath()` method. `{% include %}` / `{% embed %}` / `{% extends %}` with file paths now work everywhere Twig renders
- Stop leaking template source to visitors on render errors. With `twig.debug` off, element renders return an empty string and the document-level pass returns the content with Twig delimiters made inert; errors are logged with template name and line. Debug mode keeps the old return-the-source behaviour

## 0.7.0-pl

- Upgrade Twig from 3.23 to 3.24
- Remove `modx_runtime` Twig global. All its methods are available as Twig functions (`chunk()`, `snippet()`, `option()`, `link()`, etc.)

## 0.6.0-pl

- Extend pdoTools Parser directly when pdoTools is installed, removing the wrapper/delegate pattern
- Fenom and MODX tag processing now runs through the native parent class chain instead of a wrapped parser instance
- Falls back to extending modParser when pdoTools is not available
- Fix tests to account for Fenom post-processing of content

## 0.5.0-pl

- Add `twig.debug` system setting to control debug mode (enabled by default). When disabled, `dump()` returns nothing and the debug extension is not loaded
- Move VarDumper from dev dependency to runtime dependency so it is always available when debug is on
- Cache ResourceAccessor across renders so TV lookups are not repeated per chunk
- Unify ResourceAccessor property resolution to avoid double lookups from Twig's `__isset` + `__get` calls
- Cache ReflectionClass in chunk proxy instead of recreating on every property access
- Cache `parser_max_iterations` setting in ModxRuntime
- Define global keys once as a constant on Twig, referenced from ModxDebugExtension
- Remove redundant `syncGlobals()` call during initialisation

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
