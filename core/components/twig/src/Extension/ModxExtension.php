<?php
declare(strict_types=1);

namespace Boffinate\Twig\Extension;

use Boffinate\Twig\Support\ModxRuntime;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class ModxExtension extends AbstractExtension
{
    public function __construct(private ModxRuntime $runtime)
    {
    }

    /*
     * The callables point straight at ModxRuntime, whose methods already
     * carry the signatures these functions expose — a wrapper per function
     * would only restate them.
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('chunk', [$this->runtime, 'chunk'], ['is_safe' => ['html']]),
            new TwigFunction('snippet', [$this->runtime, 'snippet'], ['is_safe' => ['html']]),
            new TwigFunction('uncached_snippet', [$this->runtime, 'uncachedSnippet'], ['is_safe' => ['html']]),
            new TwigFunction('placeholder', [$this->runtime, 'placeholder']),
            new TwigFunction('ph', [$this->runtime, 'placeholder']),
            new TwigFunction('option', [$this->runtime, 'option']),
            new TwigFunction('config', [$this->runtime, 'option']),
            new TwigFunction('lexicon', [$this->runtime, 'lexicon']),
            new TwigFunction('trans', [$this->runtime, 'translate']),
            new TwigFunction('link', [$this->runtime, 'link']),
            new TwigFunction('resource_url', [$this->runtime, 'resourceUrl']),
            new TwigFunction('field', [$this->runtime, 'field']),
        ];
    }
}
