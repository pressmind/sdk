<?php

namespace Pressmind\Tests\Unit\Registry;

use PHPUnit\Framework\TestCase;
use Pressmind\Registry;

class RegistryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Registry::clear();
    }

    protected function tearDown(): void
    {
        Registry::clear();
        parent::tearDown();
    }

    public function testGetInstanceReturnsSameInstance(): void
    {
        $instanceA = Registry::getInstance();
        $instanceB = Registry::getInstance();

        $this->assertSame($instanceA, $instanceB);
    }

    public function testAddAndGet(): void
    {
        $registry = Registry::getInstance();
        $registry->add('key1', 'value1');

        $this->assertSame('value1', $registry->get('key1'));
    }

    public function testAddOverwritesExistingKey(): void
    {
        $registry = Registry::getInstance();
        $registry->add('key', 'original');
        $registry->add('key', 'overwritten');

        $this->assertSame('overwritten', $registry->get('key'));
    }

    public function testAddSupportsVariousValueTypes(): void
    {
        $registry = Registry::getInstance();

        $registry->add('int', 42);
        $registry->add('array', ['a', 'b']);
        $registry->add('null', null);
        $registry->add('object', new \stdClass());

        $this->assertSame(42, $registry->get('int'));
        $this->assertSame(['a', 'b'], $registry->get('array'));
        $this->assertNull($registry->get('null'));
        $this->assertInstanceOf(\stdClass::class, $registry->get('object'));
    }

    public function testRemoveDeletesKey(): void
    {
        $registry = Registry::getInstance();
        $registry->add('ephemeral', 'data');
        $registry->remove('ephemeral');

        $this->assertNull($registry->get('ephemeral'));
    }

    public function testClearResetsInstance(): void
    {
        $before = Registry::getInstance();
        $before->add('persistent', 'data');

        Registry::clear();

        $after = Registry::getInstance();
        $this->assertNotSame($before, $after);
    }

    public function testClearRemovesAllData(): void
    {
        $registry = Registry::getInstance();
        $registry->add('a', 1);
        $registry->add('b', 2);

        Registry::clear();

        $fresh = Registry::getInstance();

        $this->assertNull($fresh->get('a'));
        $this->assertNull($fresh->get('b'));
    }

    public function testMultipleKeysAreIsolated(): void
    {
        $registry = Registry::getInstance();
        $registry->add('x', 'alpha');
        $registry->add('y', 'beta');

        $this->assertSame('alpha', $registry->get('x'));
        $this->assertSame('beta', $registry->get('y'));

        $registry->remove('x');

        $this->assertSame('beta', $registry->get('y'));
    }

    public function testGetNeverSetKeyReturnsNull(): void
    {
        $registry = Registry::getInstance();
        $this->assertNull($registry->get('never_set_key'));
    }

    public function testRemoveNonExistentKeyDoesNotThrow(): void
    {
        $registry = Registry::getInstance();
        $registry->remove('nonexistent');
        $this->assertNull($registry->get('nonexistent'));
    }

    public function testCloneIsProhibited(): void
    {
        $this->expectException(\Error::class);
        $registry = Registry::getInstance();
        clone $registry;
    }

    public function testCloneMethodIsCallableViaReflection(): void
    {
        $registry = Registry::getInstance();
        $registry->add('beforeClone', 'yes');

        $reflection = new \ReflectionClass($registry);
        $cloneMethod = $reflection->getMethod('__clone');
        $cloneMethod->setAccessible(true);
        $cloneMethod->invoke($registry);

        $this->assertSame('yes', $registry->get('beforeClone'));
    }

    public function testConstructorIsProtected(): void
    {
        $reflection = new \ReflectionClass(Registry::class);
        $constructor = $reflection->getMethod('__construct');
        $this->assertTrue($constructor->isProtected());
    }
}
