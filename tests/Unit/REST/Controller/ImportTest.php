<?php

namespace Pressmind\Tests\Unit\REST\Controller;

use Pressmind\Tests\Unit\AbstractTestCase;
use Pressmind\REST\Controller\Import;

class ImportTest extends AbstractTestCase
{
    public function testAddToQueueWithNoParametersReturnsError(): void
    {
        $controller = new Import();
        $result = $controller->addToQueue([]);
        $this->assertIsArray($result);
        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('msg', $result);
        $this->assertStringContainsString('id_media_object or code is required', $result['msg']);
    }

    public function testAddToQueueWithInvalidQueueActionDefaultsToMediaobject(): void
    {
        $controller = new Import();
        $result = $controller->addToQueue([
            'queue_action' => 'invalid',
            'id_media_object' => 'not_numeric',
        ]);
        $this->assertIsArray($result);
        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('msg', $result);
    }

    public function testAddToQueueWithIdMediaObjectNonNumericAndNoCodeReturnsError(): void
    {
        $controller = new Import();
        $result = $controller->addToQueue(['id_media_object' => 'abc']);
        $this->assertIsArray($result);
        $this->assertFalse($result['success']);
    }

    public function testAddToQueueResponseHasExpectedKeys(): void
    {
        $controller = new Import();
        $result = $controller->addToQueue([]);
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('msg', $result);
        $this->assertArrayHasKey('data', $result);
    }
}
