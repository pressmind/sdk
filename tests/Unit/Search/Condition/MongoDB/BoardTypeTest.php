<?php

namespace Pressmind\Tests\Unit\Search\Condition\MongoDB;

use PHPUnit\Framework\TestCase;
use Pressmind\Search\Condition\MongoDB\BoardType;

class BoardTypeTest extends TestCase
{
    public function testGetType(): void
    {
        $c = new BoardType('HP');
        $this->assertSame('BoardType', $c->getType());
    }

    public function testGetBoardTypes(): void
    {
        $c = new BoardType(['HP', 'AI']);
        $this->assertSame(['HP', 'AI'], $c->getBoardTypes());
    }

    public function testFirstMatchQuery(): void
    {
        $c = new BoardType('HP');
        $query = $c->getQuery('first_match');
        $this->assertIsArray($query);
        $this->assertEquals(['$in' => ['HP']], $query['prices']['$elemMatch']['option_board_type']);
    }

    public function testPricesFilterQuery(): void
    {
        $c = new BoardType(['HP', 'AI']);
        $query = $c->getQuery('prices_filter');
        $this->assertIsArray($query);
        $this->assertCount(1, $query);
    }

    public function testReturnsNullForUnknownType(): void
    {
        $c = new BoardType('HP');
        $this->assertNull($c->getQuery('unknown'));
    }
}
