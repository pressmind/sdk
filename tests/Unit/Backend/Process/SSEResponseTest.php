<?php

namespace Pressmind\Tests\Unit\Backend\Process;

use Pressmind\Backend\Process\SSEResponse;
use Pressmind\Tests\Unit\AbstractTestCase;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class SSEResponseTest extends AbstractTestCase
{
    private int $obLevelBefore;

    protected function setUp(): void
    {
        $this->obLevelBefore = ob_get_level();
        parent::setUp();
    }

    protected function tearDown(): void
    {
        while (ob_get_level() > $this->obLevelBefore) {
            ob_end_clean();
        }
        while (ob_get_level() < $this->obLevelBefore) {
            ob_start();
        }
        parent::tearDown();
    }

    public function testCanBeInstantiated(): void
    {
        $sse = new SSEResponse();
        $this->assertInstanceOf(SSEResponse::class, $sse);
    }

    /**
     * Capture SSE output: SSEResponse constructor clears all buffers, so we
     * start our capture buffers AFTER construction. Outer buffer catches what
     * ob_flush() sends from the inner buffer.
     */
    private function captureSSEOutput(callable $fn): string
    {
        $sse = new SSEResponse();
        ob_start();
        ob_start();
        $fn($sse);
        $inner = ob_get_clean() ?: '';
        $outer = ob_get_clean() ?: '';
        return $outer . $inner;
    }

    public function testSendOutputsSSEFormat(): void
    {
        $output = $this->captureSSEOutput(function (SSEResponse $sse) {
            $sse->send(['msg' => 'hello']);
        });

        $this->assertStringContainsString('data:', $output);
        $this->assertStringContainsString('"msg"', $output);
        $this->assertStringContainsString('"hello"', $output);
    }

    public function testSendWithEventOutputsEventLine(): void
    {
        $output = $this->captureSSEOutput(function (SSEResponse $sse) {
            $sse->send(['msg' => 'test'], 'status');
        });

        $this->assertStringContainsString('event: status', $output);
        $this->assertStringContainsString('data:', $output);
    }

    public function testSendStringPayload(): void
    {
        $output = $this->captureSSEOutput(function (SSEResponse $sse) {
            $sse->send('plain text');
        });

        $this->assertStringContainsString('data: plain text', $output);
    }

    public function testKeepaliveOutputsComment(): void
    {
        $output = $this->captureSSEOutput(function (SSEResponse $sse) {
            $sse->keepalive();
        });

        $this->assertStringContainsString(': keepalive', $output);
    }

    public function testSendPaddingOutputsSpaces(): void
    {
        $output = $this->captureSSEOutput(function (SSEResponse $sse) {
            $sse->sendPadding();
        });

        $this->assertGreaterThanOrEqual(4096, strlen($output));
    }
}
