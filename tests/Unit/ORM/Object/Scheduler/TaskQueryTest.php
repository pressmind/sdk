<?php

namespace Pressmind\Tests\Unit\ORM\Object\Scheduler;

use DateTime;
use Pressmind\ORM\Object\Scheduler\Task;
use Pressmind\Tests\Unit\AbstractTestCase;

class TaskQueryTest extends AbstractTestCase
{
    public function testShallRunReturnsFalseWhenRunning(): void
    {
        $task = new Task(null, false);
        $task->running = true;
        $task->schedule = json_encode(['type' => 'Minutely', 'value' => 5]);
        $task->name = 'test-task';

        $this->assertFalse($task->shallRun());
    }

    public function testShallRunReturnsFalseForUnknownScheduleType(): void
    {
        $task = new Task(null, false);
        $task->running = false;
        $task->schedule = json_encode(['type' => 'Weekly', 'value' => 1]);
        $task->name = 'test-task';

        $this->assertFalse($task->shallRun());
    }

    public function testFailOverCheckReturnsFalseWhenWithinTimeLimit(): void
    {
        $task = new Task(null, false);
        $task->running = true;
        $task->name = 'test-task';
        $task->schedule = json_encode([
            'type' => 'Minutely',
            'value' => 5,
            'max_running_time_in_minutes' => 60,
        ]);
        $task->last_run = new DateTime('now');

        $this->assertFalse($task->failOverCheck());
    }

    public function testFailOverCheckResetsRunningWhenExceeded(): void
    {
        $task = new Task(null, false);
        $task->running = true;
        $task->name = 'test-task';
        $task->schedule = json_encode([
            'type' => 'Minutely',
            'value' => 5,
            'max_running_time_in_minutes' => 10,
        ]);
        $task->last_run = new DateTime('-2 days');

        $result = $task->failOverCheck();

        $this->assertIsString($result);
        $this->assertStringContainsString('Reset running to false', $result);
        $this->assertFalse($task->running);
    }
}
