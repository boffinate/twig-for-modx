<?php
declare(strict_types=1);

namespace Boffinate\Twig\PdoTools;

use ModxPro\PdoTools\CoreTools;

/*
 * Drop-in replacement for pdoTools' CoreTools that renders Twig in chunks
 * fetched outside the MODX parser (tpl chunks, @INLINE bindings, fast mode).
 *
 * Activate it via the `pdotools_pdotools_class` system setting:
 * Boffinate\Twig\PdoTools\CoreToolsTwig
 */
class CoreToolsTwig extends CoreTools
{
    use RendersTwigChunks;
}
