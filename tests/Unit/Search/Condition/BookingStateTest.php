<?php

namespace Pressmind\Tests\Unit\Search\Condition;

use PHPUnit\Framework\TestCase;
use Pressmind\Search\Condition\BookingState;

/**
 * Unit tests for Pressmind\Search\Condition\BookingState.
 */
class BookingStateTest extends TestCase
{
    public function testConstructorAndGetters(): void
    {
        $c = new BookingState('state_var', [1, 3]);
        $this->assertSame(6, $c->getSort());
        $this->assertEmpty($c->getValues());
        $this->assertNull($c->getJoinType());
        $this->assertNull($c->getSubselectJoinTable());
        $this->assertNull($c->getAdditionalFields());
    }

    public function testGetSqlWithStateIds(): void
    {
        $c = new BookingState('booking_state', [1, 2]);
        $sql = $c->getSQL();
        $this->assertStringContainsString("var_name = 'booking_state'", $sql);
        $this->assertStringContainsString('pmt2core_touristic_options.state', $sql);
        $this->assertStringContainsString('OR', $sql);
        $values = $c->getValues();
        $this->assertCount(2, $values);
        $this->assertArrayHasKey(':booking_state1', $values);
        $this->assertSame(1, $values[':booking_state1']);
        $this->assertSame(2, $values[':booking_state2']);
    }

    public function testGetSqlWithEmptyStateIds(): void
    {
        $c = new BookingState('var', []);
        $sql = $c->getSQL();
        $this->assertStringContainsString("var_name = 'var'", $sql);
        $this->assertStringContainsString('OR ()', $sql);
        $this->assertEmpty($c->getValues());
    }

    public function testGetJoins(): void
    {
        $c = new BookingState('x', [1]);
        $joins = $c->getJoins();
        $this->assertStringContainsString('INNER JOIN pmt2core_touristic_options', $joins);
        $this->assertStringContainsString('pmt2core_media_objects.id', $joins);
    }

    public function testSetConfig(): void
    {
        $c = new BookingState('x', [1]);
        $config = new \stdClass();
        $config->state_ids = [5, 6];
        $c->setConfig($config);
        $sql = $c->getSQL();
        $this->assertStringContainsString('booking_state1', $sql);
        $values = $c->getValues();
        $this->assertSame(5, $values[':booking_state1']);
        $this->assertSame(6, $values[':booking_state2']);
    }
}
