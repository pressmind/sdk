<?php

namespace Pressmind\Tests\Unit\CLI;

use PHPUnit\Framework\TestCase;
use Pressmind\CLI\TouristicOrphansCommand;

/**
 * Unit tests for TouristicOrphansCommand option parsing.
 */
class TouristicOrphansCommandTest extends TestCase
{
    public function testCanBeInstantiated(): void
    {
        $cmd = new TouristicOrphansCommand();
        $this->assertInstanceOf(TouristicOrphansCommand::class, $cmd);
    }

    public function testObjectTypesOptionIsParsed(): void
    {
        $cmd = new TouristicOrphansCommand();
        $reflection = new \ReflectionMethod($cmd, 'parseArguments');
        $reflection->setAccessible(true);

        $reflection->invoke($cmd, ['touristic-orphans', '--object-types=1212,1214']);

        $optionsRef = new \ReflectionProperty($cmd, 'options');
        $optionsRef->setAccessible(true);
        $options = $optionsRef->getValue($cmd);

        $this->assertSame('1212,1214', $options['object-types'] ?? null);
    }

    public function testVisibilityOptionIsParsed(): void
    {
        $cmd = new TouristicOrphansCommand();
        $reflection = new \ReflectionMethod($cmd, 'parseArguments');
        $reflection->setAccessible(true);

        $reflection->invoke($cmd, ['touristic-orphans', '--visibility=40']);

        $optionsRef = new \ReflectionProperty($cmd, 'options');
        $optionsRef->setAccessible(true);
        $options = $optionsRef->getValue($cmd);

        $this->assertSame('40', $options['visibility'] ?? null);
    }

    public function testDetailsOptionIsParsed(): void
    {
        $cmd = new TouristicOrphansCommand();
        $reflection = new \ReflectionMethod($cmd, 'parseArguments');
        $reflection->setAccessible(true);

        $reflection->invoke($cmd, ['touristic-orphans', '--details=12345']);

        $optionsRef = new \ReflectionProperty($cmd, 'options');
        $optionsRef->setAccessible(true);
        $options = $optionsRef->getValue($cmd);

        $this->assertSame('12345', $options['details'] ?? null);
    }

    public function testStatsOnlyOptionIsParsed(): void
    {
        $cmd = new TouristicOrphansCommand();
        $reflection = new \ReflectionMethod($cmd, 'parseArguments');
        $reflection->setAccessible(true);

        $reflection->invoke($cmd, ['touristic-orphans', '--stats-only']);

        $optionsRef = new \ReflectionProperty($cmd, 'options');
        $optionsRef->setAccessible(true);
        $options = $optionsRef->getValue($cmd);

        $this->assertTrue($options['stats-only'] ?? false);
    }

    public function testNonInteractiveOptionIsParsed(): void
    {
        $cmd = new TouristicOrphansCommand();
        $reflection = new \ReflectionMethod($cmd, 'parseArguments');
        $reflection->setAccessible(true);

        $reflection->invoke($cmd, ['touristic-orphans', '--non-interactive']);

        $this->assertTrue(
            (new \ReflectionMethod($cmd, 'isNonInteractive'))->invoke($cmd)
        );
    }
}
