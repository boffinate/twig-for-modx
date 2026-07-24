<?php
declare(strict_types=1);

namespace MODX\Revolution\Tests\Twig;

use Boffinate\Twig\Component\UxComponentSupport;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

/*
 * Components must be renderable from a Twig environment our own PHP creates
 * and controls, with no MODX parser involved — the shape used when a MODX
 * resource's PHP generates pages directly (e.g. collection pages). No modX
 * instance is touched anywhere in this test.
 */
class StandaloneComponentTest extends TestCase
{
    private string $templateDir;

    protected function setUp(): void
    {
        $this->templateDir = sys_get_temp_dir() . '/twig-standalone-' . bin2hex(random_bytes(4));
        mkdir($this->templateDir . '/components', 0777, true);
        file_put_contents(
            $this->templateDir . '/components/Button.html.twig',
            "{% props label, variant = 'primary' %}\n" .
            '<a{{ attributes.defaults({class: \'a-btn a-btn--\' ~ variant}) }}>{{ label }}</a>'
        );
        file_put_contents(
            $this->templateDir . '/page.html.twig',
            '<main><twig:Button :label="cta" variant="secondary" /></main>'
        );
    }

    protected function tearDown(): void
    {
        unlink($this->templateDir . '/components/Button.html.twig');
        unlink($this->templateDir . '/page.html.twig');
        rmdir($this->templateDir . '/components');
        rmdir($this->templateDir);
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
}
