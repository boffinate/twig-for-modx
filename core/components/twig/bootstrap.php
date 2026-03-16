<?php
/** @var MODX\Revolution\modX $modx */

require_once MODX_CORE_PATH . 'components/twig/vendor/autoload.php';

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
