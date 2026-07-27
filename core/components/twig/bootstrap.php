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
