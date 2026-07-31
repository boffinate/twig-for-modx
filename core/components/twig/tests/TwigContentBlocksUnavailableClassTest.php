<?php
declare(strict_types=1);

namespace MODX\Revolution\Tests\Twig;

use PHPUnit\Framework\TestCase;

class TwigContentBlocksUnavailableClassTest extends TestCase
{
    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_unavailable_twig_class_preserves_template_and_logs_failure(): void
    {
        $this->assertTrue(class_exists(\xPDO\xPDO::class));
        $this->assertFalse(class_exists(\Boffinate\Twig\Twig::class, false));

        $autoloaders = spl_autoload_functions() ?: [];
        foreach ($autoloaders as $autoloader) {
            spl_autoload_unregister($autoloader);
        }

        $modx = new class() {
            public object $event;
            /** @var string[] */
            public array $log = [];
            /** @var array<string, mixed> */
            private array $options = [];

            public function __construct()
            {
                $this->event = (object) ['_output' => null];
            }

            public function getOption(string $key, mixed $options = null, mixed $default = null): mixed
            {
                return $this->options[$key] ?? $default;
            }

            public function setOption(string $key, mixed $value): void
            {
                $this->options[$key] = $value;
            }

            public function log(int $level, string $message): void
            {
                $this->log[] = $message;
            }
        };

        $tpl = '<p>{{ value }}</p>';
        $phs = ['value' => 'Ignored'];

        try {
            $returned = require __DIR__ . '/../elements/plugins/TwigContentBlocks.php';
        } finally {
            foreach ($autoloaders as $autoloader) {
                spl_autoload_register($autoloader);
            }
        }

        $this->assertNull($returned);
        $this->assertSame($tpl, $modx->event->_output);
        $this->assertCount(1, $modx->log);
        $this->assertStringContainsString('Boffinate\\Twig\\Twig is not autoloadable', $modx->log[0]);
    }
}
