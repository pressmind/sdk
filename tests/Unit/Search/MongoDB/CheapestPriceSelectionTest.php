<?php

namespace Pressmind\Tests\Unit\Search\MongoDB;

use Pressmind\Tests\Unit\AbstractTestCase;

/**
 * Tests for the cheapest-price selection logic used in the MongoDB aggregation pipeline.
 *
 * Selection order: Occupancy (DZ=2 > EZ=1 > rest) > State (lower = better: 100 bookable, 200 request, 300 stop) > Price > Duration.
 * The pipeline uses $let with $switch for occupancy rank, then $cond with $or for the comparator.
 */
class CheapestPriceSelectionTest extends AbstractTestCase
{
    /** MongoDB index state values (lower = better) */
    private const STATE_BOOKABLE = 100;
    private const STATE_REQUEST  = 200;
    private const STATE_STOP      = 300;

    // ------------------------------------------------------------------
    // Pipeline structure tests
    // ------------------------------------------------------------------

    public function testPipelineContainsReduceStage(): void
    {
        $pipeline = $this->buildMinimalPipeline();

        $reduceFound = false;
        foreach ($pipeline as $stage) {
            if (isset($stage['$project']['prices']['$reduce'])) {
                $reduceFound = true;
                break;
            }
        }

        $this->assertTrue($reduceFound, 'Pipeline must contain a $project stage with a $reduce on prices');
    }

    public function testReduceStageUsesLetWithOccupancySwitch(): void
    {
        $reduce = $this->extractPriceReduceFromPipeline();
        $this->assertNotNull($reduce, '$reduce definition must exist in the prices projection');

        $this->assertArrayHasKey('$let', $reduce['in'], 'Reduce in must use $let for occupancy rank and comparator');
        $let = $reduce['in']['$let'];
        $this->assertArrayHasKey('vars', $let);
        $this->assertArrayHasKey('thisOccRank', $let['vars']);
        $this->assertArrayHasKey('valueOccRank', $let['vars']);
        $this->assertArrayHasKey('$switch', $let['vars']['thisOccRank'], 'Occupancy rank must use $switch');
    }

    public function testReduceStageStateComparisonInCond(): void
    {
        $reduce = $this->extractPriceReduceFromPipeline();
        $letIn = $reduce['in']['$let']['in'] ?? [];
        $condIf = $letIn['$cond']['if']['$or'] ?? [];

        $stateAndFound = false;
        foreach ($condIf as $branch) {
            if (isset($branch['$and'])) {
                foreach ($branch['$and'] as $expr) {
                    if (isset($expr['$lt']) && $expr['$lt'] === ['$$this.state', '$$value.state']) {
                        $stateAndFound = true;
                        break 2;
                    }
                }
            }
        }
        $this->assertTrue($stateAndFound, '$or must contain a branch comparing $$this.state < $$value.state');
    }

    public function testReduceStagePrefersSamePriceLongerDuration(): void
    {
        $reduce = $this->extractPriceReduceFromPipeline();
        $letIn = $reduce['in']['$let']['in'] ?? [];
        $condIf = $letIn['$cond']['if']['$or'] ?? [];

        $durationBranch = null;
        foreach ($condIf as $branch) {
            if (isset($branch['$and'])) {
                foreach ($branch['$and'] as $expr) {
                    if (isset($expr['$gt']) && $expr['$gt'] === ['$$this.duration', '$$value.duration']) {
                        $durationBranch = $branch;
                        break 2;
                    }
                }
            }
        }
        $this->assertNotEmpty($durationBranch, 'One $or branch must use $gt for duration tiebreak');
    }

    // ------------------------------------------------------------------
    // Behavioural tests (PHP re-implementation: Occupancy > State > Price > Duration)
    // ------------------------------------------------------------------

    public function testOccupancyDoubleRoomPreferredOverSingle(): void
    {
        $prices = [
            ['occupancy' => 1, 'state' => self::STATE_BOOKABLE, 'price_total' => 3399, 'duration' => 7],
            ['occupancy' => 2, 'state' => self::STATE_BOOKABLE, 'price_total' => 4899, 'duration' => 7],
        ];

        $best = $this->reduceToSingleBestPrice($prices);

        $this->assertSame(2, $best['occupancy'], 'DZ (2) must win over EZ (1) at same state');
        $this->assertSame(4899.0, (float)$best['price_total']);
    }

    public function testOccupancySingleRoomPreferredOverTriple(): void
    {
        $prices = [
            ['occupancy' => 3, 'state' => self::STATE_BOOKABLE, 'price_total' => 4000, 'duration' => 7],
            ['occupancy' => 1, 'state' => self::STATE_BOOKABLE, 'price_total' => 5000, 'duration' => 7],
        ];

        $best = $this->reduceToSingleBestPrice($prices);

        $this->assertSame(1, $best['occupancy'], 'EZ (1) must win over 3-bed (3)');
    }

    public function testOccupancyFallbackToSingleWhenNoDouble(): void
    {
        $prices = [
            ['occupancy' => 1, 'state' => self::STATE_BOOKABLE, 'price_total' => 1899, 'duration' => 7],
            ['occupancy' => 3, 'state' => self::STATE_BOOKABLE, 'price_total' => 1700, 'duration' => 7],
        ];

        $best = $this->reduceToSingleBestPrice($prices);

        $this->assertSame(1, $best['occupancy']);
        $this->assertSame(1899.0, (float)$best['price_total']);
    }

    public function testOccupancySameRoomStatePriorityStillApplies(): void
    {
        $prices = [
            ['occupancy' => 2, 'state' => self::STATE_REQUEST,  'price_total' => 3200, 'duration' => 7],
            ['occupancy' => 2, 'state' => self::STATE_BOOKABLE, 'price_total' => 4899, 'duration' => 7],
        ];

        $best = $this->reduceToSingleBestPrice($prices);

        $this->assertSame(self::STATE_BOOKABLE, $best['state'], 'Same occupancy: better state (bookable) must win');
        $this->assertSame(4899.0, (float)$best['price_total']);
    }

    public function testOccupancySameRoomSameStateLowerPriceWins(): void
    {
        $prices = [
            ['occupancy' => 2, 'state' => self::STATE_BOOKABLE, 'price_total' => 5200, 'duration' => 7],
            ['occupancy' => 2, 'state' => self::STATE_BOOKABLE, 'price_total' => 4899, 'duration' => 7],
        ];

        $best = $this->reduceToSingleBestPrice($prices);

        $this->assertSame(4899.0, (float)$best['price_total']);
    }

    public function testOccupancySameRoomSamePriceLongerDurationWins(): void
    {
        $prices = [
            ['occupancy' => 2, 'state' => self::STATE_BOOKABLE, 'price_total' => 4899, 'duration' => 5],
            ['occupancy' => 2, 'state' => self::STATE_BOOKABLE, 'price_total' => 4899, 'duration' => 10],
        ];

        $best = $this->reduceToSingleBestPrice($prices);

        $this->assertSame(10, $best['duration']);
    }

    public function testStatePriorityBookableOverStop(): void
    {
        $prices = [
            ['occupancy' => 2, 'state' => self::STATE_STOP,     'price_total' => 300, 'duration' => 7],
            ['occupancy' => 2, 'state' => self::STATE_BOOKABLE, 'price_total' => 600, 'duration' => 7],
        ];

        $best = $this->reduceToSingleBestPrice($prices);

        $this->assertSame(self::STATE_BOOKABLE, $best['state'], 'BOOKABLE must beat STOP regardless of price');
    }

    public function testStatePrioritySameStateLowerPriceWins(): void
    {
        $prices = [
            ['occupancy' => 2, 'state' => self::STATE_BOOKABLE, 'price_total' => 800, 'duration' => 7],
            ['occupancy' => 2, 'state' => self::STATE_BOOKABLE, 'price_total' => 450, 'duration' => 7],
        ];

        $best = $this->reduceToSingleBestPrice($prices);

        $this->assertSame(450.0, (float)$best['price_total']);
    }

    public function testSinglePriceReturnsThatPrice(): void
    {
        $prices = [
            ['occupancy' => 2, 'state' => self::STATE_STOP, 'price_total' => 999, 'duration' => 3],
        ];

        $best = $this->reduceToSingleBestPrice($prices);

        $this->assertSame(self::STATE_STOP, $best['state']);
        $this->assertSame(999, $best['price_total']);
    }

    public function testEmptyPricesReturnsInitialValue(): void
    {
        $best = $this->reduceToSingleBestPrice([]);

        $this->assertSame([], $best, 'Empty input must return the initial value (empty array)');
    }

    public function testComplexMixOccupancyStatePrice(): void
    {
        $prices = [
            ['occupancy' => 1, 'state' => self::STATE_BOOKABLE, 'price_total' => 3399, 'duration' => 7],
            ['occupancy' => 2, 'state' => self::STATE_REQUEST,  'price_total' => 3200, 'duration' => 7],
            ['occupancy' => 2, 'state' => self::STATE_BOOKABLE, 'price_total' => 4899, 'duration' => 7],
        ];

        $best = $this->reduceToSingleBestPrice($prices);

        $this->assertSame(2, $best['occupancy'], 'DZ preferred over EZ');
        $this->assertSame(self::STATE_BOOKABLE, $best['state'], 'Among DZ, bookable preferred over request');
        $this->assertSame(4899.0, (float)$best['price_total']);
    }

    public function testPipelineContainsLetWithOccupancySwitch(): void
    {
        $reduce = $this->extractPriceReduceFromPipeline();
        $this->assertNotNull($reduce);
        $vars = $reduce['in']['$let']['vars'] ?? [];
        $this->assertArrayHasKey('thisOccRank', $vars);
        $thisOccRank = $vars['thisOccRank'];
        $this->assertArrayHasKey('$switch', $thisOccRank);
        $branches = $thisOccRank['$switch']['branches'] ?? [];
        $occ2 = array_filter($branches, function ($b) {
            return isset($b['case']['$eq']) && $b['case']['$eq'] === ['$$this.occupancy', 2];
        });
        $this->assertCount(1, $occ2, 'Pipeline must have branch for occupancy 2 (double room)');
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * Build a minimal aggregation pipeline via the MongoDB search class.
     * Uses reflection to bypass the constructor's Registry config lookups
     * that would require real MongoDB connection URIs.
     */
    private function buildMinimalPipeline(): array
    {
        $config = $this->createMockConfig([
            'data' => [
                'search_mongodb' => [
                    'database' => ['uri' => 'mongodb://localhost:27017', 'db' => 'test'],
                    'search'   => ['allow_invalid_offers' => false],
                ],
                'languages' => ['allowed' => ['de'], 'default' => 'de'],
                'touristic' => ['generate_offer_for_each_startingpoint_option' => false],
            ],
        ]);

        $registry = \Pressmind\Registry::getInstance();
        $registry->add('config', $config);

        $ref = new \ReflectionClass(\Pressmind\Search\MongoDB::class);
        $instance = $ref->newInstanceWithoutConstructor();

        // Set required private properties via reflection
        $props = [
            '_conditions'  => [],
            '_sort'        => ['price_total' => 'asc'],
            '_language'    => null,
            '_origin'      => 0,
            '_agency'      => null,
            '_get_filters' => false,
            '_return_filters_only' => false,
            '_use_opensearch'      => false,
            '_collection_name'     => 'test',
            '_collection_name_description' => 'test_desc',
        ];
        foreach ($props as $name => $value) {
            $p = $ref->getProperty($name);
            $p->setAccessible(true);
            $p->setValue($instance, $value);
        }

        return $instance->buildQuery();
    }

    /**
     * Extract the $reduce definition for the prices field from the pipeline.
     */
    private function extractPriceReduceFromPipeline(): ?array
    {
        $pipeline = $this->buildMinimalPipeline();
        foreach ($pipeline as $stage) {
            if (isset($stage['$project']['prices']['$reduce'])) {
                return $stage['$project']['prices']['$reduce'];
            }
        }
        return null;
    }

    private static function occupancyRank(int $occupancy): int
    {
        if ($occupancy === 2) {
            return 0;
        }
        if ($occupancy === 1) {
            return 1;
        }
        return 2;
    }

    /**
     * PHP re-implementation of the MongoDB $reduce comparator: Occupancy > State > Price > Duration.
     *
     * @param array<int, array{occupancy?: int, state: int, price_total: int|float, duration: int}> $prices
     * @return array The winning price document, or [] if input is empty
     */
    private function reduceToSingleBestPrice(array $prices): array
    {
        $value = [];
        foreach ($prices as $current) {
            $curOcc = isset($current['occupancy']) ? (int)$current['occupancy'] : 2;
            if (empty($value)) {
                $value = $current;
                continue;
            }
            $valOcc = isset($value['occupancy']) ? (int)$value['occupancy'] : 2;
            $curRank = self::occupancyRank($curOcc);
            $valRank = self::occupancyRank($valOcc);
            $replace = false;
            if ($curRank < $valRank) {
                $replace = true;
            } elseif ($curRank === $valRank && (int)$current['state'] < (int)$value['state']) {
                $replace = true;
            } elseif ($curRank === $valRank && (int)$current['state'] === (int)$value['state']
                && (float)$current['price_total'] < (float)$value['price_total']) {
                $replace = true;
            } elseif ($curRank === $valRank && (int)$current['state'] === (int)$value['state']
                && (float)$current['price_total'] === (float)$value['price_total']
                && (int)$current['duration'] > (int)$value['duration']) {
                $replace = true;
            }
            if ($replace) {
                $value = $current;
            }
        }
        return $value;
    }
}
