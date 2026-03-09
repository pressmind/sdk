<?php

namespace Pressmind\Tests\Unit\Search;

use Pressmind\Registry;
use Pressmind\Search;
use Pressmind\Search\Condition\ConditionInterface;
use Pressmind\Search\Paginator;
use Pressmind\Search\Result;
use Pressmind\Tests\Unit\AbstractTestCase;

class SearchTest extends AbstractTestCase
{
    protected $defaultConfig = [
        'cache' => ['enabled' => false, 'types' => []],
        'database' => ['dbname' => 'test', 'engine' => 'InnoDB'],
        'logging' => ['enable_advanced_object_log' => false],
    ];

    /**
     * Build a mock ConditionInterface with configurable return values.
     */
    private function createMockCondition(array $overrides = []): ConditionInterface
    {
        $defaults = [
            'sql'              => 'pmt2core_media_objects.id_object_type = ?',
            'values'           => [],
            'sort'             => 0,
            'joins'            => null,
            'joinType'         => 'JOIN',
            'subselectTable'   => null,
            'additionalFields' => null,
            'toJSON'           => ['type' => 'MockCondition'],
        ];
        $cfg = array_merge($defaults, $overrides);

        $mock = $this->createMock(ConditionInterface::class);
        $mock->method('getSQL')->willReturn($cfg['sql']);
        $mock->method('getValues')->willReturn($cfg['values']);
        $mock->method('getSort')->willReturn($cfg['sort']);
        $mock->method('getJoins')->willReturn($cfg['joins']);
        $mock->method('getJoinType')->willReturn($cfg['joinType']);
        $mock->method('getSubselectJoinTable')->willReturn($cfg['subselectTable']);
        $mock->method('getAdditionalFields')->willReturn($cfg['additionalFields']);

        if (method_exists($mock, 'toJSON')) {
            $mock->method('toJSON')->willReturn($cfg['toJSON']);
        }

        return $mock;
    }

    // =========================================================================
    // Constructor
    // =========================================================================

    public function testConstructorDefaults(): void
    {
        $search = new Search();
        $this->assertSame([], $search->getConditions());
        $this->assertNull($search->getQuery());
        $this->assertSame([], $search->getValues());
        $this->assertFalse($search->return_id_only);
    }

    public function testConstructorWithConditionsLimitsSort(): void
    {
        $cond = $this->createMockCondition();
        $limits = ['start' => 0, 'length' => 10];
        $sort = ['price' => 'ASC'];

        $search = new Search(
            ['objectType' => $cond],
            $limits,
            $sort
        );

        $this->assertCount(1, $search->getConditions());
        $this->assertSame($cond, $search->getConditions()['objectType']);
    }

    // =========================================================================
    // addCondition / getConditions / hasCondition / getCondition
    // =========================================================================

    public function testAddConditionStoresWithKey(): void
    {
        $search = new Search();
        $cond = $this->createMockCondition();
        $search->addCondition('myCondition', $cond);

        $this->assertArrayHasKey('myCondition', $search->getConditions());
        $this->assertSame($cond, $search->getConditions()['myCondition']);
    }

    public function testAddConditionOverwritesSameKey(): void
    {
        $search = new Search();
        $cond1 = $this->createMockCondition(['sql' => 'a = 1']);
        $cond2 = $this->createMockCondition(['sql' => 'b = 2']);

        $search->addCondition('key', $cond1);
        $search->addCondition('key', $cond2);

        $this->assertCount(1, $search->getConditions());
        $this->assertSame($cond2, $search->getConditions()['key']);
    }

    public function testHasConditionReturnsTrueForExistingClass(): void
    {
        $search = new Search();
        $cond = $this->createMockCondition();
        $search->addCondition('test', $cond);

        $this->assertTrue($search->hasCondition(ConditionInterface::class));
    }

    public function testHasConditionReturnsFalseForMissingClass(): void
    {
        $search = new Search();
        $this->assertFalse($search->hasCondition('Pressmind\Search\Condition\NonExistent'));
    }

    public function testGetConditionReturnsMatchingCondition(): void
    {
        $search = new Search();
        $cond = $this->createMockCondition();
        $search->addCondition('test', $cond);

        $result = $search->getCondition(ConditionInterface::class);
        $this->assertSame($cond, $result);
    }

    public function testGetConditionReturnsFalseWhenNotFound(): void
    {
        $search = new Search();
        $this->assertFalse($search->getCondition('Pressmind\Search\Condition\NonExistent'));
    }

    // =========================================================================
    // removeCondition
    // =========================================================================

    public function testRemoveConditionRemovesAndReturnsCondition(): void
    {
        $search = new Search();
        $cond = $this->createMockCondition();
        $search->addCondition('test', $cond);

        $removed = $search->removeCondition(ConditionInterface::class);

        $this->assertSame($cond, $removed);
        $this->assertFalse($search->hasCondition(ConditionInterface::class));
    }

    public function testRemoveConditionReturnsFalseWhenNotFound(): void
    {
        $search = new Search();
        $this->assertFalse($search->removeCondition('Pressmind\Search\Condition\NonExistent'));
    }

    // =========================================================================
    // setLimits / removeLimits
    // =========================================================================

    public function testSetLimitsAndRemoveLimits(): void
    {
        $search = new Search();
        $limits = ['start' => 10, 'length' => 20];
        $search->setLimits($limits);
        $cond = $this->createMockCondition();
        $search->addCondition('test', $cond);
        $search->_concatSql();

        $sql = $search->getQuery();
        $this->assertStringContainsString('LIMIT 10, 20', $sql);

        $search->removeLimits();
        $search->_concatSql();
        $this->assertStringNotContainsString('LIMIT', $search->getQuery());
    }

    // =========================================================================
    // setSortProperties / removeSortProperties
    // =========================================================================

    public function testSetSortPropertiesAndRemove(): void
    {
        $search = new Search();
        $cond = $this->createMockCondition();
        $search->addCondition('test', $cond);

        $search->setSortProperties(['name' => 'ASC']);
        $search->_concatSql();
        $this->assertStringContainsString('ORDER BY name ASC', $search->getQuery());

        $search->removeSortProperties();
        $search->_concatSql();
        $this->assertStringNotContainsString('ORDER BY', $search->getQuery());
    }

    public function testSortPropertyMappedPrice(): void
    {
        $search = new Search();
        $cond = $this->createMockCondition();
        $search->addCondition('test', $cond);
        $search->setSortProperties(['price' => 'ASC']);
        $search->_concatSql();

        $this->assertStringContainsString('cheapest_price_total ASC', $search->getQuery());
    }

    public function testSortPropertyMappedDateDeparture(): void
    {
        $search = new Search();
        $cond = $this->createMockCondition();
        $search->addCondition('test', $cond);
        $search->setSortProperties(['date_departure' => 'DESC']);
        $search->_concatSql();

        $this->assertStringContainsString('cheapest_price_speed.date_departure DESC', $search->getQuery());
    }

    public function testSortPropertyRand(): void
    {
        $search = new Search();
        $cond = $this->createMockCondition();
        $search->addCondition('test', $cond);
        $search->setSortProperties(['random' => 'RAND()']);
        $search->_concatSql();

        $this->assertStringContainsString('RAND()', $search->getQuery());
    }

    // =========================================================================
    // setPaginator / getPaginator
    // =========================================================================

    public function testPaginatorGetterSetter(): void
    {
        $search = new Search();
        $this->assertNull($search->getPaginator());

        $paginator = new Paginator(10, 1);
        $search->setPaginator($paginator);
        $this->assertSame($paginator, $search->getPaginator());
    }

    // =========================================================================
    // getTotalResultCount
    // =========================================================================

    public function testGetTotalResultCountInitiallyNull(): void
    {
        $search = new Search();
        $this->assertNull($search->getTotalResultCount());
    }

    public function testGetTotalResultCountAfterExec(): void
    {
        $search = new Search();
        $cond = $this->createMockCondition();
        $search->addCondition('test', $cond);

        $result = $search->exec();
        $this->assertSame(0, $search->getTotalResultCount());
    }

    // =========================================================================
    // exec (without paginator, cache disabled)
    // =========================================================================

    public function testExecReturnsResultObject(): void
    {
        $search = new Search();
        $cond = $this->createMockCondition();
        $search->addCondition('test', $cond);

        $result = $search->exec();

        $this->assertInstanceOf(Result::class, $result);
        $this->assertIsArray($result->getResult());
        $this->assertSame(0, $search->getTotalResultCount());
    }

    public function testExecSetsQueryAndValues(): void
    {
        $cond = $this->createMockCondition([
            'values' => [':id' => 100],
        ]);
        $search = new Search(['ot' => $cond]);
        $result = $search->exec();

        $this->assertNotNull($result->getQuery());
        $this->assertIsString($result->getQuery());
        $this->assertNotEmpty($search->getQuery());
    }

    public function testExecWithPaginatorDisabled(): void
    {
        $search = new Search();
        $cond = $this->createMockCondition();
        $search->addCondition('test', $cond);

        $paginator = new Paginator(10, 1);
        $search->setPaginator($paginator);

        $result = $search->exec(true);
        $this->assertInstanceOf(Result::class, $result);
    }

    public function testExecWithPaginatorEnabled(): void
    {
        $totalRow = new \stdClass();
        $totalRow->total_rows = 25;

        $db = $this->createMockDb();
        $db = $this->createMock(\Pressmind\DB\Adapter\AdapterInterface::class);
        $db->method('fetchAll')->willReturn([]);
        $db->method('fetchRow')->willReturn($totalRow);
        $db->method('getTablePrefix')->willReturn('pmt2core_');
        Registry::getInstance()->add('db', $db);

        $search = new Search();
        $cond = $this->createMockCondition();
        $search->addCondition('test', $cond);
        $search->setPaginator(new Paginator(10, 1));

        $result = $search->exec();

        $this->assertInstanceOf(Result::class, $result);
        $this->assertSame(25, $search->getTotalResultCount());
    }

    public function testExecPaginatorWithLimitsClampsTotalCount(): void
    {
        $totalRow = new \stdClass();
        $totalRow->total_rows = 100;

        $db = $this->createMock(\Pressmind\DB\Adapter\AdapterInterface::class);
        $db->method('fetchAll')->willReturn([]);
        $db->method('fetchRow')->willReturn($totalRow);
        $db->method('getTablePrefix')->willReturn('pmt2core_');
        Registry::getInstance()->add('db', $db);

        $search = new Search();
        $cond = $this->createMockCondition();
        $search->addCondition('test', $cond);
        $search->setLimits(['start' => 0, 'length' => 50]);
        $search->setPaginator(new Paginator(10, 1));

        $result = $search->exec();
        $this->assertSame(50, $search->getTotalResultCount());
    }

    // =========================================================================
    // getResults (caches internal result)
    // =========================================================================

    public function testGetResultsCachesInternally(): void
    {
        $search = new Search();
        $cond = $this->createMockCondition();
        $search->addCondition('test', $cond);

        $results1 = $search->getResults();
        $results2 = $search->getResults();
        $this->assertSame($results1, $results2);
    }

    public function testGetResultsDisablePaginatorBypassesCache(): void
    {
        $search = new Search();
        $cond = $this->createMockCondition();
        $search->addCondition('test', $cond);

        $results = $search->getResults(false, true);
        $this->assertIsArray($results);
    }

    // =========================================================================
    // generateCacheKey
    // =========================================================================

    public function testGenerateCacheKeyIsDeterministic(): void
    {
        $search = new Search();
        $cond = $this->createMockCondition();
        $search->addCondition('test', $cond);
        $search->_concatSql();

        $key1 = $search->generateCacheKey();
        $key2 = $search->generateCacheKey();
        $this->assertSame($key1, $key2);
    }

    public function testGenerateCacheKeyStartsWithSearchPrefix(): void
    {
        $search = new Search();
        $cond = $this->createMockCondition();
        $search->addCondition('test', $cond);
        $search->_concatSql();

        $this->assertStringStartsWith('SEARCH_', $search->generateCacheKey());
    }

    public function testGenerateCacheKeyDiffersWithAddParam(): void
    {
        $search = new Search();
        $cond = $this->createMockCondition();
        $search->addCondition('test', $cond);
        $search->_concatSql();

        $keyDefault = $search->generateCacheKey();
        $keyCount = $search->generateCacheKey('_COUNT');

        $this->assertNotSame($keyDefault, $keyCount);
        $this->assertStringStartsWith('SEARCH_COUNT_', $keyCount);
    }

    public function testGenerateCacheKeyDiffersForDifferentQueries(): void
    {
        $search1 = new Search();
        $cond1 = $this->createMockCondition(['sql' => 'a = 1']);
        $search1->addCondition('a', $cond1);
        $search1->_concatSql();

        $search2 = new Search();
        $cond2 = $this->createMockCondition(['sql' => 'b = 2']);
        $search2->addCondition('b', $cond2);
        $search2->_concatSql();

        $this->assertNotSame($search1->generateCacheKey(), $search2->generateCacheKey());
    }

    // =========================================================================
    // disableCache
    // =========================================================================

    public function testDisableCacheSetsSkipFlag(): void
    {
        $search = new Search();
        $search->disableCache();

        $reflection = new \ReflectionClass($search);
        $prop = $reflection->getProperty('_skip_cache');
        $prop->setAccessible(true);
        $this->assertTrue($prop->getValue($search));
    }

    // =========================================================================
    // getQuery / getValues
    // =========================================================================

    public function testGetQueryAndValuesAfterConcatSql(): void
    {
        $cond = $this->createMockCondition([
            'sql'    => 'pmt2core_media_objects.id_object_type = :ot',
            'values' => [':ot' => 42],
        ]);
        $search = new Search(['ot' => $cond]);
        $search->_concatSql();

        $this->assertStringContainsString('pmt2core_media_objects', $search->getQuery());
        $this->assertArrayHasKey(':ot', $search->getValues());
        $this->assertSame(42, $search->getValues()[':ot']);
    }

    public function testGetQueryNullBeforeConcatSql(): void
    {
        $search = new Search();
        $this->assertNull($search->getQuery());
    }

    // =========================================================================
    // _concatSql (SQL generation)
    // =========================================================================

    public function testConcatSqlNoConditionsProducesDefaultVisibility(): void
    {
        $search = new Search();
        $search->_concatSql();

        $sql = $search->getQuery();
        $this->assertStringContainsString('SELECT pmt2core_media_objects.*', $sql);
        $this->assertStringContainsString('pmt2core_media_objects.visibility = 30', $sql);
    }

    public function testConcatSqlWithConditionIncludesVisibilityAndValidity(): void
    {
        $cond = $this->createMockCondition([
            'sql' => 'pmt2core_media_objects.id_object_type IN (:ot)',
            'values' => [':ot' => 100],
        ]);
        $search = new Search(['ot' => $cond]);
        $search->_concatSql();

        $sql = $search->getQuery();
        $this->assertStringContainsString('visibility = 30', $sql);
        $this->assertStringContainsString('valid_from', $sql);
        $this->assertStringContainsString('valid_to', $sql);
    }

    public function testConcatSqlReturnTotalCountUsesCountDistinct(): void
    {
        $cond = $this->createMockCondition();
        $search = new Search(['test' => $cond]);
        $search->_concatSql(true);

        $sql = $search->getQuery();
        $this->assertStringContainsString('COUNT(DISTINCT pmt2core_media_objects.id)', $sql);
        $this->assertStringContainsString('total_rows', $sql);
    }

    public function testConcatSqlWithLimitsAppendsLimitClause(): void
    {
        $cond = $this->createMockCondition();
        $search = new Search(['test' => $cond], ['start' => 5, 'length' => 15]);
        $search->_concatSql();

        $this->assertStringContainsString('LIMIT 5, 15', $search->getQuery());
    }

    public function testConcatSqlReturnTotalCountIgnoresLimit(): void
    {
        $cond = $this->createMockCondition();
        $search = new Search(['test' => $cond], ['start' => 0, 'length' => 10]);
        $search->_concatSql(true);

        $this->assertStringNotContainsString('LIMIT', $search->getQuery());
    }

    public function testConcatSqlReturnTotalCountIgnoresSortProperties(): void
    {
        $cond = $this->createMockCondition();
        $search = new Search(['test' => $cond], [], ['name' => 'ASC']);
        $search->_concatSql(true);

        $this->assertStringNotContainsString('ORDER BY', $search->getQuery());
    }

    public function testConcatSqlWithJoins(): void
    {
        $cond = $this->createMockCondition([
            'joins'    => 'INNER JOIN pmt2core_cheapest_price_speed ON pmt2core_cheapest_price_speed.id_media_object = pmt2core_media_objects.id',
            'joinType' => 'JOIN',
        ]);
        $search = new Search(['test' => $cond]);
        $search->_concatSql();

        $this->assertStringContainsString('INNER JOIN pmt2core_cheapest_price_speed', $search->getQuery());
    }

    public function testConcatSqlMergesValues(): void
    {
        $cond1 = $this->createMockCondition([
            'sql'    => 'a = :a',
            'values' => [':a' => 1],
            'sort'   => 0,
        ]);
        $cond2 = $this->createMockCondition([
            'sql'    => 'b = :b',
            'values' => [':b' => 2],
            'sort'   => 1,
        ]);

        $search = new Search(['c1' => $cond1, 'c2' => $cond2]);
        $search->_concatSql();

        $values = $search->getValues();
        $this->assertArrayHasKey(':a', $values);
        $this->assertArrayHasKey(':b', $values);
    }

    public function testConcatSqlResetsBetweenCalls(): void
    {
        $cond = $this->createMockCondition([
            'sql'    => 'x = :x',
            'values' => [':x' => 99],
        ]);
        $search = new Search(['test' => $cond]);
        $search->_concatSql();
        $sql1 = $search->getQuery();

        $search->_concatSql();
        $sql2 = $search->getQuery();

        $this->assertSame($sql1, $sql2);
        $this->assertCount(1, $search->getValues());
    }

    // =========================================================================
    // return_id_only flag
    // =========================================================================

    public function testReturnIdOnlyChangesSelectClause(): void
    {
        $search = new Search();
        $cond = $this->createMockCondition();
        $search->addCondition('test', $cond);

        $search->return_id_only = false;
        $search->_concatSql();
        $this->assertStringContainsString('pmt2core_media_objects.*', $search->getQuery());

        $search->return_id_only = true;
        $search->_concatSql();
        $sqlIdOnly = $search->getQuery();
        $this->assertStringContainsString('pmt2core_media_objects.id', $sqlIdOnly);
        $this->assertStringNotContainsString('pmt2core_media_objects.*', $sqlIdOnly);
    }

    public function testReturnIdOnlyDefaultIsFalse(): void
    {
        $search = new Search();
        $this->assertFalse($search->return_id_only);
    }

    // =========================================================================
    // getConditionsAsJSON
    // =========================================================================

    public function testGetConditionsAsJsonEmptyConditions(): void
    {
        $search = new Search([], ['start' => 0, 'length' => 20], ['price' => 'ASC']);
        $json = $search->getConditionsAsJSON();

        $decoded = json_decode($json, true);
        $this->assertIsArray($decoded);
        $this->assertSame(['start' => 0, 'length' => 20], $decoded['limit']);
        $this->assertSame('price', $decoded['sort']['property']);
        $this->assertSame('ASC', $decoded['sort']['direction']);
        $this->assertSame(100, $decoded['pagination']['pagesize']);
    }

    public function testGetConditionsAsJsonWithConditions(): void
    {
        $cond = new class implements ConditionInterface {
            public function getSQL() { return 'a = 1'; }
            public function getValues() { return []; }
            public function getSort() { return 0; }
            public function getJoins() { return null; }
            public function getJoinType() { return 'JOIN'; }
            public function getSubselectJoinTable() { return null; }
            public function getAdditionalFields() { return null; }
            public function setConfig($config) {}
            public function toJSON() { return ['type' => 'TestCondition', 'value' => 42]; }
        };

        $search = new Search(['myTest' => $cond], [], ['name' => 'DESC']);
        $json = $search->getConditionsAsJSON();
        $decoded = json_decode($json, true);

        $this->assertArrayHasKey('conditions', $decoded);
        $this->assertArrayHasKey('myTest', $decoded['conditions']);
        $this->assertSame('TestCondition', $decoded['conditions']['myTest']['type']);
    }

    public function testGetConditionsAsJsonReturnsValidJson(): void
    {
        $search = new Search([], [], ['price' => 'ASC']);
        $json = $search->getConditionsAsJSON();
        $this->assertJson($json);
    }

    // =========================================================================
    // Subselect join handling
    // =========================================================================

    public function testConcatSqlWithSubselectCondition(): void
    {
        $joinSql = 'INNER JOIN (SELECT id_media_object FROM pmt2core_cheapest_price_speed WHERE (###CONDITIONS###) GROUP BY id_media_object) cps ON cps.id_media_object = pmt2core_media_objects.id';

        $cond = $this->createMockCondition([
            'sql'            => 'price_total BETWEEN 100 AND 500',
            'values'         => [],
            'sort'           => 2,
            'joins'          => $joinSql,
            'joinType'       => 'SUBSELECT',
            'subselectTable' => 'pmt2core_cheapest_price_speed',
        ]);
        $search = new Search(['price' => $cond]);
        $search->_concatSql();

        $sql = $search->getQuery();
        $this->assertStringContainsString('price_total BETWEEN 100 AND 500', $sql);
        $this->assertStringNotContainsString('###CONDITIONS###', $sql);
    }

    // =========================================================================
    // Multiple conditions and sort ordering
    // =========================================================================

    public function testMultipleConditionsProduceAndJoinedWhere(): void
    {
        $cond1 = $this->createMockCondition([
            'sql'    => 'a = 1',
            'values' => [],
            'sort'   => 0,
        ]);
        $cond2 = $this->createMockCondition([
            'sql'    => 'b = 2',
            'values' => [],
            'sort'   => 1,
        ]);
        $search = new Search(['c1' => $cond1, 'c2' => $cond2]);
        $search->_concatSql();

        $sql = $search->getQuery();
        $this->assertStringContainsString('(a = 1)', $sql);
        $this->assertStringContainsString('(b = 2)', $sql);
        $this->assertStringContainsString(') AND (', $sql);
    }

    public function testMultipleSortProperties(): void
    {
        $cond = $this->createMockCondition();
        $search = new Search(['test' => $cond], [], ['name' => 'ASC', 'created' => 'DESC']);
        $search->_concatSql();

        $sql = $search->getQuery();
        $this->assertStringContainsString('ORDER BY name ASC, created DESC', $sql);
    }

    // =========================================================================
    // Edge cases
    // =========================================================================

    public function testExecWithoutConditions(): void
    {
        $search = new Search();
        $result = $search->exec();

        $this->assertInstanceOf(Result::class, $result);
        $sql = $search->getQuery();
        $this->assertStringContainsString('visibility = 30', $sql);
        $this->assertStringNotContainsString('AND ()', $sql);
    }

    public function testConcatSqlCalledTwiceDoesNotDuplicateValues(): void
    {
        $cond = $this->createMockCondition([
            'values' => [':val' => 'test'],
        ]);
        $search = new Search(['test' => $cond]);

        $search->_concatSql();
        $countFirst = count($search->getValues());

        $search->_concatSql();
        $countSecond = count($search->getValues());

        $this->assertSame($countFirst, $countSecond);
    }

    public function testDuplicateJoinsNotRepeated(): void
    {
        $joinStr = 'INNER JOIN pmt2core_other ON pmt2core_other.id = pmt2core_media_objects.id';
        $cond1 = $this->createMockCondition([
            'sql'      => 'x = 1',
            'joins'    => $joinStr,
            'joinType' => 'JOIN',
            'sort'     => 0,
        ]);
        $cond2 = $this->createMockCondition([
            'sql'      => 'y = 2',
            'joins'    => $joinStr,
            'joinType' => 'JOIN',
            'sort'     => 0,
        ]);
        $search = new Search(['c1' => $cond1, 'c2' => $cond2]);
        $search->_concatSql();

        $sql = $search->getQuery();
        $firstPos = strpos($sql, $joinStr);
        $secondPos = strpos($sql, $joinStr, $firstPos + 1);
        $this->assertFalse($secondPos, 'Duplicate join should not appear twice in SQL');
    }
}
