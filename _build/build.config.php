<?php
declare(strict_types=1);

$repoRoot = realpath(dirname(__DIR__));
$modxBasePath = realpath(dirname(__DIR__, 3));

if ($repoRoot === false || $modxBasePath === false) {
    throw new RuntimeException('Unable to resolve repository or MODX base path for Twig build.');
}

return [
    'name' => 'twig',
    'display_name' => 'Twig',
    'namespace' => 'twig',
    'version' => '0.2.0',
    'release' => 'pl',
    'repo_root' => $repoRoot,
    'modx_base_path' => rtrim($modxBasePath, '/\\') . '/',
    'component_core_path' => $repoRoot . '/core/components/twig/',
    'component_assets_path' => $repoRoot . '/assets/components/twig/',
];
