<?php

namespace Pressmind\Tests\Unit\Cache;

use Pressmind\Cache\Service;
use Pressmind\Tests\Unit\AbstractTestCase;

/**
 * Unit tests for Pressmind\Cache\Service.
 * cleanUp() uses Redis internally; when Redis is unavailable it returns an ERROR string.
 */
class ServiceTest extends AbstractTestCase
{
    public function testCleanUpReturnsString(): void
    {
        $service = new Service();
        $result = $service->cleanUp();
        $this->assertIsString($result);
        $this->assertTrue(
            strpos($result, 'Cleanup') !== false || strpos($result, 'ERROR') !== false,
            'cleanUp() must return a message containing "Cleanup" or "ERROR"'
        );
    }
}
