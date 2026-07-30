<?php
declare(strict_types=1);

use MODX\Revolution\modNamespace;
use MODX\Revolution\modSystemSetting;

/** @var MODX\Revolution\modX $modx */

if ($modx->getCount(modNamespace::class, ['name' => 'pdotools']) === 0) {
    return '';
}

$targets = [
    'pdotools_pdotools_class' => Boffinate\Twig\PdoTools\CoreToolsTwig::class,
    'pdotools_fetch_class' => Boffinate\Twig\PdoTools\FetchTwig::class,
];
$customValues = [];

foreach ($targets as $key => $target) {
    $setting = $modx->getObject(modSystemSetting::class, ['key' => $key]);
    if ($setting !== null && $setting->get('value') !== $target) {
        $customValues[$key] = (string) $setting->get('value');
    }
}

$checked = $customValues === [] ? ' checked' : '';
$description = 'pdoTools is installed. Save its service-class settings so pdoMenu, pdoResources, and other pdoTools snippets render Twig in their tpl chunks.';

if ($customValues !== []) {
    $current = [];
    foreach ($customValues as $key => $value) {
        $current[] = sprintf('<code>%s</code> is currently <code>%s</code>', htmlspecialchars($key, ENT_QUOTES, 'UTF-8'), htmlspecialchars($value, ENT_QUOTES, 'UTF-8'));
    }
    $description .= ' Existing custom values were detected (' . implode('; ', $current) . '), so this option is unchecked. Select it to replace both values with the Twig-aware classes.';
}

return sprintf(
    '<label><input type="checkbox" name="twig_configure_pdotools" value="1"%s> Configure pdoTools for Twig tpl chunks</label><p>%s</p>',
    $checked,
    $description
);
