<?php

namespace Pressmind\Tests\Unit\Search\Hook;

use PHPUnit\Framework\TestCase;
use Pressmind\Search\Hook\SearchHookResult;

class SearchHookResultTest extends TestCase
{
    public function testDefaultValuesEmpty(): void
    {
        $result = new SearchHookResult();
        $this->assertSame([], $result->codes);
        $this->assertNull($result->forceOrder);
        $this->assertSame([], $result->removeConditions);
        $this->assertSame([], $result->data);
    }

    public function testHasCodesReturnsTrueWhenCodesPresent(): void
    {
        $result = new SearchHookResult(codes: ['ABC', 'DEF']);
        $this->assertTrue($result->hasCodes());
    }

    public function testHasCodesReturnsFalseWhenEmpty(): void
    {
        $result = new SearchHookResult();
        $this->assertFalse($result->hasCodes());
    }

    public function testShouldForceOrderReturnsTrueWhenSet(): void
    {
        $result = new SearchHookResult(forceOrder: 'list');
        $this->assertTrue($result->shouldForceOrder());
        $this->assertSame('list', $result->forceOrder);
    }

    public function testShouldForceOrderReturnsFalseWhenNull(): void
    {
        $result = new SearchHookResult();
        $this->assertFalse($result->shouldForceOrder());
    }

    public function testHasConditionsToRemoveReturnsTrue(): void
    {
        $result = new SearchHookResult(removeConditions: ['DateRange', 'PriceRange']);
        $this->assertTrue($result->hasConditionsToRemove());
        $this->assertSame(['DateRange', 'PriceRange'], $result->removeConditions);
    }

    public function testHasConditionsToRemoveReturnsFalseWhenEmpty(): void
    {
        $result = new SearchHookResult();
        $this->assertFalse($result->hasConditionsToRemove());
    }

    public function testGetDataReturnsValueByKey(): void
    {
        $result = new SearchHookResult(data: ['offers' => [1, 2, 3], 'source' => 'infx']);
        $this->assertSame([1, 2, 3], $result->getData('offers'));
        $this->assertSame('infx', $result->getData('source'));
    }

    public function testGetDataReturnsDefaultForMissingKey(): void
    {
        $result = new SearchHookResult();
        $this->assertNull($result->getData('nonexistent'));
        $this->assertSame('fallback', $result->getData('nonexistent', 'fallback'));
    }

    public function testFullConstructor(): void
    {
        $result = new SearchHookResult(
            codes: ['A1', 'B2'],
            forceOrder: 'list',
            removeConditions: ['Fulltext'],
            data: ['key' => 'value']
        );
        $this->assertSame(['A1', 'B2'], $result->codes);
        $this->assertSame('list', $result->forceOrder);
        $this->assertSame(['Fulltext'], $result->removeConditions);
        $this->assertSame('value', $result->getData('key'));
    }

    public function testReadonlyProperties(): void
    {
        $result = new SearchHookResult(codes: ['X']);
        $this->assertSame(['X'], $result->codes);
    }
}
