<?php

namespace Pressmind\Tests\Unit\CLI;

use PHPUnit\Framework\TestCase;
use Pressmind\CLI\ImportCommand;

/**
 * Unit tests for ImportCommand: argument parsing, callback, and help text.
 * DB-dependent tests (run() with lock handling) are in Integration/CLI/.
 */
class ImportCommandTest extends TestCase
{
    public function testCanBeInstantiated(): void
    {
        $cmd = new ImportCommand();
        $this->assertInstanceOf(ImportCommand::class, $cmd);
    }

    public function testSetOnAfterImportCallback(): void
    {
        $cmd = new ImportCommand();
        $called = false;
        $cmd->setOnAfterImportCallback(function (array $ids) use (&$called) {
            $called = true;
        });
        $this->assertFalse($called);
    }

    public function testParseArgumentsShortConfigOption(): void
    {
        $cmd = new ImportCommand();
        $reflection = new \ReflectionMethod($cmd, 'parseArguments');
        $reflection->setAccessible(true);

        $reflection->invoke($cmd, ['import.php', '-c=pm-config-test.php', 'help']);

        $optionsRef = new \ReflectionProperty($cmd, 'options');
        $optionsRef->setAccessible(true);
        $options = $optionsRef->getValue($cmd);

        $this->assertSame('pm-config-test.php', $options['c'] ?? null);
    }

    public function testParseArgumentsLongConfigOption(): void
    {
        $cmd = new ImportCommand();
        $reflection = new \ReflectionMethod($cmd, 'parseArguments');
        $reflection->setAccessible(true);

        $reflection->invoke($cmd, ['import.php', '--config=pm-config-alt.php', 'help']);

        $optionsRef = new \ReflectionProperty($cmd, 'options');
        $optionsRef->setAccessible(true);
        $options = $optionsRef->getValue($cmd);

        $this->assertSame('pm-config-alt.php', $options['config'] ?? null);
    }

    public function testPrintHelpContainsAllSubcommands(): void
    {
        $cmd = new ImportCommand();
        $reflection = new \ReflectionMethod($cmd, 'printHelp');
        $reflection->setAccessible(true);

        ob_start();
        $reflection->invoke($cmd);
        $output = ob_get_clean();

        $expected = [
            'fullimport', 'fullimport_touristic', 'mediaobject', 'touristic',
            'itinerary', 'objecttypes', 'destroy', 'depublish', 'offer',
            'calendar', 'remove_orphans', 'update_tags', 'postimport',
            'categories', 'unlock', 'create_translations', 'reset_insurances',
            'powerfilter',
        ];

        foreach ($expected as $sub) {
            $this->assertStringContainsString($sub, $output, "Help missing subcommand: $sub");
        }
    }

    public function testParseArgumentsMultipleShortOptions(): void
    {
        $cmd = new ImportCommand();
        $reflection = new \ReflectionMethod($cmd, 'parseArguments');
        $reflection->setAccessible(true);

        $reflection->invoke($cmd, ['import.php', '-c=config.php', '-n', 'fullimport']);

        $optionsRef = new \ReflectionProperty($cmd, 'options');
        $optionsRef->setAccessible(true);
        $options = $optionsRef->getValue($cmd);

        $this->assertSame('config.php', $options['c'] ?? null);
        $this->assertTrue($options['n'] ?? false);
    }

    public function testParseArgumentsPositionalIds(): void
    {
        $cmd = new ImportCommand();
        $reflection = new \ReflectionMethod($cmd, 'parseArguments');
        $reflection->setAccessible(true);

        $reflection->invoke($cmd, ['import.php', 'mediaobject', '12345,67890']);

        $argsRef = new \ReflectionProperty($cmd, 'arguments');
        $argsRef->setAccessible(true);
        $args = $argsRef->getValue($cmd);

        $this->assertSame('mediaobject', $args[0]);
        $this->assertSame('12345,67890', $args[1]);
    }

    public function testInvokeAfterImportCallbackWithEmptyIds(): void
    {
        $cmd = new ImportCommand();
        $calledWith = null;
        $cmd->setOnAfterImportCallback(function (array $ids) use (&$calledWith) {
            $calledWith = $ids;
        });

        $reflection = new \ReflectionMethod($cmd, 'invokeAfterImportCallback');
        $reflection->setAccessible(true);

        $reflection->invoke($cmd, []);
        $this->assertNull($calledWith);

        $reflection->invoke($cmd, [123, 456]);
        $this->assertSame([123, 456], $calledWith);
    }
}
