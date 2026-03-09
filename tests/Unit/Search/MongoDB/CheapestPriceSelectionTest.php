<?php

namespace Pressmind\Tests\Unit\Search\MongoDB;

use Pressmind\Tests\Unit\AbstractTestCase;

/**
 * Tests for the cheapest-price selection logic used in the MongoDB aggregation pipeline.
 *
 * The MongoDB\Search::buildQuery() method generates a $reduce stage that picks the best
 * price per product. Because the actual $reduce runs inside MongoDB, these tests verify:
 *  1. The pipeline structure contains the expected $reduce operator.
 *  2. A PHP re-implementation of the $reduce comparator produces the correct winner
 *     for various state / price / duration combinations.
 *
 * State codes used in the price documents (lower value = higher priority in $lt comparison):
 *   REQUEST = 1, BOOKABLE = 3, STOP = 5
 */
class CheapestPriceSelectionTest extends AbstractTestCase
{
    private const STATE_REQUEST  = 1;
    private const STATE_BOOKABLE = 3;
    private const STATE_STOP     = 5;

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

    public function testReduceStageUsesLtForStateComparison(): void
    {
        $reduce = $this->extractPriceReduceFromPipeline();
        $this->assertNotNull($reduce, '$reduce definition must exist in the prices projection');

        $condIf = $reduce['in']['$cond']['if']['$or'] ?? [];

        $stateLt = $condIf[0] ?? [];
        $this->assertArrayHasKey('$lt', $stateLt, 'First $or branch must use $lt for state comparison');
        $this->assertSame(
            ['$$this.state', '$$value.state'],
            $stateLt['$lt'],
            '$lt operands must compare $$this.state against $$value.state'
        );
    }

    public function testReduceStagePrefersSamePriceLongerDuration(): void
    {
        $reduce = $this->extractPriceReduceFromPipeline();
        $condIf = $reduce['in']['$cond']['if']['$or'] ?? [];

        $durationBranch = $condIf[2]['$and'] ?? [];

        $this->assertNotEmpty($durationBranch, 'Third $or branch must handle same-state-same-price tiebreak');

        $gtFound = false;
        foreach ($durationBranch as $expr) {
            if (isset($expr['$gt']) && $expr['$gt'] === ['$$this.duration', '$$value.duration']) {
                $gtFound = true;
            }
        }
        $this->assertTrue($gtFound, 'Duration tiebreak must use $gt to prefer longer duration');
    }

    // ------------------------------------------------------------------
    // Behavioural tests (PHP re-implementation of the $reduce comparator)
    // ------------------------------------------------------------------

    public function testStatePriorityLowerStateWins(): void
    {
        $prices = [
            ['state' => self::STATE_BOOKABLE, 'price_total' => 500, 'duration' => 7],
            ['state' => self::STATE_REQUEST,  'price_total' => 500, 'duration' => 7],
        ];

        $best = $this->reduceToSingleBestPrice($prices);

        $this->assertSame(self::STATE_REQUEST, $best['state'], 'Lower state value must win (REQUEST < BOOKABLE)');
    }

    public function testStatePriorityBookableOverStop(): void
    {
        $prices = [
            ['state' => self::STATE_STOP,     'price_total' => 300, 'duration' => 7],
            ['state' => self::STATE_BOOKABLE, 'price_total' => 600, 'duration' => 7],
        ];

        $best = $this->reduceToSingleBestPrice($prices);

        $this->assertSame(self::STATE_BOOKABLE, $best['state'], 'BOOKABLE (3) must beat STOP (5) regardless of price');
    }

    public function testStatePrioritySameStateLowerPriceWins(): void
    {
        $prices = [
            ['state' => self::STATE_BOOKABLE, 'price_total' => 800, 'duration' => 7],
            ['state' => self::STATE_BOOKABLE, 'price_total' => 450, 'duration' => 7],
            ['state' => self::STATE_BOOKABLE, 'price_total' => 600, 'duration' => 7],
        ];

        $best = $this->reduceToSingleBestPrice($prices);

        $this->assertSame(450.0, (float)$best['price_total'], 'Same state: lowest price_total must win');
    }

    public function testStatePrioritySamePricePreferLongerDuration(): void
    {
        $prices = [
            ['state' => self::STATE_BOOKABLE, 'price_total' => 500, 'duration' => 5],
            ['state' => self::STATE_BOOKABLE, 'price_total' => 500, 'duration' => 10],
            ['state' => self::STATE_BOOKABLE, 'price_total' => 500, 'duration' => 7],
        ];

        $best = $this->reduceToSingleBestPrice($prices);

        $this->assertSame(10, $best['duration'], 'Same state+price: longest duration must win');
    }

    public function testSinglePriceReturnsThatPrice(): void
    {
        $prices = [
            ['state' => self::STATE_STOP, 'price_total' => 999, 'duration' => 3],
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

    public function testComplexMixPicksCorrectWinner(): void
    {
        $prices = [
            ['state' => self::STATE_STOP,     'price_total' => 100, 'duration' => 14],
            ['state' => self::STATE_BOOKABLE, 'price_total' => 350, 'duration' => 7],
            ['state' => self::STATE_REQUEST,  'price_total' => 700, 'duration' => 5],
            ['state' => self::STATE_REQUEST,  'price_total' => 400, 'duration' => 10],
            ['state' => self::STATE_REQUEST,  'price_total' => 400, 'duration' => 14],
        ];

        $best = $this->reduceToSingleBestPrice($prices);

        $this->assertSame(self::STATE_REQUEST, $best['state'], 'REQUEST (1) must win over BOOKABLE (3) and STOP (5)');
        $this->assertSame(400, $best['price_total'], 'Among REQUEST prices, 400 is the lowest');
        $this->assertSame(14, $best['duration'], 'Among REQUEST prices at 400, duration 14 is the longest');
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

    /**
     * PHP re-implementation of the MongoDB $reduce comparator from buildQuery().
     *
     * Rules (evaluated in order):
     *  1. Lower state value wins           ($lt on state)
     *  2. Same state → lower price_total   ($lt on price_total)
     *  3. Same state + price → longer dur. ($gt on duration)
     *
     * @param array<int, array{state: int, price_total: int|float, duration: int}> $prices
     * @return array The winning price document, or [] if input is empty
     */
    private function reduceToSingleBestPrice(array $prices): array
    {
        $value = [];
        foreach ($prices as $current) {
            if (empty($value)) {
                $value = $current;
                continue;
            }
            $replace = false;
            if ($current['state'] < $value['state']) {
                $replace = true;
            } elseif ($current['state'] === $value['state'] && $current['price_total'] < $value['price_total']) {
                $replace = true;
            } elseif ($current['state'] === $value['state']
                && $current['price_total'] === $value['price_total']
                && $current['duration'] > $value['duration']) {
                $replace = true;
            }
            if ($replace) {
                $value = $current;
            }
        }
        return $value;
    }
}
