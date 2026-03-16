<?php
declare(strict_types=1);

namespace Boffinate\Twig\Extension;

use Boffinate\Twig\Twig;
use Symfony\Component\VarDumper\Caster\CutStub;
use Symfony\Component\VarDumper\Cloner\Stub;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\HtmlDumper;
use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\Template;
use Twig\TemplateWrapper;
use Twig\TwigFunction;

/**
 * Replaces Twig's built-in DebugExtension with a version that excludes
 * globals (modx, resource, placeholders) from context dump()
 * calls so that {{ dump() }} and {{ dump(_context) }} answer "what variables
 * does this template have?" rather than listing things that are always available.
 *
 * Uses Symfony VarDumper HtmlDumper when available, rendered inside an iframe
 * srcdoc to isolate the JS/CSS from Twig, Fenom, and MODX tag parsing.
 *
 * Registers custom casters for modX and xPDO so that useful properties
 * (config, resource, request, response, etc.) are fully expandable while
 * framework internals (pdo, driver, classMap, etc.) appear as collapsed stubs.
 */
final class ModxDebugExtension extends AbstractExtension
{
    /** @see Twig::GLOBAL_KEYS */
    private const GLOBAL_KEYS = Twig::GLOBAL_KEYS;
    private const MAX_DUMP_SIZE = 2_097_152; // 2MB

    /** Properties on modX (and inherited xPDO) that template authors will find useful — fully expandable. */
    private const MODX_EXPANDABLE = [
        // modX
        'config', 'context', 'resource', 'request', 'response',
        'user', 'cultureKey', 'resourceIdentifier', 'resourceMethod',
        'placeholders',
        'version', 'site_id', 'uuid', 'resourceGenerated',
        // xPDO
        'package', 'startTime', 'executedQueries', 'queryTime',
    ];

    /** Properties on xPDO that are useful — fully expandable. */
    private const XPDO_EXPANDABLE = [
        'config', 'package', 'startTime', 'executedQueries', 'queryTime',
    ];

    public function getFunctions(): array
    {
        return [
            new TwigFunction('dump', [self::class, 'dump'], [
                'is_safe' => ['html'],
                'needs_context' => true,
                'needs_environment' => true,
                'is_variadic' => true,
            ]),
        ];
    }

    /**
     * @internal
     */
    public static function dump(Environment $env, array $context, mixed ...$vars): ?string
    {
        if (!$env->isDebug()) {
            return null;
        }

        // No args: dump filtered context (template-specific variables only)
        if (!$vars) {
            $vars = [self::filterContext($context)];
        } else {
            // If _context is passed explicitly, apply the same filtering
            $vars = array_map(fn ($v) => $v === $context ? self::filterContext($context) : $v, $vars);
        }

        if (class_exists(VarCloner::class)) {
            $output = self::dumpWithVarDumper(...$vars);
        } else {
            ob_start();
            var_dump(...$vars);
            $output = '<pre>' . htmlspecialchars(ob_get_clean()) . '</pre>';
        }

        if (strlen($output) > self::MAX_DUMP_SIZE) {
            return '<pre>[Twig dump] Output truncated at '
                . number_format(self::MAX_DUMP_SIZE / 1024) . ' KB. '
                . 'Dump specific variables instead of the full context.</pre>';
        }

        return $output;
    }

    private static function filterContext(array $context): array
    {
        $filtered = [];
        foreach ($context as $key => $value) {
            if ($value instanceof Template || $value instanceof TemplateWrapper) {
                continue;
            }
            if (in_array($key, self::GLOBAL_KEYS, true)) {
                continue;
            }
            $filtered[$key] = $value;
        }
        return $filtered;
    }

    private static function dumpWithVarDumper(mixed ...$vars): string
    {
        $cloner = new VarCloner();
        $cloner->setMaxItems(2000);
        $cloner->setMaxString(100_000);
        $cloner->addCasters(self::getCasters());
        $dumper = new HtmlDumper();
        $output = '';

        foreach ($vars as $var) {
            $output .= $dumper->dump($cloner->cloneVar($var), true);
        }

        // Render inside an iframe srcdoc to completely isolate VarDumper's
        // JS/CSS from Fenom, Twig, and MODX tag parsing. Entity-encode { }
        // so Fenom doesn't try to parse them in the attribute value.
        // Inject a ResizeObserver so the iframe grows/shrinks as dump nodes
        // are expanded or collapsed.
        $resizeScript = '<script>new ResizeObserver(function(){var f=frameElement;f.style.height="0";f.style.height=document.documentElement.scrollHeight+"px"}).observe(document.body)</script>';
        $output .= $resizeScript;

        $encoded = htmlspecialchars($output, ENT_QUOTES, 'UTF-8');
        $encoded = str_replace(['{', '}'], ['&#123;', '&#125;'], $encoded);

        return '<iframe srcdoc="' . $encoded . '" '
            . 'style="width:100%;min-height:60px;border:1px solid #888;background:#18171B;">'
            . '</iframe>';
    }

    private static function getCasters(): array
    {
        return [
            'MODX\Revolution\modX' => [self::class, 'castModx'],
            'xPDO\xPDO' => [self::class, 'castXpdo'],
        ];
    }

    /**
     * Cast modX: useful properties are fully expandable, everything else
     * is shown as a non-expandable stub (type + count).
     * Merges both modX and xPDO expandable lists since modX extends xPDO
     * and VarDumper passes all properties to each caster.
     *
     * @internal
     */
    public static function castModx(object $obj, array $array, Stub $stub, bool $isNested): array
    {
        return self::applyExpandableFilter($array, self::MODX_EXPANDABLE);
    }

    /**
     * Cast xPDO: show config and stats expandable, collapse ORM internals.
     * Skips modX instances (handled by castModx which merges both lists).
     *
     * @internal
     */
    public static function castXpdo(object $obj, array $array, Stub $stub, bool $isNested): array
    {
        if ($obj instanceof \MODX\Revolution\modX) {
            return $array;
        }
        return self::applyExpandableFilter($array, self::XPDO_EXPANDABLE);
    }

    /**
     * Keep all properties but replace non-expandable values with CutStub.
     * Scalars and nulls are always shown as-is (they have no children to expand).
     * Groups expandable properties first, then collapsed stubs, both sorted alphabetically.
     */
    private static function applyExpandableFilter(array $array, array $expandable): array
    {
        $expanded = [];
        $collapsed = [];

        foreach ($array as $key => $value) {
            $cleanKey = preg_replace('/^\0[^\\0]*\0/', '', $key);

            if (in_array($cleanKey, $expandable, true)
                || is_scalar($value)
                || $value === null) {
                $expanded[$key] = $value;
            } else {
                $collapsed[$key] = new CutStub($value);
            }
        }

        ksort($expanded);
        ksort($collapsed);

        return $expanded + $collapsed;
    }
}
