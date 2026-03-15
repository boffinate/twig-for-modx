<?php
/** @var MODX\Revolution\modX $modx */

require_once MODX_CORE_PATH . 'components/twig/vendor/autoload.php';

// Add factories
$modx->services[Boffinate\Twig\Twig::class] = $modx->services->factory(function ($c) use ($modx) {
    $class = $modx->getOption('modxTwig.class', null, \Boffinate\Twig\Twig::class, true);
    return new $class($modx, $c->get('pdotools'));
});
// Add services
$modx->services->add('twigparser', function ($c) use ($modx) {
    return $c->get(\Boffinate\Twig\Twig::class);
});
