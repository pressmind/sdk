<?php

namespace Pressmind\Tests\Unit\CLI;

use PHPUnit\Framework\TestCase;
use Pressmind\CLI\FileStorageImportCommand;

class FileStorageImportCommandTest extends TestCase
{
    public function testCanBeInstantiated(): void
    {
        $cmd = new FileStorageImportCommand();
        $this->assertInstanceOf(FileStorageImportCommand::class, $cmd);
    }

    public function testParseArgumentsForceAndFolderAndNoDownload(): void
    {
        $cmd = new FileStorageImportCommand();
        $reflection = new \ReflectionMethod($cmd, 'parseArguments');
        $reflection->setAccessible(true);
        $reflection->invoke($cmd, ['file_storage_import.php', '--force', '--folder=fixture-folder-001', '--no-download']);

        $optionsRef = new \ReflectionProperty($cmd, 'options');
        $optionsRef->setAccessible(true);
        $options = $optionsRef->getValue($cmd);

        $this->assertTrue($options['force'] ?? false);
        $this->assertSame('fixture-folder-001', $options['folder'] ?? null);
        $this->assertTrue($options['no-download'] ?? false);
    }
}
