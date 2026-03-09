<?php

namespace Pressmind\Tests\Unit\REST\Controller;

use Exception;
use Pressmind\Tests\Unit\AbstractTestCase;
use Pressmind\REST\Controller\System;

class SystemTest extends AbstractTestCase
{
    public function testUpdateTagsThrowsWhenIdObjectTypeMissing(): void
    {
        $controller = new System();
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Parameter id_object_type is missing');
        $controller->updateTags([]);
    }

    public function testUpdateTagsThrowsWhenIdObjectTypeNotSet(): void
    {
        $controller = new System();
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('id_object_type');
        $controller->updateTags(['other' => 'value']);
    }
}
