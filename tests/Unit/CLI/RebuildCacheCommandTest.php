<?php

namespace Pressmind\Tests\Unit\CLI;

use PHPUnit\Framework\TestCase;
use Pressmind\CLI\RebuildCacheCommand;

/**
 * Unit tests for RebuildCacheCommand.
 */
class RebuildCacheCommandTest extends TestCase
{
    public function testCanBeInstantiated(): void
    {
        $cmd = new RebuildCacheCommand();
        $this->assertInstanceOf(RebuildCacheCommand::class, $cmd);
    }
}
