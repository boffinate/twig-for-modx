<?php
declare(strict_types=1);

namespace MODX\Revolution\Tests\Twig;

require_once __DIR__ . '/ParserTestCase.php';

use xPDO\xPDO;
use xPDO\xPDOContainer;

class TwigContentBlocksTest extends ParserTestCase
{
    private const FAILURE_FLAG = '__twig_contentblocks_parser_unavailable';
    private const PLUGIN_PATH = 'components/twig/elements/plugins/TwigContentBlocks.php';

    protected function usesTwigParser(): bool
    {
        return true;
    }

    public function test_missing_twigparser_service_preserves_template_and_logs_once(): void
    {
        $twig = $this->modx->services->get('twigparser');
        unset($this->modx->services['twigparser']);

        try {
            [$template, $outputs, $log] = $this->captureFailure(function (string $template): array {
                return [
                    $this->executePluginFile(MODX_CORE_PATH . self::PLUGIN_PATH, ['tpl' => $template, 'phs' => ['value' => 'First']]),
                    $this->executePluginFile(MODX_CORE_PATH . self::PLUGIN_PATH, ['tpl' => $template, 'phs' => ['value' => 'Second']]),
                ];
            });
        } finally {
            $this->modx->services->add('twigparser', $twig);
        }

        $this->assertSame([$template, $template], $outputs);
        $this->assertCount(1, $log);
        $this->assertStringContainsString(
            '[TwigContentBlocks] twigparser service unavailable',
            $log[0]
        );
    }

    public function test_throwing_twigparser_service_preserves_template_and_logs_failure(): void
    {
        $services = $this->modx->services;
        $throwingServices = new class() extends xPDOContainer {
            public int $getCalls = 0;

            public function has(string $id): bool
            {
                return $id === 'twigparser';
            }

            public function get(string $id)
            {
                ++$this->getCalls;
                throw new \RuntimeException('twigparser factory exploded');
            }
        };
        $this->modx->services = $throwingServices;

        try {
            [$template, $outputs, $log] = $this->captureFailure(function (string $template): array {
                return [
                    $this->executePluginFile(MODX_CORE_PATH . self::PLUGIN_PATH, ['tpl' => $template, 'phs' => ['value' => 'First']]),
                    $this->executePluginFile(MODX_CORE_PATH . self::PLUGIN_PATH, ['tpl' => $template, 'phs' => ['value' => 'Second']]),
                ];
            });
        } finally {
            $this->modx->services = $services;
        }

        $this->assertSame([$template, $template], $outputs);
        $this->assertSame(1, $throwingServices->getCalls);
        $this->assertCount(1, $log);
        $this->assertStringContainsString('RuntimeException: twigparser factory exploded', $log[0]);
    }

    /** @return array{string, string[], string[]} */
    private function captureFailure(callable $exercise): array
    {
        $previousFailureFlag = $this->modx->getOption(self::FAILURE_FLAG, null, null);
        $this->modx->setOption(self::FAILURE_FLAG, false);

        $log = [];
        $previousLogTarget = $this->modx->setLogTarget([
            'target' => 'ARRAY',
            'options' => ['var' => &$log],
        ]);
        $previousLogLevel = $this->modx->getLogLevel();
        $this->modx->setLogLevel(xPDO::LOG_LEVEL_ERROR);
        $template = '<p>{{ value }}</p>';

        try {
            $outputs = $exercise($template);
        } finally {
            $this->modx->setLogTarget($previousLogTarget);
            $this->modx->setLogLevel($previousLogLevel);
            $this->modx->setOption(self::FAILURE_FLAG, $previousFailureFlag);
        }

        return [$template, $outputs, $log];
    }
}
