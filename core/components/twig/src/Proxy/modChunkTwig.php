<?php
declare(strict_types=1);

namespace Boffinate\Twig\Proxy;

use Boffinate\Twig\Twig;
use MODX\Revolution\modChunk;

/**
 * A pain we have to extend the whole of modChunk, but MODX has some `instanceof` checks we need to pass.
 * If only there was an interface to implement :)
 */
class modChunkTwig extends modChunk
{
    public function __construct(private modChunk $wrappedClass, private Twig $twig)
    {
        parent::__construct($twig->modx);
    }

    /*
     * The parser and modX::getChunk() configure the element before calling
     * process(): the outer tag that keys the element cache, and whether the
     * tag was cacheable at all. Both setters are concrete on modElement, so
     * __call never forwards them, and the wrapped chunk — which does the
     * processing — would default to cacheable under a tag it rebuilds from
     * name and properties: [[!$chunk]] and repeated getChunk() calls would
     * then be served the first output of the request even after the
     * placeholders they read had changed.
     */
    public function setTag($tag)
    {
        parent::setTag($tag);
        $this->wrappedClass->setTag($tag);
    }

    public function setCacheable($cacheable = true)
    {
        parent::setCacheable($cacheable);
        $this->wrappedClass->setCacheable($cacheable);
    }

    public function process($properties = null, $content = null)
    {
        $response = $this->wrappedClass->process($properties, $content);
        if (!Twig::containsTwigSyntax($response)) {
            return $response;
        }

        return $this->twig->renderString(
            $response,
            array_merge(
                (array) $this->wrappedClass->_properties,
                is_array($properties) ? $properties : []
            )
        );
    }

    public function __call(string $name, array $arguments)
    {
        return $this->wrappedClass->$name(...$arguments);
    }

    public function __get($name)
    {
        return $this->wrappedClass->$name;
    }

    public function __set($name, mixed $value): void
    {
        $this->wrappedClass->$name = $value;
    }

    public function __isset($name): bool
    {
        return isset($this->wrappedClass->$name);
    }

    public function __unset(string $name): void
    {
        unset($this->wrappedClass->$name);
    }
}
