<?php
declare(strict_types=1);

namespace MODX\Revolution\Tests\Twig;

/*
 * Writes Twig templates to a throwaway directory and removes them again
 * after the test, whatever the test added to it.
 *
 * Deliberately free of MODX: StandaloneComponentTest builds its own Twig
 * environment with no modX instance anywhere, and uses this too.
 */
trait WritesTemplateFiles
{
    /** @var string[] directories created by writeTemplateFiles() */
    private array $writtenTemplateDirs = [];

    /**
     * @param array<string, string> $templates relative path => file contents;
     *                                         intermediate directories are created
     *
     * @return string the directory the templates were written to
     */
    protected function writeTemplateFiles(array $templates, string $prefix = 'twig-templates-'): string
    {
        $dir = sys_get_temp_dir() . '/' . $prefix . bin2hex(random_bytes(4));
        mkdir($dir, 0777, true);
        $this->writtenTemplateDirs[] = $dir;

        foreach ($templates as $name => $content) {
            $path = $dir . '/' . $name;
            if (!is_dir(dirname($path))) {
                mkdir(dirname($path), 0777, true);
            }
            file_put_contents($path, $content);
        }

        return $dir;
    }

    /**
     * @after
     */
    public function removeWrittenTemplateDirs(): void
    {
        foreach ($this->writtenTemplateDirs as $dir) {
            if (!is_dir($dir)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($iterator as $item) {
                $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
            }
            rmdir($dir);
        }

        $this->writtenTemplateDirs = [];
    }
}
