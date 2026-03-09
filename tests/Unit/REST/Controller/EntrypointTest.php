<?php

namespace Pressmind\Tests\Unit\REST\Controller;

use Pressmind\Tests\Unit\AbstractTestCase;
use Pressmind\REST\Controller\Entrypoint;

class EntrypointTest extends AbstractTestCase
{
    public function testGetBookingLinkWithEmptyParamsReturnsError(): void
    {
        $controller = new Entrypoint();
        $result = $controller->getBookingLink([]);
        $this->assertIsArray($result);
        $this->assertTrue($result['error']);
        $this->assertArrayHasKey('msg', $result);
        $this->assertNull($result['payload']);
    }

    public function testGetBookingLinkWithMissingIdOfferReturnsError(): void
    {
        $registry = \Pressmind\Registry::getInstance();
        $registry->add('config', $this->createMockConfig(['ib3' => ['endpoint' => 'https://example.com']]));
        $controller = new Entrypoint();
        $result = $controller->getBookingLink(['pax' => 2]);
        $this->assertTrue($result['error']);
        $this->assertStringContainsString('id_offer', $result['msg']);
    }

    public function testGetBookingLinkWithMissingPaxReturnsError(): void
    {
        $registry = \Pressmind\Registry::getInstance();
        $registry->add('config', $this->createMockConfig(['ib3' => ['endpoint' => 'https://example.com']]));
        $controller = new Entrypoint();
        $result = $controller->getBookingLink(['id_offer' => 1]);
        $this->assertTrue($result['error']);
        $this->assertStringContainsString('pax', $result['msg']);
    }
}
