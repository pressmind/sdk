<?php

namespace Pressmind\Tests\Unit\REST\Controller;

use Exception;
use Pressmind\Tests\Unit\AbstractTestCase;
use Pressmind\REST\Controller\Command;

class CommandTest extends AbstractTestCase
{
    public function testListCommandsThrowsWhenApiKeyNotConfigured(): void
    {
        $controller = new Command();
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('API key');
        $controller->listCommands([]);
    }
}
