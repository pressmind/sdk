<?php

namespace Pressmind\Tests\Unit\Search\Condition\MongoDB;

use PHPUnit\Framework\TestCase;
use Pressmind\Search\Condition\MongoDB\Code;

class CodeTest extends TestCase
{
    public function testGetType(): void
    {
        $c = new Code(['A', 'B']);
        $this->assertSame('Code', $c->getType());
    }

    public function testGetCodesAndCombineOperator(): void
    {
        $c = new Code(['X'], 'OR');
        $this->assertSame(['X'], $c->getCodes());
        $this->assertSame('OR', $c->getCombineOperator());
    }

    public function testFirstMatchQueryIn(): void
    {
        $c = new Code(['A', 'B']);
        $query = $c->getQuery('first_match');
        $this->assertIsArray($query);
        $this->assertEquals(['$in' => ['A', 'B']], $query['code']);
    }

    public function testFirstMatchQueryRegex(): void
    {
        $c = new Code(['pattern'], 'OR', true);
        $query = $c->getQuery('first_match');
        $this->assertIsArray($query);
        $this->assertArrayHasKey('$regex', $query['code']);
    }

    public function testReturnsNullForUnknownType(): void
    {
        $c = new Code('X');
        $this->assertNull($c->getQuery('prices_filter'));
    }

    public function testConstructorWithScalarCode(): void
    {
        $c = new Code('SINGLE');
        $this->assertSame(['SINGLE'], $c->getCodes());
    }

    public function testFirstMatchQueryRegexMultipleCodes(): void
    {
        $c = new Code(['p1', 'p2'], 'OR', true);
        $query = $c->getQuery('first_match');
        $this->assertIsArray($query);
        $this->assertArrayHasKey('$or', $query);
        $this->assertCount(2, $query['$or']);
    }

    public function testFirstMatchQueryRegexMultipleCodesAnd(): void
    {
        $c = new Code(['p1', 'p2'], 'AND', true);
        $query = $c->getQuery('first_match');
        $this->assertArrayHasKey('$and', $query);
        $this->assertCount(2, $query['$and']);
    }

    public function testFirstMatchQueryInOperator(): void
    {
        $c = new Code(['CODE1', 'CODE2', 'CODE3']);
        $query = $c->getQuery('first_match');
        $this->assertSame(['$in' => ['CODE1', 'CODE2', 'CODE3']], $query['code']);
    }

    public function testPricesFilterReturnsNull(): void
    {
        $c = new Code(['A']);
        $this->assertNull($c->getQuery('prices_filter'));
    }

    public function testDepartureFilterReturnsNull(): void
    {
        $c = new Code(['A']);
        $this->assertNull($c->getQuery('departure_filter'));
    }
}
