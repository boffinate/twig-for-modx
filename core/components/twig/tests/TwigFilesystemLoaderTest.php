<?php
declare(strict_types=1);

namespace MODX\Revolution\Tests\Twig;

require_once __DIR__ . '/ParserTestCase.php';

use Boffinate\Twig\Twig;

class TwigFilesystemLoaderTest extends ParserTestCase
{
    private ?string $templateDir = null;

    protected function usesTwigParser(): bool
    {
        return true;
    }

    /**
     * @after
     */
    public function removeTemplateDir(): void
    {
        if ($this->templateDir !== null && is_dir($this->templateDir)) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($this->templateDir, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($iterator as $item) {
                $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
            }
            rmdir($this->templateDir);
            $this->templateDir = null;
        }
    }

    private function createTemplateDir(array $templates): string
    {
        $dir = sys_get_temp_dir() . '/twig-components-' . bin2hex(random_bytes(4));
        mkdir($dir, 0777, true);
        foreach ($templates as $name => $content) {
            $path = $dir . '/' . $name;
            if (!is_dir(dirname($path))) {
                mkdir(dirname($path), 0777, true);
            }
            file_put_contents($path, $content);
        }
        $this->templateDir = $dir;

        return $dir;
    }

    private function twigParser(): Twig
    {
        $parser = $this->modx->parser;
        $this->assertInstanceOf(Twig::class, $parser);

        return $parser;
    }

    public function test_registered_namespace_path_resolves_includes(): void
    {
        $dir = $this->createTemplateDir([
            'button/button.twig' => '<a class="a-btn">{{ label }}</a>',
        ]);
        $this->twigParser()->registerTemplatePath($dir, 'components');

        $output = $this->twigParser()->renderString(
            "{% include '@components/button/button.twig' with { label: 'Book now' } only %}",
            []
        );

        $this->assertSame('<a class="a-btn">Book now</a>', $output);
    }

    public function test_registered_main_namespace_resolves_bare_paths(): void
    {
        $dir = $this->createTemplateDir([
            'partial.twig' => 'Partial says {{ word }}',
        ]);
        $this->twigParser()->registerTemplatePath($dir);

        $output = $this->twigParser()->renderString(
            "{% include 'partial.twig' with { word: 'hi' } only %}",
            []
        );

        $this->assertSame('Partial says hi', $output);
    }

    public function test_template_paths_system_setting_registers_namespace(): void
    {
        $dir = $this->createTemplateDir([
            'card/card.twig' => '<div class="card">{{ title }}</div>',
        ]);
        $this->modx->setOption('twig.template_paths', json_encode(['components' => $dir]));

        $output = $this->twigParser()->renderString(
            "{% include '@components/card/card.twig' with { title: 'A card' } only %}",
            []
        );

        $this->assertSame('<div class="card">A card</div>', $output);
    }

    public function test_missing_template_path_is_skipped_without_breaking_rendering(): void
    {
        $this->twigParser()->registerTemplatePath(sys_get_temp_dir() . '/twig-does-not-exist-' . bin2hex(random_bytes(4)));

        $this->assertSame('4', $this->twigParser()->renderString('{{ 2 + 2 }}', []));
    }

    public function test_include_resolves_inside_chunk_rendered_through_modx_parser(): void
    {
        $dir = $this->createTemplateDir([
            'button/button.twig' => '<a class="a-btn">{{ label }}</a>',
        ]);
        $this->twigParser()->registerTemplatePath($dir, 'components');
        $this->registerChunk(
            'ComponentChunk',
            "{% include '@components/button/button.twig' with { label: name } only %}"
        );

        $this->assertSame(
            '<a class="a-btn">World</a>',
            $this->processContent('[[$ComponentChunk? &name=`World`]]')
        );
    }

    public function test_extends_layout_from_filesystem(): void
    {
        $dir = $this->createTemplateDir([
            'layout.twig' => "<main>{% block body %}{% endblock %}</main>",
        ]);
        $this->twigParser()->registerTemplatePath($dir);

        $output = $this->twigParser()->renderString(
            "{% extends 'layout.twig' %}{% block body %}Hello{% endblock %}",
            []
        );

        $this->assertSame('<main>Hello</main>', $output);
    }
}
