<?php

namespace Pressmind\Tests\Unit\Search;

use PHPUnit\Framework\TestCase;
use Pressmind\Search\Result;

class ResultTest extends TestCase
{
    public function testConstructorDefaultIsCachedIsFalse(): void
    {
        $result = new Result();
        $this->assertFalse($result->isCached());
    }

    public function testConstructorWithIsCachedTrue(): void
    {
        $result = new Result(true);
        $this->assertTrue($result->isCached());
    }

    public function testConstructorWithIsCachedStringKey(): void
    {
        $result = new Result('my-cache-key');
        $this->assertSame('my-cache-key', $result->isCached());
    }

    public function testSetResultRawAndGetResultReturnsItems(): void
    {
        $result = new Result();
        $items = [
            (object)['id' => 1, 'name' => 'Item A'],
            (object)['id' => 2, 'name' => 'Item B'],
            (object)['id' => 3, 'name' => 'Item C'],
        ];
        $result->setResultRaw($items);

        $returned = $result->getResult();

        $this->assertCount(3, $returned);
        $this->assertSame($items[0], $returned[0]);
        $this->assertSame($items[1], $returned[1]);
        $this->assertSame($items[2], $returned[2]);
    }

    public function testGetResultWithEmptyResultRaw(): void
    {
        $result = new Result();
        $result->setResultRaw([]);

        $this->assertSame([], $result->getResult());
    }

    public function testGetResultCachesResultOnSecondCall(): void
    {
        $result = new Result();
        $items = [
            (object)['id' => 1],
            (object)['id' => 2],
        ];
        $result->setResultRaw($items);

        $firstCall = $result->getResult();
        $secondCall = $result->getResult();

        $this->assertSame($firstCall, $secondCall);
    }

    public function testSetQueryAndGetQuery(): void
    {
        $result = new Result();
        $query = 'SELECT * FROM media_objects WHERE id = ?';
        $result->setQuery($query);

        $this->assertSame($query, $result->getQuery());
    }

    public function testSetValuesAndGetValues(): void
    {
        $result = new Result();
        $values = [42, 'active', true];
        $result->setValues($values);

        $this->assertSame($values, $result->getValues());
    }

    public function testIsCachedReturnsCorrectValue(): void
    {
        $notCached = new Result(false);
        $cached = new Result(true);

        $this->assertFalse($notCached->isCached());
        $this->assertTrue($cached->isCached());
    }
}
