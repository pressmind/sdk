<?php

namespace Pressmind\Tests\Unit\Log;

use Pressmind\Log\Writer;
use Pressmind\Registry;
use Pressmind\Tests\Unit\AbstractTestCase;

/**
 * Unit tests for Pressmind\Log\Writer.
 * Tests screen, file, and both output modes. Database logging is skipped (requires DB table).
 */
class WriterTest extends AbstractTestCase
{
    /** @var string */
    private $tempLogDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempLogDir = sys_get_temp_dir() . '/pm_log_test_' . uniqid();
        mkdir($this->tempLogDir, 0755, true);
    }

    protected function tearDown(): void
    {
        if ($this->tempLogDir !== null && is_dir($this->tempLogDir)) {
            array_map('unlink', glob($this->tempLogDir . '/*') ?: []);
            @rmdir($this->tempLogDir);
        }
        parent::tearDown();
    }

    private function setLoggingConfig(array $overrides = []): void
    {
        $defaults = [
            'logging' => [
                'mode' => 'ALL',
                'storage' => 'filesystem',
                'log_file_path' => $this->tempLogDir,
            ],
        ];
        $config = $this->createMockConfig(array_replace_recursive($defaults, $overrides));
        Registry::getInstance()->add('config', $config);
    }

    public function testWriteScreenReturnsLogText(): void
    {
        $this->setLoggingConfig();
        ob_start();
        $result = Writer::write('Hello screen', Writer::OUTPUT_SCREEN);
        ob_end_clean();
        $this->assertSame('Hello screen', $result);
    }

    public function testWriteScreenEchoesInCli(): void
    {
        $this->setLoggingConfig();
        ob_start();
        Writer::write('CLI output test', Writer::OUTPUT_SCREEN);
        $output = ob_get_clean();
        $this->assertStringContainsString('CLI output test', $output);
    }

    public function testWriteFileCreatesLogFile(): void
    {
        $this->setLoggingConfig();
        Writer::write('file message', Writer::OUTPUT_FILE, 'testlog');
        $logFile = $this->tempLogDir . DIRECTORY_SEPARATOR . 'testlog.log';
        $this->assertFileExists($logFile);
        $content = file_get_contents($logFile);
        $this->assertStringContainsString('file message', $content);
    }

    public function testWriteFileAppendsTimestamp(): void
    {
        $this->setLoggingConfig();
        Writer::write('timed entry', Writer::OUTPUT_FILE, 'timed');
        $content = file_get_contents($this->tempLogDir . DIRECTORY_SEPARATOR . 'timed.log');
        $this->assertMatchesRegularExpression('/\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\]/', $content);
    }

    public function testWriteFileNonInfoTypeAppendsSuffix(): void
    {
        $this->setLoggingConfig();
        Writer::write('error msg', Writer::OUTPUT_FILE, 'app', Writer::TYPE_ERROR);
        $logFile = $this->tempLogDir . DIRECTORY_SEPARATOR . 'app_error.log';
        $this->assertFileExists($logFile);
        $this->assertStringContainsString('error msg', file_get_contents($logFile));
    }

    public function testWriteFileWithWarningType(): void
    {
        $this->setLoggingConfig();
        Writer::write('warn msg', Writer::OUTPUT_FILE, 'app', Writer::TYPE_WARNING);
        $logFile = $this->tempLogDir . DIRECTORY_SEPARATOR . 'app_warning.log';
        $this->assertFileExists($logFile);
    }

    public function testWriteFileWithDebugType(): void
    {
        $this->setLoggingConfig();
        Writer::write('debug msg', Writer::OUTPUT_FILE, 'app', Writer::TYPE_DEBUG);
        $logFile = $this->tempLogDir . DIRECTORY_SEPARATOR . 'app_debug.log';
        $this->assertFileExists($logFile);
    }

    public function testWriteFileModeSpecificMatchesType(): void
    {
        $this->setLoggingConfig(['logging' => ['mode' => 'ERROR']]);
        Writer::write('error specific', Writer::OUTPUT_FILE, 'specific', Writer::TYPE_ERROR);
        $logFile = $this->tempLogDir . DIRECTORY_SEPARATOR . 'specific_error.log';
        $this->assertFileExists($logFile);
    }

    public function testWriteFileModeSpecificSkipsNonMatchingType(): void
    {
        $this->setLoggingConfig(['logging' => ['mode' => 'ERROR']]);
        Writer::write('should skip', Writer::OUTPUT_FILE, 'skipped', Writer::TYPE_INFO);
        $logFile = $this->tempLogDir . DIRECTORY_SEPARATOR . 'skipped.log';
        $this->assertFileDoesNotExist($logFile);
    }

    public function testWriteBothOutputsToScreenAndFile(): void
    {
        $this->setLoggingConfig();
        ob_start();
        $result = Writer::write('both message', Writer::OUTPUT_BOTH, 'bothlog');
        $screenOutput = ob_get_clean();
        $this->assertStringContainsString('both message', $screenOutput);
        $logFile = $this->tempLogDir . DIRECTORY_SEPARATOR . 'bothlog.log';
        $this->assertFileExists($logFile);
        $this->assertStringContainsString('both message', file_get_contents($logFile));
        $this->assertStringContainsString('both message', $result);
    }

    public function testWriteFileUsesCustomLogPath(): void
    {
        $customDir = $this->tempLogDir . DIRECTORY_SEPARATOR . 'custom';
        mkdir($customDir, 0755, true);
        $config = $this->createMockConfig([
            'logging' => [
                'mode' => 'ALL',
                'storage' => 'filesystem',
                'log_file_path' => $customDir,
            ],
        ]);
        Registry::getInstance()->add('config', $config);
        Writer::write('custom path msg', Writer::OUTPUT_FILE, 'custom');
        $this->assertFileExists($customDir . DIRECTORY_SEPARATOR . 'custom.log');
        $this->assertStringContainsString('custom path msg', file_get_contents($customDir . DIRECTORY_SEPARATOR . 'custom.log'));
        @unlink($customDir . DIRECTORY_SEPARATOR . 'custom.log');
        @rmdir($customDir);
    }

    public function testWriteFileAppends(): void
    {
        $this->setLoggingConfig();
        Writer::write('first', Writer::OUTPUT_FILE, 'append');
        Writer::write('second', Writer::OUTPUT_FILE, 'append');
        $content = file_get_contents($this->tempLogDir . DIRECTORY_SEPARATOR . 'append.log');
        $this->assertStringContainsString('first', $content);
        $this->assertStringContainsString('second', $content);
    }

    public function testWriteFileWithArrayMessage(): void
    {
        $this->setLoggingConfig();
        Writer::write(['key' => 'value'], Writer::OUTPUT_FILE, 'arraylog');
        $content = file_get_contents($this->tempLogDir . DIRECTORY_SEPARATOR . 'arraylog.log');
        $this->assertStringContainsString('key', $content);
        $this->assertStringContainsString('value', $content);
    }

    public function testGetLogFilePathUsesConfigPath(): void
    {
        $this->setLoggingConfig();
        $path = Writer::getLogFilePath();
        $this->assertSame($this->tempLogDir, $path);
    }

    public function testGetLogFilePathDefaultsToApplicationPathLogs(): void
    {
        $config = $this->createMockConfig([
            'logging' => ['mode' => 'ALL', 'storage' => 'filesystem'],
        ]);
        Registry::getInstance()->add('config', $config);
        $path = Writer::getLogFilePath();
        $this->assertSame(APPLICATION_PATH . DIRECTORY_SEPARATOR . 'logs', $path);
    }

    public function testGetLogFilePathReplacesApplicationPathConstant(): void
    {
        $config = $this->createMockConfig([
            'logging' => [
                'mode' => 'ALL',
                'storage' => 'filesystem',
                'log_file_path' => 'APPLICATION_PATH/custom_logs',
            ],
        ]);
        Registry::getInstance()->add('config', $config);
        $path = Writer::getLogFilePath();
        $this->assertSame(APPLICATION_PATH . '/custom_logs', $path);
    }

    public function testGetTraceReturnsArray(): void
    {
        $trace = Writer::getTrace();
        $this->assertIsArray($trace);
        $this->assertNotEmpty($trace);
    }

    public function testGetTraceEntriesContainFileInfo(): void
    {
        $trace = Writer::getTrace();
        foreach ($trace as $entry) {
            $this->assertIsString($entry);
            $this->assertStringContainsString('()', $entry);
        }
    }

    public function testCleanupReturnsZeroWithoutDb(): void
    {
        $this->setLoggingConfig();
        $mockDb = $this->createMock(\Pressmind\DB\Adapter\AdapterInterface::class);
        $mockDb->method('delete')->willThrowException(new \Exception('no table'));
        Registry::getInstance()->add('db', $mockDb);
        $result = Writer::cleanup();
        $this->assertSame(0, $result);
    }

    public function testWriteWithLoggingCategoriesFilter(): void
    {
        $config = $this->createMockConfig([
            'logging' => [
                'mode' => 'ALL',
                'storage' => 'filesystem',
                'log_file_path' => $this->tempLogDir,
                'categories' => ['import', 'sync'],
            ],
        ]);
        Registry::getInstance()->add('config', $config);
        Writer::write('categorized', Writer::OUTPUT_FILE, 'import');
        $this->assertFileExists($this->tempLogDir . DIRECTORY_SEPARATOR . 'import.log');
    }

    public function testWriteFileFatalType(): void
    {
        $this->setLoggingConfig();
        Writer::write('fatal msg', Writer::OUTPUT_FILE, 'app', Writer::TYPE_FATAL);
        $logFile = $this->tempLogDir . DIRECTORY_SEPARATOR . 'app_fatal.log';
        $this->assertFileExists($logFile);
        $this->assertStringContainsString('fatal msg', file_get_contents($logFile));
    }

    public function testWriteFileModeAsArray(): void
    {
        $config = $this->createMockConfig([
            'logging' => [
                'mode' => ['ERROR', 'WARNING'],
                'storage' => 'filesystem',
                'log_file_path' => $this->tempLogDir,
            ],
        ]);
        Registry::getInstance()->add('config', $config);
        Writer::write('only screen', Writer::OUTPUT_FILE, 'filtered', Writer::TYPE_INFO);
        $logFile = $this->tempLogDir . DIRECTORY_SEPARATOR . 'filtered.log';
        $this->assertFileDoesNotExist($logFile);
    }

    public function testOutputConstants(): void
    {
        $this->assertSame('screen', Writer::OUTPUT_SCREEN);
        $this->assertSame('file', Writer::OUTPUT_FILE);
        $this->assertSame('both', Writer::OUTPUT_BOTH);
    }

    public function testTypeConstants(): void
    {
        $this->assertSame('DEBUG', Writer::TYPE_DEBUG);
        $this->assertSame('INFO', Writer::TYPE_INFO);
        $this->assertSame('WARNING', Writer::TYPE_WARNING);
        $this->assertSame('ERROR', Writer::TYPE_ERROR);
        $this->assertSame('FATAL', Writer::TYPE_FATAL);
    }
}
