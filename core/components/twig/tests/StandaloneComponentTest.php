<?php
declare(strict_types=1);

namespace MODX\Revolution\Tests\Twig;

use Boffinate\Twig\Component\UxComponentSupport;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

/*
 * No MODX involved, so nothing else here loads the addon's dependencies:
 * pull them in directly, or this file only passes when a MODX-based test
 * happens to run first.
 */
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/WritesTemplateFiles.php';

/*
 * Components must be renderable from a Twig environment our own PHP creates
 * and controls, with no MODX parser involved — the shape used when a MODX
 * resource's PHP generates pages directly (e.g. collection pages). No modX
 * instance is touched anywhere in this test.
 */
class StandaloneComponentTest extends TestCase
{
    use WritesTemplateFiles;

    private string $templateDir;

    protected function setUp(): void
    {
        $this->templateDir = $this->writeTemplateFiles([
            'components/Button.html.twig' => "{% props label, variant = 'primary' %}\n"
                . '<a{{ attributes.defaults({class: \'a-btn a-btn--\' ~ variant}) }}>{{ label }}</a>',
            'page.html.twig' => '<main><twig:Button :label="cta" variant="secondary" /></main>',
        ], 'twig-standalone-');
    }

    private function createEnvironment(): Environment
    {
        $twig = new Environment(new FilesystemLoader([$this->templateDir]), [
            'autoescape' => 'html',
        ]);
        UxComponentSupport::register($twig);

        return $twig;
    }

    public function test_component_renders_in_plain_twig_environment(): void
    {
        $output = $this->createEnvironment()->render('page.html.twig', ['cta' => 'Visit us']);

        $this->assertSame(
            '<main><a class="a-btn a-btn--secondary">Visit us</a></main>',
            trim($output)
        );
    }

    public function test_component_function_and_autoescape_in_plain_environment(): void
    {
        $twig = $this->createEnvironment();
        $template = $twig->createTemplate("{{ component('Button', { label: evil }) }}");

        $output = $template->render(['evil' => '<script>x</script>']);

        $this->assertStringNotContainsString('<script>', $output);
        $this->assertStringContainsString('&lt;script&gt;', $output);
    }

    /*
     * Hooks (e.g. MODX OnTwigInit handlers) only ever see the Environment,
     * so the dispatcher register() wired in must be retrievable from it —
     * that is what makes post-registration PreMount listeners possible.
     */
    public function test_listeners_added_via_dispatcher_for_receive_pre_mount(): void
    {
        $twig = $this->createEnvironment();
        $seen = [];

        $dispatcher = UxComponentSupport::dispatcherFor($twig);
        $this->assertNotNull($dispatcher);
        $dispatcher->addListener(
            \Symfony\UX\TwigComponent\Event\PreMountEvent::class,
            function (\Symfony\UX\TwigComponent\Event\PreMountEvent $event) use (&$seen): void {
                $seen[] = [$event->getMetadata()?->getName(), $event->getData()];
            }
        );

        $twig->render('page.html.twig', ['cta' => 'Visit us']);

        $this->assertSame('Button', $seen[0][0]);
        $this->assertSame(['label' => 'Visit us', 'variant' => 'secondary'], $seen[0][1]);
    }

    public function test_register_accepts_a_caller_supplied_dispatcher(): void
    {
        $twig = new Environment(new FilesystemLoader([$this->templateDir]), [
            'autoescape' => 'html',
        ]);
        $dispatcher = new \Symfony\Component\EventDispatcher\EventDispatcher();
        UxComponentSupport::register($twig, 'components', $dispatcher);

        $this->assertSame($dispatcher, UxComponentSupport::dispatcherFor($twig));
    }

    public function test_dispatcher_for_is_null_on_an_unregistered_environment(): void
    {
        $bare = new Environment(new FilesystemLoader([$this->templateDir]));

        $this->assertNull(UxComponentSupport::dispatcherFor($bare));
    }
}
