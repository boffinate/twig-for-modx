<?php
declare(strict_types=1);

use MODX\Revolution\modNamespace;
use MODX\Revolution\modSystemSetting;
use MODX\Revolution\modX;
use xPDO\Transport\xPDOTransport;
use xPDO\xPDO;

$modx = $object->xpdo ?? null;
if (!$modx instanceof modX) {
    return true;
}

$definitions = [
    'pdotools_pdotools_class' => Boffinate\Twig\PdoTools\CoreToolsTwig::class,
    'pdotools_fetch_class' => Boffinate\Twig\PdoTools\FetchTwig::class,
];
$action = $options[xPDOTransport::PACKAGE_ACTION] ?? null;

if ($action === xPDOTransport::ACTION_INSTALL || $action === xPDOTransport::ACTION_UPGRADE) {
    if (empty($options['twig_configure_pdotools'])) {
        return true;
    }

    if ($modx->getCount(modNamespace::class, ['name' => 'pdotools']) === 0) {
        $modx->log(xPDO::LOG_LEVEL_WARN, '[Twig] pdoTools was not found; its service-class settings were not created.');
        return true;
    }

    foreach ($definitions as $key => $value) {
        $setting = $modx->getObject(modSystemSetting::class, ['key' => $key]) ?? $modx->newObject(modSystemSetting::class);
        $setting->fromArray([
            'key' => $key,
            'value' => $value,
            'xtype' => 'textfield',
            'namespace' => 'pdotools',
            'area' => 'pdotools_main',
        ], '', true, true);

        if (!$setting->save()) {
            $modx->log(xPDO::LOG_LEVEL_ERROR, '[Twig] Could not save the ' . $key . ' system setting.');
            return false;
        }
    }

    return true;
}

if ($action === xPDOTransport::ACTION_UNINSTALL) {
    foreach ($definitions as $key => $value) {
        $setting = $modx->getObject(modSystemSetting::class, ['key' => $key]);
        if ($setting !== null && $setting->get('value') === $value && !$setting->remove()) {
            $modx->log(xPDO::LOG_LEVEL_ERROR, '[Twig] Could not remove the ' . $key . ' system setting.');
            return false;
        }
    }
}

return true;
