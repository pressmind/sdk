<?php

namespace Pressmind\Tests\Unit\CLI;

use PHPUnit\Framework\TestCase;
use Pressmind\CLI\CachePrimerCommand;

/**
 * Unit tests for CachePrimerCommand.
 */
class CachePrimerCommandTest extends TestCase
{
    public function testCanBeInstantiated(): void
    {
        $cmd = new CachePrimerCommand();
        $this->assertInstanceOf(CachePrimerCommand::class, $cmd);
    }

    public function testBaseUrlOptionIsParsed(): void
    {
        $cmd = new CachePrimerCommand();
        $reflection = new \ReflectionMethod($cmd, 'parseArguments');
        $reflection->setAccessible(true);

        $reflection->invoke($cmd, ['cache_primer.php', '--base-url=https://example.com']);

        $optionsRef = new \ReflectionProperty($cmd, 'options');
        $optionsRef->setAccessible(true);
        $options = $optionsRef->getValue($cmd);

        $this->assertSame('https://example.com', $options['base-url'] ?? null);
    }
}
