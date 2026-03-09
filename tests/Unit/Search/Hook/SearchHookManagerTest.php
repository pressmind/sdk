<?php

namespace Pressmind\Tests\Unit\Search\Hook;

use Pressmind\Registry;
use Pressmind\Search\Hook\SearchHookInterface;
use Pressmind\Search\Hook\SearchHookManager;
use Pressmind\Search\Hook\SearchHookResult;
use Pressmind\Tests\Unit\AbstractTestCase;

class StubSearchHook implements SearchHookInterface
{
    private int $priority;
    private bool $active;
    private ?SearchHookResult $preSearchResult;
    public int $preSearchCalls = 0;
    public int $postSearchCalls = 0;

    public function __construct(int $priority = 10, bool $active = true, ?SearchHookResult $preSearchResult = null)
    {
        $this->priority = $priority;
        $this->active = $active;
        $this->preSearchResult = $preSearchResult;
    }

    public function preSearch(array $conditions, array $context): ?SearchHookResult
    {
        $this->preSearchCalls++;
        return $this->preSearchResult;
    }

    public function postSearch(object $result, array $context): object
    {
        $this->postSearchCalls++;
        $result->hookApplied = true;
        return $result;
    }

    public function isActive(array $context): bool
    {
        return $this->active;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }
}

class SearchHookManagerTest extends AbstractTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        SearchHookManager::clear();
    }

    protected function tearDown(): void
    {
        SearchHookManager::clear();
        parent::tearDown();
    }

    public function testRegisterAndGetHooks(): void
    {
        $hook = new StubSearchHook();
        SearchHookManager::register($hook);
        $hooks = SearchHookManager::getHooks();
        $this->assertCount(1, $hooks);
        $this->assertSame($hook, $hooks[0]);
    }

    public function testClearRemovesAllHooks(): void
    {
        SearchHookManager::register(new StubSearchHook());
        SearchHookManager::register(new StubSearchHook());
        SearchHookManager::clear();
        $hooks = SearchHookManager::getHooks();
        $this->assertCount(0, $hooks);
    }

    public function testExecutePreSearchReturnsNullWhenNoHooks(): void
    {
        $result = SearchHookManager::executePreSearch([], ['language' => 'de']);
        $this->assertNull($result);
    }

    public function testExecutePreSearchReturnsFirstNonNullResult(): void
    {
        $hookResult = new SearchHookResult(codes: ['ABC', 'DEF']);
        $hook1 = new StubSearchHook(priority: 5, preSearchResult: null);
        $hook2 = new StubSearchHook(priority: 10, preSearchResult: $hookResult);
        $hook3 = new StubSearchHook(priority: 15, preSearchResult: new SearchHookResult(codes: ['GHI']));
        SearchHookManager::register($hook1);
        SearchHookManager::register($hook2);
        SearchHookManager::register($hook3);
        $result = SearchHookManager::executePreSearch([], ['language' => 'de']);
        $this->assertNotNull($result);
        $this->assertSame(['ABC', 'DEF'], $result->codes);
        $this->assertSame(1, $hook1->preSearchCalls);
        $this->assertSame(1, $hook2->preSearchCalls);
        $this->assertSame(0, $hook3->preSearchCalls, 'Third hook should not be called');
    }

    public function testExecutePreSearchSkipsInactiveHooks(): void
    {
        $hookResult = new SearchHookResult(codes: ['X']);
        $inactiveHook = new StubSearchHook(priority: 1, active: false, preSearchResult: $hookResult);
        $activeHook = new StubSearchHook(priority: 10, preSearchResult: null);
        SearchHookManager::register($inactiveHook);
        SearchHookManager::register($activeHook);
        $result = SearchHookManager::executePreSearch([], ['language' => 'de']);
        $this->assertNull($result);
        $this->assertSame(0, $inactiveHook->preSearchCalls);
        $this->assertSame(1, $activeHook->preSearchCalls);
    }

    public function testExecutePreSearchSkipsWhenSkipSearchHooksSet(): void
    {
        $hookResult = new SearchHookResult(codes: ['ABC']);
        SearchHookManager::register(new StubSearchHook(preSearchResult: $hookResult));
        $result = SearchHookManager::executePreSearch([], ['skip_search_hooks' => true]);
        $this->assertNull($result);
    }

    public function testExecutePostSearchModifiesResult(): void
    {
        $hook = new StubSearchHook();
        SearchHookManager::register($hook);
        $result = new \stdClass();
        $result->documents = [];
        $modified = SearchHookManager::executePostSearch($result, ['language' => 'de']);
        $this->assertTrue($modified->hookApplied);
        $this->assertSame(1, $hook->postSearchCalls);
    }

    public function testExecutePostSearchSkipsInactiveHooks(): void
    {
        $hook = new StubSearchHook(active: false);
        SearchHookManager::register($hook);
        $result = new \stdClass();
        $modified = SearchHookManager::executePostSearch($result, ['language' => 'de']);
        $this->assertSame(0, $hook->postSearchCalls);
        $this->assertFalse(isset($modified->hookApplied));
    }

    public function testExecutePostSearchSkipsWhenSkipSearchHooksSet(): void
    {
        $hook = new StubSearchHook();
        SearchHookManager::register($hook);
        $result = new \stdClass();
        $modified = SearchHookManager::executePostSearch($result, ['skip_search_hooks' => true]);
        $this->assertSame(0, $hook->postSearchCalls);
    }

    public function testPrioritySortOrder(): void
    {
        $resultHigh = new SearchHookResult(codes: ['HIGH']);
        $resultLow = new SearchHookResult(codes: ['LOW']);
        $hookHigh = new StubSearchHook(priority: 20, preSearchResult: $resultHigh);
        $hookLow = new StubSearchHook(priority: 5, preSearchResult: $resultLow);
        SearchHookManager::register($hookHigh);
        SearchHookManager::register($hookLow);
        $result = SearchHookManager::executePreSearch([], ['language' => 'de']);
        $this->assertSame(['LOW'], $result->codes, 'Lower priority (5) hook should execute first');
        $this->assertSame(0, $hookHigh->preSearchCalls, 'Higher priority hook should not be called');
    }

    public function testHasActiveHooksReturnsTrueWhenActive(): void
    {
        SearchHookManager::register(new StubSearchHook(active: true));
        $this->assertTrue(SearchHookManager::hasActiveHooks(['language' => 'de']));
    }

    public function testHasActiveHooksReturnsFalseWhenNoneActive(): void
    {
        SearchHookManager::register(new StubSearchHook(active: false));
        $this->assertFalse(SearchHookManager::hasActiveHooks(['language' => 'de']));
    }

    public function testHasActiveHooksReturnsFalseWhenEmpty(): void
    {
        $this->assertFalse(SearchHookManager::hasActiveHooks([]));
    }

    public function testInitFromConfigWithNoHooksConfigured(): void
    {
        SearchHookManager::clear();
        SearchHookManager::initFromConfig();
        $this->assertCount(0, SearchHookManager::getHooks());
    }

    public function testInitFromConfigWithNonexistentClass(): void
    {
        $config = $this->createMockConfig([
            'data' => [
                'search_hooks' => [
                    ['class' => '\\NonExistent\\Hook\\Class123', 'config' => []],
                ],
            ],
        ]);
        Registry::getInstance()->add('config', $config);
        SearchHookManager::clear();
        SearchHookManager::initFromConfig();
        $this->assertCount(0, SearchHookManager::getHooks());
    }

    public function testInitFromConfigIsIdempotent(): void
    {
        SearchHookManager::clear();
        SearchHookManager::initFromConfig();
        SearchHookManager::initFromConfig();
        $this->assertCount(0, SearchHookManager::getHooks());
    }
}
