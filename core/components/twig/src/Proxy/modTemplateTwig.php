<?php
declare(strict_types=1);

namespace Boffinate\Twig\Proxy;

use Boffinate\Twig\Twig;
use MODX\Revolution\modTemplate;

/*
 * Element-level Twig for templates.
 *
 * Every other element type can be intercepted through the parser: chunks,
 * snippets and TVs are resolved by getElement() (which is where the
 * modChunkTwig proxy is installed), and plugins run through the event
 * system. Templates are the exception — modResource::process() fetches one
 * with getOne('Template'), an xPDO relation, and modTemplate::process()
 * parses its own content with $processUncacheable = false. Nothing in the
 * parser gets a provenance-clean look at template source, which is why
 * Twig in templates used to depend on the document pass.
 *
 * So the interception happens one level down: bootstrap.php points the
 * Template aggregate at this class, and Twig renders in getContent().
 * modElement::process() calls that after resolving properties and before
 * running the tag pass, which is the only moment the string is still
 * purely template source — a moment later and [[*content]] has merged the
 * resource's editor-entered content into it.
 *
 * Ordering therefore differs from modChunkTwig on purpose: Twig first,
 * MODX tags second. A chunk is interpolated into itself and its author
 * decides what it pulls in; a template always merges the resource body.
 */
class modTemplateTwig extends modTemplate
{
    private bool $twigRendered = false;

    /**
     * @param array $options
     *
     * @return string|null
     */
    public function getContent(array $options = [])
    {
        $content = parent::getContent($options);

        if ($this->twigRendered || !is_string($content) || $content === '') {
            return $content;
        }

        /*
         * parent::getContent() has already resolved static files and synced
         * a changed file back to the database, so what is saved is always
         * the source, never the rendered output.
         */
        $twig = Twig::fromServices($this->xpdo);
        if ($twig === null
            || $this->xpdo->context === null
            || $this->xpdo->context->key === 'mgr'
            || !Twig::containsTwigSyntax($content)) {
            return $content;
        }

        $this->twigRendered = true;

        /*
         * Neutralize rather than blank on error. modResource::process()
         * reads an empty template result as "no template" and falls through
         * to rendering the raw resource content as the entire page, so an
         * empty-string fallback would turn one broken expression into a
         * page with no layout.
         */
        $this->_content = (string) $twig->renderString(
            $content,
            (array) $this->_properties,
            Twig::ERROR_FALLBACK_NEUTRALIZE
        );

        return $this->_content;
    }
}
