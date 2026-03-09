<?php

namespace Pressmind\Tests\Unit\ORM\Object\Import;

use Pressmind\ORM\Object\Import\Queue;
use Pressmind\Tests\Unit\AbstractTestCase;

class QueueTest extends AbstractTestCase
{
    public function testExistsReturnsFalseWhenNoEntries(): void
    {
        $this->assertFalse(Queue::exists(12345));
    }

    public function testGetAllPendingReturnsEmptyArrayWhenNoEntries(): void
    {
        $this->assertSame([], Queue::getAllPending());
    }

    public function testGetAllPendingWithActionReturnsEmptyArrayWhenNoEntries(): void
    {
        $this->assertSame([], Queue::getAllPendingWithAction());
    }

    public function testCountReturnsZeroWhenEmpty(): void
    {
        $this->assertSame(0, Queue::count());
    }

    public function testClearExecutesWithoutError(): void
    {
        Queue::clear();
        $this->assertTrue(true);
    }

    public function testRemoveReturnsFalseWhenNoEntriesFound(): void
    {
        $this->assertFalse(Queue::remove(99999));
    }

    public function testAddToQueueReturnsTrueForNewItem(): void
    {
        $this->assertTrue(Queue::addToQueue(42));
    }

    public function testAddToQueueWithCustomSourceAndAction(): void
    {
        $this->assertTrue(Queue::addToQueue(100, 'webhook', 'touristic'));
    }

    public function testQueueDefinitionsContainExpectedTableName(): void
    {
        $queue = new Queue();
        $this->assertSame('pmt2core_pmt2core_import_queue', $queue->getDbTableName());
    }

    public function testQueuePropertyAssignment(): void
    {
        $queue = new Queue();
        $queue->id_media_object = 55;
        $queue->source = 'api_import';
        $queue->action = 'touristic';

        $this->assertSame(55, $queue->id_media_object);
        $this->assertSame('api_import', $queue->source);
        $this->assertSame('touristic', $queue->action);
    }
}
