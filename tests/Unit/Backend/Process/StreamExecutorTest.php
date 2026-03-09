<?php

namespace Pressmind\Tests\Unit\Backend\Process;

use Pressmind\Backend\Process\SSEResponse;
use Pressmind\Backend\Process\StreamExecutor;
use Pressmind\Tests\Unit\AbstractTestCase;

class StreamExecutorTest extends AbstractTestCase
{
    public function testCanBeInstantiated(): void
    {
        $sse = $this->createMock(SSEResponse::class);
        $executor = new StreamExecutor($sse, '/usr/bin/php');
        $this->assertInstanceOf(StreamExecutor::class, $executor);
    }

    public function testFindPhpBinaryReturnsString(): void
    {
        $result = StreamExecutor::findPhpBinary([]);
        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }

    public function testFindPhpBinaryUsesConfigValue(): void
    {
        $config = ['server' => ['php_cli_binary' => '/custom/php']];
        $result = StreamExecutor::findPhpBinary($config);
        $this->assertSame('/custom/php', $result);
    }

    public function testFindPhpBinaryUsesLegacyConfigKey(): void
    {
        $config = ['php_cli_binary' => '/legacy/php'];
        $result = StreamExecutor::findPhpBinary($config);
        $this->assertSame('/legacy/php', $result);
    }

    public function testFindPhpBinaryIgnoresEmptyConfigValue(): void
    {
        $config = ['server' => ['php_cli_binary' => '']];
        $result = StreamExecutor::findPhpBinary($config);
        $this->assertIsString($result);
        $this->assertNotSame('', $result);
    }
}
