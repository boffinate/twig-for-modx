<?php
declare(strict_types=1);

use MODX\Revolution\modX;
use MODX\Revolution\Transport\modPackageBuilder;
use xPDO\Transport\xPDOTransport;

$start = microtime(true);
$config = require __DIR__ . '/build.config.php';

require_once __DIR__ . '/includes/functions.php';

$modx = twigBuildGetModx($config['modx_base_path']);
$modx->log(modX::LOG_LEVEL_INFO, 'Building Twig transport package...');

$builder = new modPackageBuilder($modx);
$builder->createPackage($config['name'], $config['version'], $config['release']);
$builder->registerNamespace(
    $config['namespace'],
    false,
    true,
    '{core_path}components/twig/',
    '{assets_path}components/twig/'
);

$eventAttributes = [
    xPDOTransport::UNIQUE_KEY => 'name',
    xPDOTransport::PRESERVE_KEYS => true,
    xPDOTransport::UPDATE_OBJECT => true,
];

foreach (require __DIR__ . '/data/transport.events.php' as $definition) {
    $builder->putVehicle($builder->createVehicle(twigBuildCreateEvent($modx, $definition), $eventAttributes));
    $modx->log(modX::LOG_LEVEL_INFO, 'Packaged event: ' . $definition['name']);
}

$settingAttributes = [
    xPDOTransport::UNIQUE_KEY => 'key',
    xPDOTransport::PRESERVE_KEYS => true,
    xPDOTransport::UPDATE_OBJECT => false,
];

foreach (require __DIR__ . '/data/transport.system_settings.php' as $definition) {
    $builder->putVehicle($builder->createVehicle(twigBuildCreateSystemSetting($modx, $definition), $settingAttributes));
    $modx->log(modX::LOG_LEVEL_INFO, 'Packaged system setting: ' . $definition['key']);
}

$category = twigBuildCreateCategory($modx, $config);
$categoryAttributes = [
    xPDOTransport::UNIQUE_KEY => 'category',
    xPDOTransport::PRESERVE_KEYS => false,
    xPDOTransport::UPDATE_OBJECT => true,
    xPDOTransport::RELATED_OBJECTS => true,
    xPDOTransport::RELATED_OBJECT_ATTRIBUTES => [
        'Plugins' => [
            xPDOTransport::UNIQUE_KEY => 'name',
            xPDOTransport::PRESERVE_KEYS => false,
            xPDOTransport::UPDATE_OBJECT => true,
            xPDOTransport::RELATED_OBJECTS => true,
            xPDOTransport::RELATED_OBJECT_ATTRIBUTES => [
                'PluginEvents' => [
                    xPDOTransport::UNIQUE_KEY => ['pluginid', 'event'],
                    xPDOTransport::PRESERVE_KEYS => true,
                    xPDOTransport::UPDATE_OBJECT => false,
                ],
            ],
        ],
        'Snippets' => [
            xPDOTransport::UNIQUE_KEY => 'name',
            xPDOTransport::PRESERVE_KEYS => false,
            xPDOTransport::UPDATE_OBJECT => true,
        ],
        'Chunks' => [
            xPDOTransport::UNIQUE_KEY => 'name',
            xPDOTransport::PRESERVE_KEYS => false,
            xPDOTransport::UPDATE_OBJECT => true,
        ],
        'Templates' => [
            xPDOTransport::UNIQUE_KEY => 'templatename',
            xPDOTransport::PRESERVE_KEYS => false,
            xPDOTransport::UPDATE_OBJECT => true,
        ],
    ],
];

$vehicle = $builder->createVehicle($category, $categoryAttributes);
$vehicle->resolve('file', twigBuildCoreResolverDefinition($config));

if ($assetsResolver = twigBuildAssetsResolverDefinition($config)) {
    $vehicle->resolve('file', $assetsResolver);
}

$builder->putVehicle($vehicle);
$modx->log(modX::LOG_LEVEL_INFO, 'Packaged category and file vehicles.');

$builder->setPackageAttributes([
    'license' => file_get_contents($config['repo_root'] . '/LICENSE.md'),
    'readme' => file_get_contents($config['repo_root'] . '/README.md'),
    'changelog' => file_get_contents($config['repo_root'] . '/CHANGELOG.md'),
]);

$builder->pack();

$elapsed = sprintf('%0.4f', microtime(true) - $start);
$signature = strtolower($config['name']) . '-' . $config['version'] . '-' . $config['release'];
$modx->log(modX::LOG_LEVEL_INFO, 'Built package: ' . $signature . '.transport.zip');
$modx->log(modX::LOG_LEVEL_INFO, 'Finished in ' . $elapsed . 's');
