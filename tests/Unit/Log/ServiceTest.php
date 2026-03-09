<?php

namespace Pressmind\Tests\Unit\Log;

use Pressmind\Log\Service;
use Pressmind\Registry;
use Pressmind\Tests\Unit\AbstractTestCase;

/**
 * Unit tests for Pressmind\Log\Service.
 */
class ServiceTest extends AbstractTestCase
{
    public function testCleanUpFilesystemReturnsMessage(): void
    {
        $config = $this->createMockConfig([
            'logging' => [
                'storage' => 'filesystem',
                'enable_advanced_object_log' => false,
            ],
        ]);
        Registry::getInstance()->add('config', $config);

        $service = new Service();
        $result = $service->cleanUp();
        $this->assertSame('Log cleanup for filesystem has run', $result);
    }

    public function testCleanUpDatabaseReturnsMessage(): void
    {
        $config = $this->createMockConfig([
            'logging' => [
                'storage' => 'database',
                'enable_advanced_object_log' => false,
                'lifetime' => 86400,
                'keep_log_types' => [],
            ],
        ]);
        Registry::getInstance()->add('config', $config);

        $service = new Service();
        $result = $service->cleanUp();
        $this->assertSame('Log cleanup for database has run', $result);
    }
}
