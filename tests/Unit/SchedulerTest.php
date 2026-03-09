<?php

namespace Pressmind\Tests\Unit;

use Pressmind\Registry;
use Pressmind\Scheduler;

class SchedulerTest extends AbstractTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $config = $this->createMockConfig([
            'logging' => [
                'mode' => 'NONE',
                'storage' => 'none',
            ],
        ]);
        Registry::getInstance()->add('config', $config, true);
    }

    /**
     * Scheduler constructor calls Task::listAll() internally.
     * With mocked DB returning empty results, it should not throw.
     */
    public function testSchedulerCanBeInstantiatedWithMockedDb(): void
    {
        $scheduler = new Scheduler();
        $this->assertInstanceOf(Scheduler::class, $scheduler);
    }
}
