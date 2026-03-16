<?php
declare(strict_types=1);

use MODX\Revolution\modCategory;
use MODX\Revolution\modChunk;
use MODX\Revolution\modEvent;
use MODX\Revolution\modPlugin;
use MODX\Revolution\modPluginEvent;
use MODX\Revolution\modSnippet;
use MODX\Revolution\modSystemSetting;
use MODX\Revolution\modTemplate;
use MODX\Revolution\modX;

function twigBuildGetModx(string $modxBasePath): modX
{
    require_once $modxBasePath . 'config.core.php';
    require_once MODX_CORE_PATH . 'vendor/autoload.php';

    $modx = new modX();
    $modx->initialize('mgr');
    $modx->setLogLevel(modX::LOG_LEVEL_INFO);
    $modx->setLogTarget('ECHO');

    return $modx;
}

function twigBuildRequireWrapper(string $relativeFile): string
{
    return "return require MODX_CORE_PATH . 'components/twig/" . ltrim($relativeFile, '/') . "';";
}

function twigBuildStaticFilePath(string $relativeFile): string
{
    return '{core_path}components/twig/' . ltrim($relativeFile, '/');
}

function twigBuildReadComponentFile(array $config, string $relativeFile): string
{
    $path = rtrim($config['component_core_path'], '/\\') . '/' . ltrim($relativeFile, '/');
    $content = @file_get_contents($path);

    if ($content === false) {
        throw new RuntimeException('Unable to read component file: ' . $path);
    }

    return $content;
}

function twigBuildHasFiles(string $directory): bool
{
    if (!is_dir($directory)) {
        return false;
    }

    $items = scandir($directory);
    if (!is_array($items)) {
        return false;
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        if (str_starts_with($item, '.')) {
            continue;
        }

        return true;
    }

    return false;
}

function twigBuildCoreResolverDefinition(array $config): array
{
    return [
        'source' => rtrim($config['component_core_path'], '/\\'),
        'target' => "return MODX_CORE_PATH . 'components/';",
    ];
}

function twigBuildAssetsResolverDefinition(array $config): ?array
{
    if (!twigBuildHasFiles($config['component_assets_path'])) {
        return null;
    }

    return [
        'source' => rtrim($config['component_assets_path'], '/\\'),
        'target' => "return MODX_ASSETS_PATH . 'components/';",
    ];
}

function twigBuildCreateSystemSetting(modX $modx, array $definition): modSystemSetting
{
    $setting = $modx->newObject(modSystemSetting::class);
    $setting->fromArray([
        'key' => $definition['key'],
        'value' => $definition['value'] ?? '',
        'xtype' => $definition['xtype'] ?? 'textfield',
        'namespace' => $definition['namespace'] ?? 'twig',
        'area' => $definition['area'] ?? 'general',
        'editedon' => null,
    ], '', true, true);

    return $setting;
}

function twigBuildCreateEvent(modX $modx, array $definition): modEvent
{
    $event = $modx->newObject(modEvent::class);
    $event->fromArray([
        'name' => $definition['name'],
        'service' => $definition['service'] ?? 6,
        'groupname' => $definition['groupname'] ?? 'twig',
    ], '', true, true);

    return $event;
}

function twigBuildCreatePlugin(modX $modx, array $definition): modPlugin
{
    $plugin = $modx->newObject(modPlugin::class);
    $plugin->fromArray([
        'name' => $definition['name'],
        'description' => $definition['description'] ?? '',
        'plugincode' => twigBuildRequireWrapper($definition['file']),
        'static' => 0,
        'static_file' => '',
        'disabled' => $definition['disabled'] ?? 0,
    ], '', true, true);

    $events = [];
    foreach ($definition['events'] ?? [] as $eventDefinition) {
        $event = $modx->newObject(modPluginEvent::class);
        $event->fromArray([
            'event' => $eventDefinition['event'],
            'priority' => $eventDefinition['priority'] ?? 0,
            'propertyset' => $eventDefinition['propertyset'] ?? 0,
        ], '', true, true);
        $events[] = $event;
    }

    if ($events !== []) {
        $plugin->addMany($events, 'PluginEvents');
    }

    return $plugin;
}

function twigBuildCreateSnippet(modX $modx, array $definition): modSnippet
{
    $snippet = $modx->newObject(modSnippet::class);
    $snippet->fromArray([
        'name' => $definition['name'],
        'description' => $definition['description'] ?? '',
        'snippet' => twigBuildRequireWrapper($definition['file']),
        'static' => 0,
        'static_file' => '',
    ], '', true, true);

    return $snippet;
}

function twigBuildCreateChunk(modX $modx, array $config, array $definition): modChunk
{
    $chunk = $modx->newObject(modChunk::class);
    $chunk->fromArray([
        'name' => $definition['name'],
        'description' => $definition['description'] ?? 'Chunk',
        'snippet' => twigBuildReadComponentFile($config, $definition['file']),
        'static' => 1,
        'static_file' => twigBuildStaticFilePath($definition['file']),
    ], '', true, true);

    return $chunk;
}

function twigBuildCreateTemplate(modX $modx, array $config, array $definition): modTemplate
{
    $template = $modx->newObject(modTemplate::class);
    $template->fromArray([
        'templatename' => $definition['name'],
        'description' => $definition['description'] ?? '',
        'content' => twigBuildReadComponentFile($config, $definition['file']),
        'static' => 1,
        'static_file' => twigBuildStaticFilePath($definition['file']),
    ], '', true, true);

    return $template;
}

function twigBuildCreateCategory(modX $modx, array $config): modCategory
{
    $category = $modx->newObject(modCategory::class);
    $category->fromArray([
        'category' => $config['display_name'],
        'parent' => 0,
    ], '', true, true);

    $plugins = [];
    foreach (require dirname(__DIR__) . '/data/transport.plugins.php' as $definition) {
        $plugins[] = twigBuildCreatePlugin($modx, $definition);
    }
    if ($plugins !== []) {
        $category->addMany($plugins, 'Plugins');
    }

    $snippets = [];
    foreach (require dirname(__DIR__) . '/data/transport.snippets.php' as $definition) {
        $snippets[] = twigBuildCreateSnippet($modx, $definition);
    }
    if ($snippets !== []) {
        $category->addMany($snippets, 'Snippets');
    }

    $chunks = [];
    foreach (require dirname(__DIR__) . '/data/transport.chunks.php' as $definition) {
        $chunks[] = twigBuildCreateChunk($modx, $config, $definition);
    }
    if ($chunks !== []) {
        $category->addMany($chunks, 'Chunks');
    }

    $templates = [];
    foreach (require dirname(__DIR__) . '/data/transport.templates.php' as $definition) {
        $templates[] = twigBuildCreateTemplate($modx, $config, $definition);
    }
    if ($templates !== []) {
        $category->addMany($templates, 'Templates');
    }

    return $category;
}
