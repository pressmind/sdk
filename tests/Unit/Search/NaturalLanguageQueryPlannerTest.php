<?php

declare(strict_types=1);

namespace Pressmind\Tests\Unit\Search;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Pressmind\Search\NaturalLanguageQueryPlanner;

final class NaturalLanguageQueryPlannerTest extends TestCase
{
    private NaturalLanguageQueryPlanner $planner;

    protected function setUp(): void
    {
        $this->planner = new NaturalLanguageQueryPlanner([
            'now' => new DateTimeImmutable('2026-06-25 12:00:00'),
            'object_type_terms' => [
                [
                    'id' => '1001',
                    'name' => 'Schiff',
                    'terms' => ['schiffssuche', 'schiffe', 'schiff'],
                ],
                [
                    'id' => '1002',
                    'name' => 'Ausflug',
                    'terms' => ['tagesausflug', 'ausfluege', 'ausflug'],
                ],
                [
                    'id' => '1003',
                    'name' => 'Reise',
                    'terms' => ['flussreise', 'flusskreuzfahrt', 'kreuzfahrt', 'reisen', 'reise'],
                ],
            ],
            'category_terms' => [
                [
                    'field' => 'zielgebiet_fluss_default',
                    'id' => 'cat-donau',
                    'name' => 'Donau',
                ],
                [
                    'field' => 'zielgebiet_fluss_default',
                    'id' => 'cat-rhein',
                    'name' => 'Rhein',
                ],
                [
                    'field' => 'reiseart_default',
                    'id' => 'cat-flusskreuzfahrt',
                    'name' => 'Flusskreuzfahrt',
                    'aliases' => ['Kreuzfahrt'],
                ],
                [
                    'field' => 'schiff_default',
                    'id' => 'cat-ms-thurgau-gold',
                    'name' => 'MS Thurgau Gold',
                    'aliases' => ['Thurgau Gold'],
                ],
                [
                    'field' => 'sterne_schiffskategorie_default',
                    'id' => 'cat-4-sterne',
                    'name' => '4 Sterne',
                    'aliases' => ['4 Sternen'],
                ],
                [
                    'field' => 'zielgebiet_land_default',
                    'id' => 'cat-belgien',
                    'name' => 'Belgien',
                ],
                [
                    'field' => 'zielgebiet_land_default',
                    'id' => 'cat-frankreich',
                    'name' => 'Frankreich',
                ],
                [
                    'field' => 'reiseart_default',
                    'id' => 'cat-flussreise',
                    'name' => 'Flussreise',
                ],
                [
                    'field' => 'reisethema_default',
                    'id' => 'cat-familienreisen',
                    'name' => 'Familienreisen',
                ],
                [
                    'field' => 'reisethema_default',
                    'id' => 'cat-kulturreisen',
                    'name' => 'Kulturreisen',
                ],
            ],
        ]);
    }

    public function testBuildsHybridSearchPlanWithHardFiltersAndResidualSemanticQuery(): void
    {
        $plan = $this->planner->plan('Romantische Donau Kreuzfahrt im September bis 2000 Euro für 2 Personen');

        self::assertSame('semantic_hybrid', $plan['mode']);
        self::assertSame('romantische', $plan['semantic_query']);
        self::assertSame('romantische', $plan['request']['pm-t']);
        self::assertSame('20260901-20260930', $plan['request']['pm-dr']);
        self::assertSame('1-2000', $plan['request']['pm-pr']);
        self::assertSame('2', $plan['request']['pm-ho']);
        self::assertSame('list', $plan['request']['pm-o']);
        self::assertSame('cat-donau', $plan['request']['pm-c']['zielgebiet_fluss_default']);
        self::assertSame('cat-flusskreuzfahrt', $plan['request']['pm-c']['reiseart_default']);
    }

    public function testBuildsStructuredPlanWhenQuestionContainsOnlyFilters(): void
    {
        $plan = $this->planner->plan('MS Thurgau Gold im Juli 7 Tage');

        self::assertSame('structured', $plan['mode']);
        self::assertNull($plan['semantic_query']);
        self::assertArrayNotHasKey('pm-t', $plan['request']);
        self::assertSame('20260701-20260731', $plan['request']['pm-dr']);
        self::assertSame('7-7', $plan['request']['pm-du']);
        self::assertSame('cat-ms-thurgau-gold', $plan['request']['pm-c']['schiff_default']);
        self::assertSame('list', $plan['request']['pm-o']);
    }

    public function testMapsShipIntentChristmasAndCategoryTerms(): void
    {
        $plan = $this->planner->plan('Schiffe mit 4 Sternen Weihnachten');

        self::assertSame('1001', $plan['request']['pm-ot']);
        self::assertSame('20261220-20261231', $plan['request']['pm-dr']);
        self::assertSame('cat-4-sterne', $plan['request']['pm-c']['sterne_schiffskategorie_default']);
        self::assertSame('list', $plan['request']['pm-o']);
    }

    public function testDoesNotInferObjectTypeWithoutConfiguredTerms(): void
    {
        $planner = new NaturalLanguageQueryPlanner([
            'now' => new DateTimeImmutable('2026-06-25 12:00:00'),
        ]);

        $plan = $planner->plan('Schiffe Weihnachten');

        self::assertArrayNotHasKey('pm-ot', $plan['request']);
        self::assertSame('schiffe', $plan['semantic_query']);
    }

    public function testMapsFamilyRiverTravelFromAugustToStructuredFilters(): void
    {
        $plan = $this->planner->plan('Familienurlaub auf dem fluss ab august in belgien');

        self::assertSame('semantic_hybrid', $plan['mode']);
        self::assertSame('familienurlaub', $plan['semantic_query']);
        self::assertSame('familienurlaub', $plan['request']['pm-t']);
        self::assertSame('20260801-20280731', $plan['request']['pm-dr']);
        self::assertSame('cat-belgien', $plan['request']['pm-c']['zielgebiet_land_default']);
        self::assertSame('cat-flussreise', $plan['request']['pm-c']['reiseart_default']);
        self::assertArrayNotHasKey('reisethema_default', $plan['request']['pm-c']);
    }

    public function testMapsSummerVacationWithPartnerAndChildrenToDateAndAdultOccupancyFilters(): void
    {
        $plan = $this->planner->plan('Sommerurlaub mit Frau und 2 Kindern in Frankreich');

        self::assertSame('structured', $plan['mode']);
        self::assertNull($plan['semantic_query']);
        self::assertArrayNotHasKey('pm-t', $plan['request']);
        self::assertSame('20260601-20260831', $plan['request']['pm-dr']);
        self::assertSame('2', $plan['request']['pm-ho']);
        self::assertArrayNotHasKey('pm-hoc', $plan['request']);
        self::assertSame('cat-frankreich', $plan['request']['pm-c']['zielgebiet_land_default']);
        self::assertNotEmpty($plan['warnings']);
    }

    public function testDropsConversationalPromptWordsFromResidualSemanticQuery(): void
    {
        $plan = $this->planner->plan('Ich möchte gerne im Sommer mit meiner Familie eine Flusskreuzfahrt machen, schlage mir was vor');

        self::assertSame('semantic_hybrid', $plan['mode']);
        self::assertSame('familie', $plan['semantic_query']);
        self::assertSame('familie', $plan['request']['pm-t']);
        self::assertSame('20260601-20260831', $plan['request']['pm-dr']);
        self::assertSame('cat-flusskreuzfahrt', $plan['request']['pm-c']['reiseart_default']);
    }

    public function testMapsWinterFamilyQuestionToSeasonAndShortResidualSemanticQuery(): void
    {
        $plan = $this->planner->plan('Ich und meine Familien mit 2 Kindern wollen im Winter eine Flusskreuzfahrt machen, welche Reisen gibt es da?');

        self::assertSame('semantic_hybrid', $plan['mode']);
        self::assertSame('familien', $plan['semantic_query']);
        self::assertSame('familien', $plan['request']['pm-t']);
        self::assertSame('20261201-20270228', $plan['request']['pm-dr']);
        self::assertSame('cat-flusskreuzfahrt', $plan['request']['pm-c']['reiseart_default']);
        self::assertSame('1003', $plan['request']['pm-ot']);
    }

    public function testKeepsLargeOccupancySoftToAvoidOverFiltering(): void
    {
        $plan = $this->planner->plan('Sommerurlaub in Frankreich für 4 Personen');

        self::assertSame('structured', $plan['mode']);
        self::assertNull($plan['semantic_query']);
        self::assertArrayNotHasKey('pm-t', $plan['request']);
        self::assertArrayNotHasKey('pm-ho', $plan['request']);
        self::assertSame('20260601-20260831', $plan['request']['pm-dr']);
        self::assertSame('cat-frankreich', $plan['request']['pm-c']['zielgebiet_land_default']);
        self::assertSame('occupancy_softened', $plan['warnings'][0]['type']);
    }

    public function testMapsCommonTravelTermsToStructuredFiltersAndResidualSemanticQuery(): void
    {
        $plan = $this->planner->plan('Kulturreise in Belgien im Oktober');

        self::assertSame('semantic_hybrid', $plan['mode']);
        self::assertSame('kulturreise', $plan['semantic_query']);
        self::assertSame('kulturreise', $plan['request']['pm-t']);
        self::assertSame('20261001-20261031', $plan['request']['pm-dr']);
        self::assertSame('cat-belgien', $plan['request']['pm-c']['zielgebiet_land_default']);
        self::assertArrayNotHasKey('reisethema_default', $plan['request']['pm-c']);
    }

    public function testKeepsQualitativeShipRequestsSemantic(): void
    {
        $plan = $this->planner->plan('Komfortables kleines Schiff Frankreich');

        self::assertSame('semantic_hybrid', $plan['mode']);
        self::assertSame('komfortables kleines schiff', $plan['semantic_query']);
        self::assertSame('komfortables kleines schiff', $plan['request']['pm-t']);
        self::assertArrayNotHasKey('pm-ot', $plan['request']);
        self::assertSame('cat-frankreich', $plan['request']['pm-c']['zielgebiet_land_default']);
    }

    public function testRequiresCurrencyForOpenEndedPriceFrom(): void
    {
        $withoutCurrency = $this->planner->plan('Flussreise ab 3000');
        self::assertArrayNotHasKey('pm-pr', $withoutCurrency['request']);

        $withCurrency = $this->planner->plan('Flussreise ab 3000 Euro');
        self::assertSame('3000-9999999', $withCurrency['request']['pm-pr']);
    }

    public function testMapsAdditionalCommonTravelQueries(): void
    {
        $rhein = $this->planner->plan('Rhein Flussreise ab Mai 2027 unter 1500 Euro');
        self::assertSame('20270501-20290430', $rhein['request']['pm-dr']);
        self::assertSame('1-1500', $rhein['request']['pm-pr']);
        self::assertSame('cat-rhein', $rhein['request']['pm-c']['zielgebiet_fluss_default']);
        self::assertSame('cat-flussreise', $rhein['request']['pm-c']['reiseart_default']);

        $alone = $this->planner->plan('Allein auf der Donau im März');
        self::assertSame('1', $alone['request']['pm-ho']);
        self::assertSame('20270301-20270331', $alone['request']['pm-dr']);
        self::assertSame('cat-donau', $alone['request']['pm-c']['zielgebiet_fluss_default']);

        $family = $this->planner->plan('2 Erwachsene und 1 Kind Frankreich 10 bis 14 Tage');
        self::assertSame('2', $family['request']['pm-ho']);
        self::assertArrayNotHasKey('pm-hoc', $family['request']);
        self::assertSame('10-14', $family['request']['pm-du']);
        self::assertSame('cat-frankreich', $family['request']['pm-c']['zielgebiet_land_default']);
        self::assertSame('child_occupancy_softened', $family['warnings'][0]['type']);

        $advent = $this->planner->plan('Advent auf dem Rhein');
        self::assertSame('semantic_hybrid', $advent['mode']);
        self::assertSame('advent', $advent['request']['pm-t']);
        self::assertSame('cat-rhein', $advent['request']['pm-c']['zielgebiet_fluss_default']);
    }
}
