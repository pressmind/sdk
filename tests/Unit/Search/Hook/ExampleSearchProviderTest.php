<?php

namespace Pressmind\Tests\Unit\Search\Hook;

use PHPUnit\Framework\TestCase;
use Pressmind\Search\Hook\ExampleSearchProvider;
use Pressmind\Search\Hook\SearchHookResult;

/**
 * Unit tests for Pressmind\Search\Hook\ExampleSearchProvider.
 */
class ExampleSearchProviderTest extends TestCase
{
    public function testConstructorWithDefaults(): void
    {
        $provider = new ExampleSearchProvider([]);
        $this->assertFalse($provider->isActive([]));
        $this->assertSame(10, $provider->getPriority());
    }

    public function testConstructorWithConfig(): void
    {
        $provider = new ExampleSearchProvider([
            'enabled' => true,
            'api_url' => 'https://example.com/api',
            'object_types' => [123],
            'priority' => 5,
        ]);
        $this->assertTrue($provider->isActive([]));
        $this->assertSame(5, $provider->getPriority());
    }

    public function testIsActiveWhenDisabled(): void
    {
        $provider = new ExampleSearchProvider(['enabled' => false]);
        $this->assertFalse($provider->isActive(['object_type' => 123]));
    }

    public function testIsActiveWhenEnabledAndNoObjectTypesFilter(): void
    {
        $provider = new ExampleSearchProvider(['enabled' => true, 'object_types' => []]);
        $this->assertTrue($provider->isActive([]));
    }

    public function testPreSearchReturnsNullWhenNotActive(): void
    {
        $provider = new ExampleSearchProvider(['enabled' => false]);
        $this->assertNull($provider->preSearch([], []));
    }

    public function testPreSearchWhenActiveCallsExternalApi(): void
    {
        $provider = new ExampleSearchProvider([
            'enabled' => true,
            'api_url' => '', // empty so callExternalApi returns null
        ]);
        $result = $provider->preSearch([], []);
        $this->assertInstanceOf(SearchHookResult::class, $result);
        $this->assertSame(['__NO_RESULTS__'], $result->codes);
    }

    public function testPostSearchReturnsResultWhenNotActive(): void
    {
        $provider = new ExampleSearchProvider(['enabled' => false]);
        $result = (object)['documents' => []];
        $this->assertSame($result, $provider->postSearch($result, []));
    }

    public function testPostSearchReturnsResultWhenActiveButEmptyDocuments(): void
    {
        $provider = new ExampleSearchProvider(['enabled' => true]);
        $result = (object)['documents' => []];
        $out = $provider->postSearch($result, []);
        $this->assertSame($result, $out);
    }

    public function testPostSearchEnrichesWhenActiveAndDocumentsHaveNoCode(): void
    {
        $provider = new ExampleSearchProvider(['enabled' => true]);
        $result = (object)['documents' => [(object)['id' => 1]]];
        $out = $provider->postSearch($result, []);
        $this->assertSame($result, $out);
    }

    public function testIsActiveWithMatchingObjectTypes(): void
    {
        $condition = new class {
            public function getType(): string { return 'ObjectType'; }
            public function getObjectTypes(): array { return [123, 456]; }
        };
        $provider = new ExampleSearchProvider([
            'enabled' => true,
            'object_types' => [123],
        ]);
        $this->assertTrue($provider->isActive(['conditions' => [$condition]]));
    }

    public function testIsActiveWithNonMatchingObjectTypes(): void
    {
        $condition = new class {
            public function getType(): string { return 'ObjectType'; }
            public function getObjectTypes(): array { return [789]; }
        };
        $provider = new ExampleSearchProvider([
            'enabled' => true,
            'object_types' => [123],
        ]);
        $this->assertFalse($provider->isActive(['conditions' => [$condition]]));
    }

    public function testIsActiveWithObjectTypesButNoConditionsInContext(): void
    {
        $provider = new ExampleSearchProvider([
            'enabled' => true,
            'object_types' => [123],
        ]);
        $this->assertTrue($provider->isActive([]));
    }

    public function testIsActiveWithObjectTypesAndEmptyRequestedTypes(): void
    {
        $condition = new class {
            public function getType(): string { return 'DateRange'; }
        };
        $provider = new ExampleSearchProvider([
            'enabled' => true,
            'object_types' => [123],
        ]);
        $this->assertTrue($provider->isActive(['conditions' => [$condition]]));
    }

    public function testGetPriorityUsesConfigValue(): void
    {
        $provider = new ExampleSearchProvider(['priority' => 42]);
        $this->assertSame(42, $provider->getPriority());
    }

    public function testGetPriorityDefaultsToTen(): void
    {
        $provider = new ExampleSearchProvider([]);
        $this->assertSame(10, $provider->getPriority());
    }

    public function testPreSearchReturnsNoResultsWhenApiUrlEmpty(): void
    {
        $provider = new ExampleSearchProvider([
            'enabled' => true,
            'api_url' => '',
        ]);
        $result = $provider->preSearch([], []);
        $this->assertInstanceOf(SearchHookResult::class, $result);
        $this->assertSame(['__NO_RESULTS__'], $result->codes);
        $this->assertSame('list', $result->forceOrder);
        $this->assertSame(['DateRange', 'DurationRange'], $result->removeConditions);
    }

    public function testPostSearchWithArrayDocuments(): void
    {
        $provider = new ExampleSearchProvider(['enabled' => true]);
        $result = (object)['documents' => [['id' => 1, 'name' => 'test']]];
        $out = $provider->postSearch($result, []);
        $this->assertSame($result, $out);
    }

    public function testPostSearchLeavesDocumentsWithoutCodeUntouched(): void
    {
        $provider = new ExampleSearchProvider(['enabled' => true]);
        $doc = (object)['id' => 1, 'name' => 'test'];
        $result = (object)['documents' => [$doc]];
        $out = $provider->postSearch($result, []);
        $this->assertFalse(isset($out->documents[0]->external_offers));
    }

    public function testPostSearchWithNullDocuments(): void
    {
        $provider = new ExampleSearchProvider(['enabled' => true]);
        $result = (object)['total' => 0];
        $out = $provider->postSearch($result, []);
        $this->assertSame($result, $out);
    }
}
