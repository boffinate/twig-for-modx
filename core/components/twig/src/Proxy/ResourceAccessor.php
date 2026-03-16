<?php
declare(strict_types=1);

namespace Boffinate\Twig\Proxy;

use MODX\Revolution\modResource;
use MODX\Revolution\modTemplateVar;
use MODX\Revolution\modX;

/**
 * Wraps a modResource to provide unified property access for Twig templates.
 *
 * Built-in resource fields (pagetitle, alias, etc.) and Template Variables
 * are both accessible as properties: {{ resource.pagetitle }}, {{ resource.myTv }}.
 *
 * TV values are processed/rendered by default (same as [[*myTv]]).
 * Use tvRawValue() for the raw stored value.
 */
class ResourceAccessor
{
    /** @var array<string, mixed> Cache of TV lookups (keyed by TV name, stores processed value) */
    private array $tvCache = [];

    /** @var array<string, bool> Tracks which TV names have been looked up (including null results) */
    private array $tvLookedUp = [];

    public function __construct(private modResource $resource)
    {
    }

    public function __get(string $name): mixed
    {
        return $this->resolve($name);
    }

    public function __isset(string $name): bool
    {
        return $this->resolve($name) !== null;
    }

    /**
     * Returns the raw stored TV value without output rendering.
     *
     * Use this when you need the value as stored in the database,
     * before MODX applies bindings, output properties, or URL transforms.
     */
    public function tvRawValue(string $name): mixed
    {
        $modx = $this->resource->xpdo;
        $tv = $modx instanceof modX
            ? $modx->getParser()->getElement(modTemplateVar::class, $name)
            : $modx->getObject(modTemplateVar::class, ['name' => $name]);

        if (!$tv instanceof modTemplateVar) {
            return null;
        }

        return $tv->getValue($this->resource->get('id'));
    }

    public function __call(string $name, array $arguments): mixed
    {
        return $this->resource->$name(...$arguments);
    }

    /**
     * Resolve a name to a resource field or TV value, caching the result
     * so that Twig's __isset + __get pair doesn't double-query.
     */
    private function resolve(string $name): mixed
    {
        if (array_key_exists($name, $this->tvCache)) {
            return $this->tvCache[$name];
        }

        $value = $this->resource->get($name);
        if ($value !== null) {
            $this->tvCache[$name] = $value;
            return $value;
        }

        if (isset($this->tvLookedUp[$name])) {
            return null;
        }

        $this->tvLookedUp[$name] = true;
        $value = $this->resource->getTVValue($name);
        $this->tvCache[$name] = $value;

        return $value;
    }
}
