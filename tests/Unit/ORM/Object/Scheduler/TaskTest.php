<?php

namespace Pressmind\Tests\Unit\ORM\Object\Scheduler;

use Pressmind\ORM\Object\Scheduler\Task;
use Pressmind\Tests\Unit\AbstractTestCase;

/**
 * Unit tests for Scheduler\Task ORM: instantiation, fromArray, toStdClass.
 */
class TaskTest extends AbstractTestCase
{
    public function testInstantiationWithoutId(): void
    {
        $task = new Task(null, false);
        $this->assertNull($task->getId());
    }

    public function testGetDbTableName(): void
    {
        $task = new Task(null, false);
        $this->assertSame('pmt2core_pmt2core_scheduler_task', $task->getDbTableName());
    }

    public function testSetAndGetId(): void
    {
        $task = new Task(null, false);
        $task->setId(1);
        $this->assertSame(1, $task->getId());
    }

    public function testFromArrayPopulatesProperties(): void
    {
        $task = new Task(null, false);
        $task->fromArray([
            'id' => 1,
            'name' => 'Daily Job',
            'description' => 'Runs daily',
            'schedule' => '0 0 * * *',
            'active' => true,
            'running' => false,
            'error_count' => 0,
            'class_name' => 'SomeClass',
            'construct_parameters' => '{}',
        ]);
        $this->assertSame(1, $task->id);
        $this->assertSame('Daily Job', $task->name);
        $this->assertSame('Runs daily', $task->description);
        $this->assertSame('0 0 * * *', $task->schedule);
        $this->assertTrue($task->active);
        $this->assertFalse($task->running);
        $this->assertSame(0, $task->error_count);
        $this->assertSame('SomeClass', $task->class_name);
        $this->assertSame('{}', $task->construct_parameters);
    }

    public function testToStdClassWithoutRelations(): void
    {
        $task = new Task(null, false);
        $task->id = 2;
        $task->name = 'Test';
        $task->active = false;
        $std = $task->toStdClass(false);
        $this->assertInstanceOf(\stdClass::class, $std);
        $this->assertSame(2, $std->id);
        $this->assertSame('Test', $std->name);
        $this->assertFalse($std->active);
    }
}
