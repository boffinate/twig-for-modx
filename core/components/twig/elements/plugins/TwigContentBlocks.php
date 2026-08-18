<?php
declare(strict_types=1);

/**
 * Listen to the event: ContentBlocks_BeforeParse
 *
 * @var \MODX\Revolution\modX $modx
 * @var string $tpl
 * @var array<string, mixed> $phs
 */

if (
    !str_contains($tpl, '{{')
    && !str_contains($tpl, '{%')
    && !str_contains($tpl, '{#')
    && !str_contains($tpl, '<twig:')
) {
    return;
}

$failureFlag = '__twig_contentblocks_parser_unavailable';
$message = '[TwigContentBlocks] twigparser service unavailable — CB field templates will render without Twig';

if (!class_exists(\Boffinate\Twig\Twig::class)) {
    /*
     * The one failure fromServicesOrLogOnce() cannot report itself: with the
     * class missing there is no static method to call, so log-once here.
     */
    if (!$modx->getOption($failureFlag, null, false)) {
        $modx->log(\xPDO\xPDO::LOG_LEVEL_ERROR, $message . ' (Boffinate\\Twig\\Twig is not autoloadable)');
        $modx->setOption($failureFlag, true);
    }
    $modx->event->_output = $tpl;
    return;
}

$twig = \Boffinate\Twig\Twig::fromServicesOrLogOnce($modx, $failureFlag, $message);
if ($twig === null) {
    $modx->event->_output = $tpl;
    return;
}
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
