<?php

namespace Pressmind\Tests\Unit\REST\Controller;

use Exception;
use Pressmind\Tests\Unit\AbstractTestCase;
use Pressmind\REST\Controller\Error;

class ErrorTest extends AbstractTestCase
{
    public function testIndexReturnsErrorStructure(): void
    {
        $controller = new Error(['message' => 'Test error', 'exception' => new Exception('Test')]);
        $result = $controller->index();
        $this->assertIsArray($result);
        $this->assertTrue($result['error']);
        $this->assertSame('Test error', $result['message']);
        $this->assertArrayHasKey('trace', $result);
        $this->assertIsArray($result['trace']);
    }
}
