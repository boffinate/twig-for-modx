<?php
declare(strict_types=1);

/*
 * Clears the compiled Twig templates on OnSiteRefresh (the manager's full
 * Clear Cache) and on those OnCacheUpdate firings that represent a full,
 * deploy-style refresh. Chunk and template sources are compiled from
 * strings and keyed by content hash, so they can never go stale; only
 * templates loaded from disk can, and those change at deploy time.
 *
 * Deploy scripts typically call modCacheManager::refresh() with no
 * arguments, which fires OnCacheUpdate — never OnSiteRefresh — so the
 * plugin must listen to both. But OnCacheUpdate also fires on every
 * manager resource save, whose partial refresh (db, auto_publish,
 * context_settings, resource) invalidates nothing compiled here; wiping
 * the tree then would force a sitewide recompile per save. The `scripts`
 * partition — MODX's own compiled element code — is only in the partition
 * map when the refresh is the full, everything-goes kind, so its presence
 * is the signal that compiled Twig should go too.
 */

if ($modx->event->name === 'OnCacheUpdate') {
    $partitions = $modx->event->params['paths'] ?? [];

    if (!is_array($partitions) || !array_key_exists('scripts', $partitions)) {
        return '';
    }
}

require_once MODX_CORE_PATH . 'components/twig/vendor/autoload.php';

\Boffinate\Twig\Twig::clearCompiledTemplatesForModx($modx);

return '';
