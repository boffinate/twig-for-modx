<?php
declare(strict_types=1);

namespace Boffinate\Twig\PdoTools;

use ModxPro\PdoTools\Fetch;

/*
 * Drop-in replacement for pdoTools' Fetch that renders Twig in chunks
 * fetched outside the MODX parser (tpl chunks, @INLINE bindings, fast mode).
 *
 * Activate it via the `pdotools_fetch_class` system setting:
 * Boffinate\Twig\PdoTools\FetchTwig
 */
class FetchTwig extends Fetch
{
    use RendersTwigChunks;
}
