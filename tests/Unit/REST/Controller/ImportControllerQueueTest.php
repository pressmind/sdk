<?php

namespace Pressmind\Tests\Unit\REST\Controller;

use Pressmind\REST\Controller\Import;
use Pressmind\Tests\Unit\AbstractTestCase;

class ImportControllerQueueTest extends AbstractTestCase
{
    public function testAddToQueueKeepsDepublishQueueAction(): void
    {
        $controller = new Import();

        $result = $controller->addToQueue([
            'id_media_object' => '123',
            'queue_action' => 'depublish',
        ]);

        $this->assertTrue($result['success']);
        $this->assertSame('Success: object added to queue with action: depublish', $result['msg']);
    }
}
