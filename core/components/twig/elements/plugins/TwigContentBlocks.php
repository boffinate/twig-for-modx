<?php
declare(strict_types=1);

/**
 * Listen to the event: ContentBlocks_BeforeParse
 *
 * @var \MODX\Revolution\modX $modx
 * @var string $tpl
 * @var array<string, mixed> $phs
 */

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

if (!\Boffinate\Twig\Twig::containsTwigSyntax($tpl)) {
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
        /* The tag name carries the '+' placeholder token; the key must not. */
        $phs = [substr($matches[0][1], 1) => $phs];
    } else {
        $phs = ['value' => $phs];
    }
}

$output = (string) $twig->renderString($tpl, $phs);

/*
 * ContentBlocks::parse() runs array_filter() over the event responses and
 * then checks !empty() on the first, so both '' and '0' read as "no plugin
 * answered" and it goes on to parse the raw template — which puts the
 * unrendered Twig source on the page. A template whose Twig legitimately
 * renders to nothing ({% if %} around the whole thing), the production error
 * fallback and a bare zero all hit this, so hand over a single space instead:
 * it survives both checks and is inert in HTML.
 */
$modx->event->_output = $output === '' || $output === '0' ? ' ' : $output;
return;
