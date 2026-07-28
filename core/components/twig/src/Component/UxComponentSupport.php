<?php
declare(strict_types=1);

namespace Boffinate\Twig\Component;

use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\UX\TwigComponent\ComponentAttributes;
use Symfony\UX\TwigComponent\ComponentFactory;
use Symfony\UX\TwigComponent\ComponentProperties;
use Symfony\UX\TwigComponent\ComponentRenderer;
use Symfony\UX\TwigComponent\ComponentStack;
use Symfony\UX\TwigComponent\ComponentTemplateFinder;
use Symfony\UX\TwigComponent\Twig\ComponentExtension;
use Symfony\UX\TwigComponent\Twig\ComponentLexer;
use Symfony\UX\TwigComponent\Twig\ComponentRuntime;
use Twig\Environment;
use Twig\Runtime\EscaperRuntime;
use Twig\RuntimeLoader\FactoryRuntimeLoader;

/*
 * Hand-wires Symfony UX TwigComponent onto a plain Twig Environment: the
 * services TwigComponentBundle would register in a Symfony app, built by
 * hand so no framework is required. Anonymous (template-only) components
 * are supported; class-backed components would need a real service locator.
 *
 * Because it only needs an Environment, the same call works for the
 * MODX-created environment and for standalone environments (e.g. pages
 * rendered by our own PHP without MODX templating involved).
 */
final class UxComponentSupport
{
    /**
     * The dispatcher wired into each environment's component factory and
     * renderer, retrievable afterwards via dispatcherFor() so listeners
     * (PreMount, PostMount, PreRender, ...) can be attached by code that
     * only ever sees the Environment — e.g. an OnTwigInit hook.
     *
     * @var \WeakMap<Environment, EventDispatcherInterface>|null
     */
    private static ?\WeakMap $dispatchers = null;

    /** Whether ComponentRuntime's constructor takes a ComponentStack — a fact about the installed UX version, resolved once. */
    private static ?bool $runtimeTakesStack = null;

    /**
     * @param string $directory directory prefix, relative to the loader
     *                          roots, where anonymous component templates
     *                          live: `<twig:Button>` resolves to
     *                          `{directory}/Button.html.twig`
     * @param EventDispatcherInterface|null $dispatcher a dispatcher with
     *                          listeners already attached; created fresh
     *                          when omitted
     *
     * @return EventDispatcherInterface the dispatcher wired into this
     *                          environment, so a caller that has just
     *                          registered can attach listeners without going
     *                          back through dispatcherFor()
     */
    public static function register(
        Environment $twig,
        string $directory = 'components',
        ?EventDispatcherInterface $dispatcher = null
    ): EventDispatcherInterface {
        $propertyAccessor = PropertyAccess::createPropertyAccessor();
        $dispatcher ??= new EventDispatcher();
        self::$dispatchers ??= new \WeakMap();
        self::$dispatchers[$twig] = $dispatcher;

        $factory = new ComponentFactory(
            new ComponentTemplateFinder($twig->getLoader(), $directory),
            new ServiceLocator([]),
            $propertyAccessor,
            $dispatcher,
            [],
            [],
            $twig
        );

        $stack = new ComponentStack();
        $renderer = new ComponentRenderer(
            $twig,
            $dispatcher,
            $factory,
            new ComponentProperties($propertyAccessor),
            $stack
        );

        /*
         * UX 3.x added ComponentStack as a third ComponentRuntime constructor
         * argument; 2.x takes two. Supporting both keeps the extra usable on
         * PHP 8.2/8.3 (UX 2.36) and PHP 8.4+ (UX 3.2+) alike.
         */
        $runtimeTakesStack = self::$runtimeTakesStack ??= (new \ReflectionClass(ComponentRuntime::class))
            ->getConstructor()->getNumberOfParameters() >= 3;
        $twig->addRuntimeLoader(new FactoryRuntimeLoader([
            ComponentRuntime::class => static fn (): ComponentRuntime => $runtimeTakesStack
                ? new ComponentRuntime($renderer, new ServiceLocator([]), $stack)
                : new ComponentRuntime($renderer, new ServiceLocator([])),
        ]));
        $twig->addExtension(new ComponentExtension());
        $twig->setLexer(new ComponentLexer($twig));
        $twig->getRuntime(EscaperRuntime::class)->addSafeClass(ComponentAttributes::class, ['html']);

        return $dispatcher;
    }

    /*
     * The dispatcher register() wired into this environment, or null when
     * register() was never called on it.
     */
    public static function dispatcherFor(Environment $twig): ?EventDispatcherInterface
    {
        return (self::$dispatchers !== null && isset(self::$dispatchers[$twig]))
            ? self::$dispatchers[$twig]
            : null;
    }
}
