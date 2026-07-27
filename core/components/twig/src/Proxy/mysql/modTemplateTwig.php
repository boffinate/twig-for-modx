<?php
declare(strict_types=1);

namespace Boffinate\Twig\Proxy\mysql;

/*
 * Platform twin for the Template proxy.
 *
 * xPDO resolves a platform class by injecting the platform namespace ahead
 * of the short class name (xPDO::getPlatformClass()), so any class used as
 * an xPDO relation target needs a `…\mysql\` counterpart carrying the
 * metaMap. bootstrap.php copies modTemplate's metaMap into $metaMap at
 * load time rather than duplicating it here, so this stays correct as MODX
 * changes the schema.
 */
class modTemplateTwig extends \Boffinate\Twig\Proxy\modTemplateTwig
{
    public static $metaMap;
}
