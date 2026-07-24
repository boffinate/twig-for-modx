<?php
declare(strict_types=1);

namespace Boffinate\Twig\PdoTools;

use Boffinate\Twig\Twig;

/*
 * Twig rendering for chunks resolved through pdoTools itself.
 *
 * pdoTools-based snippets (pdoMenu, pdoResources, etc.) fetch their tpl
 * chunks through CoreTools::getChunk()/parseChunk(), which load elements
 * directly via $modx->getObject() and never touch the MODX parser's
 * getElement(). That means the modChunkTwig proxy the Twig parser installs
 * for regular [[$chunk]] tags is bypassed and Twig syntax in tpl chunks
 * would reach the page unrendered.
 *
 * This trait is shared by CoreToolsTwig and FetchTwig (Fetch extends
 * CoreTools separately, so both subclasses need the same overrides). It
 * post-renders the chunk output through the `twigparser` service with the
 * placeholders pdoTools resolved for the chunk — the same "process first,
 * Twig after" order the modChunkTwig proxy uses. renderString() no-ops on
 * content without Twig syntax and carries recursion protection, so any
 * overlap with the parser-level rendering is harmless.
 *
 * The trait is inert when the `twigparser` service is not registered:
 * every path falls back to plain pdoTools behaviour.
 */
trait RendersTwigChunks
{
    /**
     * @param string $name
     * @param array $properties
     * @param bool $fastMode
     *
     * @return mixed
     */
    public function getChunk($name = '', array $properties = [], $fastMode = false)
    {
        $name = $this->renderTwigInInlineBinding((string)$name, $properties);
        $output = parent::getChunk($name, $properties, $fastMode);

        return $this->renderTwigChunkOutput($output, $properties);
    }

    /**
     * @param string $name
     * @param array $properties
     * @param string $prefix
     * @param string $suffix
     *
     * @return mixed
     */
    public function parseChunk($name = '', array $properties = [], $prefix = '[[+', $suffix = ']]')
    {
        $name = $this->renderTwigInInlineBinding((string)$name, $properties);
        $output = parent::parseChunk($name, $properties, $prefix, $suffix);

        return $this->renderTwigChunkOutput($output, $properties);
    }

    /**
     * pdoTools' _loadElement() converts {{ / }} to [[ / ]] inside @INLINE
     * and @CODE chunk bodies (its historical convention for embedding MODX
     * tags in inline templates), which would destroy Twig output syntax
     * before it could ever render. Render Twig on the inline body first,
     * so only the rendered result goes through that conversion.
     *
     * On a Twig error the untouched source is kept, so legacy inline
     * chunks that really do use {{ ... }} as MODX tag shorthand keep
     * working exactly as before.
     */
    private function renderTwigInInlineBinding(string $name, array $placeholders): string
    {
        if (!preg_match('#^(!?@(?:INLINE|CODE))(?![A-Z])(.*)$#s', trim($name), $matches)) {
            return $name;
        }

        $content = ltrim($matches[2], ' :');
        if (!Twig::containsTwigSyntax($content)) {
            return $name;
        }

        $twig = $this->twigParserService();
        if ($twig === null) {
            return $name;
        }

        return $matches[1] . ' ' . $twig->renderString($content, $placeholders, Twig::ERROR_FALLBACK_SOURCE);
    }

    /**
     * @param mixed $output
     * @param array $placeholders
     *
     * @return mixed
     */
    private function renderTwigChunkOutput($output, array $placeholders)
    {
        if (!is_string($output) || !Twig::containsTwigSyntax($output)) {
            return $output;
        }

        $twig = $this->twigParserService();
        if ($twig === null) {
            return $output;
        }

        return $twig->renderString($output, $placeholders);
    }

    private function twigParserService(): ?Twig
    {
        if (!$this->modx->services->has('twigparser')) {
            return null;
        }

        $twig = $this->modx->services->get('twigparser');

        return $twig instanceof Twig ? $twig : null;
    }
}
