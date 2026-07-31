<?php
declare(strict_types=1);

namespace Boffinate\Twig\Support;

use Boffinate\Twig\Twig;
use MODX\Revolution\modResource;
use MODX\Revolution\modX;

class ModxRuntime
{
    private ?int $maxIterations = null;

    public function __construct(private Twig $parser, private modX $modx)
    {
    }

    public function chunk(string $name, array $properties = []): string
    {
        return $this->processElementTag('$', $name, $properties);
    }

    public function snippet(string $name, array $properties = []): string
    {
        return $this->processElementTag('', $name, $properties);
    }

    public function placeholder(string $key, mixed $default = null): mixed
    {
        $value = $this->modx->getPlaceholder($key);

        return $value ?? $default;
    }

    public function option(string $key, mixed $default = null): mixed
    {
        return $this->modx->getOption($key, null, $default);
    }

    public function lexicon(string $key, array $params = [], string $language = ''): ?string
    {
        return $this->modx->lexicon($key, $params, $language);
    }

    public function translate(string $key, string $topic = '', array $params = [], string $language = ''): ?string
    {
        if ($topic !== '') {
            $this->modx->lexicon->load($topic);
        }

        return $this->lexicon($key, $params, $language);
    }

    public function link(
        int|string $id,
        array|string $params = '',
        string $contextKey = '',
        int|string $scheme = -1,
        array $options = []
    ): string {
        return $this->modx->makeUrl($id, $contextKey, $params, $scheme, $options);
    }

    /**
     * Resolve positive integer IDs and legacy digit-only strings as MODX resources.
     *
     * Other strings pass through unchanged. Null and non-stringable values
     * become an empty string; other scalar and Stringable values are safely
     * stringified without being reinterpreted as resource IDs. Digit-only
     * strings intentionally retain ContentBlocks' legacy handling of `"0"`;
     * typed integers must be positive.
     */
    public function resourceUrl(mixed $value): string
    {
        if (is_int($value)) {
            return $value > 0 ? $this->link($value) : (string) $value;
        }

        if (is_string($value)) {
            return ctype_digit($value) ? $this->link($value) : $value;
        }

        if ($value === null) {
            return '';
        }

        if (is_scalar($value) || $value instanceof \Stringable) {
            return (string) $value;
        }

        return '';
    }

    public function field(mixed $name, mixed $default = null, mixed $resource = null): mixed
    {
        [$name, $default, $resource] = $this->normalizeFieldRequest($name, $default, $resource);
        if ($name === '') {
            return $default;
        }

        $resource = $this->resolveResource($resource);
        if (!$resource instanceof modResource) {
            return $default;
        }

        $name = ltrim($this->modx->parser->realname($name), '*');
        $value = $resource->get($name);
        if ($value !== null) {
            return $value;
        }

        $value = $resource->getTVValue($name);

        return $value ?? $default;
    }

    public function getModx(): modX
    {
        return $this->modx;
    }

    public function getParser(): Twig
    {
        return $this->parser;
    }

    private function processElementTag(string $token, string $name, array $properties): string
    {
        $tag = '[[' . $token . $name;
        if (!empty($properties)) {
            $tag .= '?';
            foreach ($properties as $key => $value) {
                $tag .= ' &' . $key . '=`' . $this->stringifyProperty($value) . '`';
            }
        }
        $tag .= ']]';

        $this->maxIterations ??= (int) $this->modx->getOption('parser_max_iterations', null, 10);
        $this->parser->processElementTags('', $tag, true, true, '[[', ']]', [], $this->maxIterations);

        return $tag;
    }

    private function stringifyProperty(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if ($value === null) {
            return '';
        }

        if (is_scalar($value)) {
            return str_replace('`', '\`', (string) $value);
        }

        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $encoded === false ? '' : str_replace('`', '\`', $encoded);
    }

    private function normalizeFieldRequest(mixed $name, mixed $default, mixed $resource): array
    {
        if (!is_array($name)) {
            return [(string) $name, $default, $resource];
        }

        return [
            (string) ($name['name'] ?? ''),
            $name['default'] ?? $default,
            $name['resource'] ?? $resource,
        ];
    }

    private function resolveResource(mixed $resource): ?modResource
    {
        if ($resource instanceof modResource) {
            return $resource;
        }

        if ($resource === null) {
            return $this->modx->resource instanceof modResource ? $this->modx->resource : null;
        }

        if (is_numeric($resource)) {
            $resource = $this->modx->getObject(modResource::class, (int) $resource);

            return $resource instanceof modResource ? $resource : null;
        }

        return null;
    }
}
