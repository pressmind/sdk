<?php

namespace Pressmind\Tests\Unit\Backend;

use Pressmind\Backend\CommandRegistry;
use Pressmind\Tests\Unit\AbstractTestCase;

class CommandRegistryTest extends AbstractTestCase
{
    public function testGetAllReturnsArray(): void
    {
        $commands = CommandRegistry::getAll();
        $this->assertIsArray($commands);
        $this->assertNotEmpty($commands);
    }

    public function testHasReturnsTrueForExistingCommand(): void
    {
        $this->assertTrue(CommandRegistry::has('fullimport'));
    }

    public function testHasReturnsFalseForUnknownCommand(): void
    {
        $this->assertFalse(CommandRegistry::has('nonexistent_command_xyz'));
    }

    public function testGetReturnsCommandDefinition(): void
    {
        $cmd = CommandRegistry::get('fullimport');
        $this->assertIsArray($cmd);
        $this->assertArrayHasKey('name', $cmd);
        $this->assertArrayHasKey('description', $cmd);
        $this->assertArrayHasKey('arguments', $cmd);
        $this->assertArrayHasKey('danger', $cmd);
        $this->assertSame('fullimport', $cmd['name']);
    }

    public function testGetReturnsNullForUnknownCommand(): void
    {
        $this->assertNull(CommandRegistry::get('nonexistent_command_xyz'));
    }

    public function testBuildArgvReturnsArray(): void
    {
        $argv = CommandRegistry::buildArgv('fullimport');
        $this->assertIsArray($argv);
        $this->assertSame(['fullimport'], $argv);
    }

    public function testBuildArgvWithIds(): void
    {
        $argv = CommandRegistry::buildArgv('import mediaobject', ['ids' => '123,456']);
        $this->assertIsArray($argv);
        $this->assertSame(['import', 'mediaobject', '123,456'], $argv);
    }

    public function testBuildArgvWithFlagArgument(): void
    {
        $argv = CommandRegistry::buildArgv('touristic-orphans', ['--stats-only' => '1']);
        $this->assertIsArray($argv);
        $this->assertContains('--stats-only', $argv);
    }

    public function testBuildArgvReturnsEmptyForUnknownCommand(): void
    {
        $argv = CommandRegistry::buildArgv('nonexistent_command_xyz');
        $this->assertSame([], $argv);
    }

    public function testAllCommandsHaveRequiredKeys(): void
    {
        $requiredKeys = ['name', 'description', 'arguments', 'danger'];
        foreach (CommandRegistry::getAll() as $key => $cmd) {
            foreach ($requiredKeys as $rk) {
                $this->assertArrayHasKey($rk, $cmd, "Command '{$key}' missing key '{$rk}'");
            }
        }
    }

    public function testDangerLevelsAreValid(): void
    {
        $valid = [
            CommandRegistry::DANGER_LOW,
            CommandRegistry::DANGER_MEDIUM,
            CommandRegistry::DANGER_HIGH,
            CommandRegistry::DANGER_CRITICAL,
        ];
        foreach (CommandRegistry::getAll() as $key => $cmd) {
            $this->assertContains($cmd['danger'], $valid, "Command '{$key}' has invalid danger level");
        }
    }
}
