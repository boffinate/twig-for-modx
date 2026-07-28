<?php
/** @var MODX\Revolution\modX $modx */

require_once MODX_CORE_PATH . 'components/twig/vendor/autoload.php';
require_once MODX_CORE_PATH . 'components/twig/src/ParserBase.php';

// Add factories
$modx->services[Boffinate\Twig\Twig::class] = $modx->services->factory(function ($c) use ($modx) {
    $class = $modx->getOption('modxTwig.class', null, \Boffinate\Twig\Twig::class, true);
    return new $class($modx);
});
// Add services
$modx->services->add('twigparser', function ($c) use ($modx) {
    return $c->get(\Boffinate\Twig\Twig::class);
});

/*
 * Point pdoTools at the Twig-aware service classes, so Twig renders in tpl
 * chunks that pdoTools fetches outside the MODX parser.
 *
 * This has to happen here, immediately below, because the next line resolves
 * the `twigparser` service and Twig's constructor force-resolves the shared
 * `pdotools` service with it — pdoTools reads `pdotools_pdotools_class` once,
 * when that service is built. Nothing later (a settings plugin on OnMODXInit,
 * for instance) can get in ahead of it, which makes this bootstrap the only
 * layer that can guarantee the options are set first.
 *
 * Consuming sites should still persist these as real system settings:
 * $modx->config is rebuilt from the database by _initContext(), so services
 * resolved after that point read the stored values, not these.
 *
 * class_exists() on the subclass alone would be fatal when pdoTools is
 * absent — autoloading it would look for a parent that is not there — hence
 * the check on pdoTools' own class first.
 */
foreach ([
    'pdotools_pdotools_class' => [
        ModxPro\PdoTools\CoreTools::class,
        Boffinate\Twig\PdoTools\CoreToolsTwig::class,
    ],
    'pdotools_fetch_class' => [
        ModxPro\PdoTools\Fetch::class,
        Boffinate\Twig\PdoTools\FetchTwig::class,
    ],
] as $option => [$pdoToolsClass, $twigClass]) {
    if (class_exists($pdoToolsClass) && class_exists($twigClass)) {
        $modx->setOption($option, $twigClass);
    }
}

// Install as $modx->parser, wrapping the existing parser (pdoTools or core)
// so Twig renders {{ }}/{% %} before MODX tags and Fenom are processed.
$modx->services->get('twigparser')->decorateParser();

/*
 * Point the Template relation at the Twig-aware proxy, so Twig renders in
 * template content at element level (see src/Proxy/modTemplateTwig.php for
 * why templates cannot be reached through the parser).
 *
 * Only mysql\modResource declares the Template aggregate — modDocument and
 * other resource subclasses inherit it — so one map entry covers every
 * resource class and every getOne('Template') call site.
 *
 * This has to run before the first resource is instantiated, because
 * xPDOObject captures _aggregates at construction. Namespace bootstraps run
 * inside modX::initialize(), well ahead of getResource().
 */
(function (MODX\Revolution\modX $modx): void {
    $resourceClass = $modx->loadClass(MODX\Revolution\modResource::class);
    $templateClass = $modx->loadClass(MODX\Revolution\modTemplate::class);
    if (!$resourceClass || !$templateClass) {
        return;
    }

    /* Carry MODX's own schema over rather than restating it. */
    $templatePlatformClass = ltrim($modx->getPlatformClass(MODX\Revolution\modTemplate::class), '\\');
    if (!class_exists($templatePlatformClass)) {
        return;
    }
    Boffinate\Twig\Proxy\mysql\modTemplateTwig::$metaMap = $templatePlatformClass::$metaMap;
    $modx->map[Boffinate\Twig\Proxy\modTemplateTwig::class] = $modx->map[$templateClass];

    /* xPDOMap::offsetGet hands back a copy, so this is read-modify-write. */
    $resourceMap = $modx->map[$resourceClass];
    if (!isset($resourceMap['aggregates']['Template']['class'])) {
        return;
    }
    $resourceMap['aggregates']['Template']['class'] = Boffinate\Twig\Proxy\modTemplateTwig::class;
    $modx->map[$resourceClass] = $resourceMap;
})($modx);
