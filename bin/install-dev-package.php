<?php
declare(strict_types=1);

use MODX\Revolution\modX;
use MODX\Revolution\Transport\modTransportPackage;
use xPDO\Transport\xPDOTransport;

$config = require dirname(__DIR__) . '/_build/build.config.php';
require_once dirname(__DIR__) . '/_build/includes/functions.php';

$modx = twigBuildGetModx($config['modx_base_path']);
$versionParts = array_map('intval', explode('.', $config['version']));

$signature = strtolower($config['name']) . '-' . $config['version'] . '-' . $config['release'];
$liveCorePath = rtrim($config['modx_base_path'], '/\\') . '/core/components/twig';
$liveAssetsPath = rtrim($config['modx_base_path'], '/\\') . '/assets/components/twig';

$coreLinked = is_link($liveCorePath) && realpath($liveCorePath) === realpath($config['component_core_path']);
$repoHasAssets = twigBuildHasFiles($config['component_assets_path']);
$assetsLinked = !$repoHasAssets || (is_link($liveAssetsPath) && realpath($liveAssetsPath) === realpath($config['component_assets_path']));
$installFiles = !($coreLinked && $assetsLinked);

/** @var modTransportPackage|null $package */
$package = $modx->getObject(modTransportPackage::class, ['signature' => $signature]);
if ($package === null) {
    $package = $modx->newObject(modTransportPackage::class);
}

$package->set('signature', $signature);
$package->fromArray([
    'state' => 1,
    'created' => $package->get('created') ?: date('Y-m-d H:i:s'),
    'workspace' => 1,
    'package_name' => strtolower($config['name']),
    'version_major' => $versionParts[0] ?? 0,
    'version_minor' => $versionParts[1] ?? 0,
    'version_patch' => $versionParts[2] ?? 0,
    'release' => $config['release'],
    'release_index' => 0,
], '', true, true);

if (!$package->get('source')) {
    $package->set('source', $signature . '.transport.zip');
}
$package->save();

$modx->log(modX::LOG_LEVEL_INFO, 'Installing package ' . $signature . ' (install_files=' . ($installFiles ? 'true' : 'false') . ')');

$installed = $package->install([
    'signature' => $signature,
    xPDOTransport::INSTALL_FILES => $installFiles,
]);

$modx->cacheManager->refresh();

if (!$installed) {
    fwrite(STDERR, 'Package install failed for ' . $signature . PHP_EOL);
    exit(1);
}

$modx->log(modX::LOG_LEVEL_INFO, 'Installed package ' . $signature);
