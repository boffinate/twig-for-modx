<?php
declare(strict_types=1);

/**
 * Listen to the event: ContentBlocks_BeforeParse
 *
 * @var \MODX\Revolution\modX $modx
 * @var string $tpl
 * @var array<string, mixed> $phs
 */

if (!\Boffinate\Twig\Twig::containsTwigSyntax($tpl)) {
    return;
}

/** @var \Boffinate\Twig\Twig $twig */
$twig = $modx->services->get('twigparser');
if (!is_array($phs)) {
    $matches = [];
    $modx->parser->collectElementTags($tpl, $matches);
    if (!empty($matches)) {
        // Strip the leading '+' from the placeholder name
        $phs = [substr($matches[0][1], 1) => $phs];
    } else {
        $phs = ['value' => $phs];
    }
}

$modx->event->_output = $twig->renderString($tpl, $phs);
return;
