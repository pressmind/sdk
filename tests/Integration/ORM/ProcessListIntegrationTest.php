<?php

namespace Pressmind\Tests\Integration\ORM;

use Pressmind\DB\Scaffolder\Mysql as ScaffolderMysql;
use Pressmind\ORM\Object\ProcessList;
use Pressmind\Tests\Integration\AbstractIntegrationTestCase;

/**
 * Integration tests for ProcessList ORM object.
 * Tests lock/unlock cycle, isLocked, getLock and stale lock detection.
 */
class ProcessListIntegrationTest extends AbstractIntegrationTestCase
{
    private const LOCK_NAME = 'test_integration_lock';
    private const LOCK_NAME_STALE = 'test_stale_lock';

    protected function setUp(): void
    {
        parent::setUp();

        if ($this->db === null) {
            $this->markTestSkipped('MySQL not available');
        }

        $this->ensureTable();
        $this->cleanLocks();
    }

    protected function tearDown(): void
    {
        if ($this->db !== null) {
            $this->cleanLocks();
        }
        parent::tearDown();
    }

    private function ensureTable(): void
    {
        try {
            $scaffolder = new ScaffolderMysql(new ProcessList());
            $scaffolder->run(true);
        } catch (\Throwable $e) {
            // table may already exist
        }
    }

    private function cleanLocks(): void
    {
        $this->db->delete('pmt2core_process_list', ['name LIKE ?', 'test_%']);
    }

    // --- isLocked on empty table ---

    public function testIsLockedReturnsFalseWhenNoLock(): void
    {
        $this->assertFalse(ProcessList::isLocked(self::LOCK_NAME));
    }

    // --- getLock on empty table ---

    public function testGetLockReturnsNullWhenNoLock(): void
    {
        $this->assertNull(ProcessList::getLock(self::LOCK_NAME));
    }

    // --- lock / isLocked / getLock cycle ---

    public function testLockCreatesLockEntry(): void
    {
        $pid = getmypid();
        $result = ProcessList::lock(self::LOCK_NAME, $pid, 3600);

        $this->assertTrue($result);
        $this->assertTrue(ProcessList::isLocked(self::LOCK_NAME));
    }

    public function testGetLockReturnsCorrectData(): void
    {
        $pid = getmypid();
        ProcessList::lock(self::LOCK_NAME, $pid, 7200);

        $lock = ProcessList::getLock(self::LOCK_NAME);

        $this->assertNotNull($lock);
        $this->assertInstanceOf(ProcessList::class, $lock);
        $this->assertSame(self::LOCK_NAME, $lock->name);
        $this->assertEquals($pid, $lock->pid);
        $this->assertEquals(7200, $lock->timeout);
        $this->assertInstanceOf(\DateTime::class, $lock->created_at);
    }

    // --- unlock ---

    public function testUnlockRemovesLock(): void
    {
        ProcessList::lock(self::LOCK_NAME, getmypid(), 3600);
        $this->assertTrue(ProcessList::isLocked(self::LOCK_NAME));

        ProcessList::unlock(self::LOCK_NAME);
        $this->assertFalse(ProcessList::isLocked(self::LOCK_NAME));
        $this->assertNull(ProcessList::getLock(self::LOCK_NAME));
    }

    public function testUnlockOnNonExistentLockReturnsTrue(): void
    {
        $result = ProcessList::unlock('test_nonexistent_lock');
        $this->assertTrue($result);
    }

    // --- re-lock overwrites previous lock ---

    public function testRelockOverwritesPreviousLock(): void
    {
        ProcessList::lock(self::LOCK_NAME, 1001, 3600);
        ProcessList::lock(self::LOCK_NAME, 2002, 1800);

        $lock = ProcessList::getLock(self::LOCK_NAME);

        $this->assertNotNull($lock);
        $this->assertEquals(2002, $lock->pid);
        $this->assertEquals(1800, $lock->timeout);
    }

    // --- stale lock detection ---

    public function testStaleLockIsNotConsideredLocked(): void
    {
        ProcessList::lock(self::LOCK_NAME_STALE, getmypid(), 1);

        // Directly manipulate created_at to simulate a stale lock
        $this->db->execute(
            'UPDATE pmt2core_process_list SET created_at = ? WHERE name = ?',
            ['2020-01-01 00:00:00', self::LOCK_NAME_STALE]
        );

        $this->assertFalse(ProcessList::isLocked(self::LOCK_NAME_STALE));
    }

    public function testFreshLockIsConsideredLocked(): void
    {
        ProcessList::lock(self::LOCK_NAME, getmypid(), 86400);

        $this->assertTrue(ProcessList::isLocked(self::LOCK_NAME));
    }

    // --- multiple independent locks ---

    public function testMultipleIndependentLocks(): void
    {
        ProcessList::lock('test_lock_alpha', getmypid(), 3600);
        ProcessList::lock('test_lock_beta', getmypid(), 3600);

        $this->assertTrue(ProcessList::isLocked('test_lock_alpha'));
        $this->assertTrue(ProcessList::isLocked('test_lock_beta'));

        ProcessList::unlock('test_lock_alpha');
        $this->assertFalse(ProcessList::isLocked('test_lock_alpha'));
        $this->assertTrue(ProcessList::isLocked('test_lock_beta'));
    }

    // --- default timeout ---

    public function testLockDefaultTimeout(): void
    {
        ProcessList::lock(self::LOCK_NAME, getmypid());

        $lock = ProcessList::getLock(self::LOCK_NAME);
        $this->assertEquals(86400, $lock->timeout);
    }
}
