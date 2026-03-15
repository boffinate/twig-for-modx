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

    public function getFunctions(): array
    {
        return [
            new TwigFunction('chunk', [$this, 'renderChunk'], ['is_safe' => ['html']]),
            new TwigFunction('snippet', [$this, 'runSnippet'], ['is_safe' => ['html']]),
            new TwigFunction('placeholder', [$this, 'getPlaceholder']),
            new TwigFunction('ph', [$this, 'getPlaceholder']),
            new TwigFunction('option', [$this, 'getOption']),
            new TwigFunction('config', [$this, 'getOption']),
            new TwigFunction('lexicon', [$this, 'lexicon']),
            new TwigFunction('trans', [$this, 'translate']),
            new TwigFunction('link', [$this, 'makeUrl']),
            new TwigFunction('field', [$this, 'getField']),
        ];
    }

    public function renderChunk(string $name, array $properties = []): string
    {
        return $this->runtime->chunk($name, $properties);
    }

    public function runSnippet(string $name, array $properties = []): string
    {
        return $this->runtime->snippet($name, $properties);
    }

    public function getPlaceholder(string $key, mixed $default = null): mixed
    {
        return $this->runtime->placeholder($key, $default);
    }

    public function getOption(string $key, mixed $default = null): mixed
    {
        return $this->runtime->option($key, $default);
    }

    public function lexicon(string $key, array $params = [], string $language = ''): ?string
    {
        return $this->runtime->lexicon($key, $params, $language);
    }

    public function translate(string $key, string $topic = '', array $params = [], string $language = ''): ?string
    {
        return $this->runtime->translate($key, $topic, $params, $language);
    }

    public function makeUrl(
        int|string $id,
        array|string $params = '',
        string $contextKey = '',
        int|string $scheme = -1,
        array $options = []
    ): string {
        return $this->runtime->link($id, $params, $contextKey, $scheme, $options);
    }

    public function getField(mixed $name, mixed $default = null, mixed $resource = null): mixed
    {
        return $this->runtime->field($name, $default, $resource);
    }
}
