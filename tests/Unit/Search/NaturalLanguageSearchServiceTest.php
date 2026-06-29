<?php

declare(strict_types=1);

namespace Pressmind\Tests\Unit\Search;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Pressmind\Search\NaturalLanguageSearchService;

final class NaturalLanguageSearchServiceTest extends TestCase
{
    public function testCreatesPlanWithConfiguredCategoryTerms(): void
    {
        $service = new NaturalLanguageSearchService([
            'now' => new DateTimeImmutable('2026-06-25 12:00:00'),
            'category_terms' => [
                [
                    'field' => 'zielgebiet_fluss_default',
                    'id' => 'cat-rhein',
                    'name' => 'Rhein',
                ],
            ],
        ]);

        $plan = $service->plan('Rhein im Juli bis 1500 Euro');

        self::assertSame('cat-rhein', $plan['request']['pm-c']['zielgebiet_fluss_default']);
        self::assertSame('20260701-20260731', $plan['request']['pm-dr']);
        self::assertSame('1-1500', $plan['request']['pm-pr']);
    }

    public function testCreatesObjectTypeFilterOnlyWithConfiguredObjectTypeTerms(): void
    {
        $service = new NaturalLanguageSearchService([
            'now' => new DateTimeImmutable('2026-06-25 12:00:00'),
            'category_terms' => [
                [
                    'field' => 'unmatched',
                    'id' => 'unmatched',
                    'name' => 'Unmatched',
                ],
            ],
            'object_type_terms' => [
                [
                    'id' => '1001',
                    'name' => 'Schiff',
                    'terms' => ['schiffe', 'schiff'],
                ],
            ],
        ]);

        $withMapping = $service->plan('Schiffe Weihnachten');
        self::assertSame('1001', $withMapping['request']['pm-ot']);

        $withoutMapping = (new NaturalLanguageSearchService([
            'now' => new DateTimeImmutable('2026-06-25 12:00:00'),
            'category_terms' => [],
        ]))->plan('Schiffe Weihnachten');
        self::assertArrayNotHasKey('pm-ot', $withoutMapping['request']);
    }

    public function testPerCallArrayOptionsReplaceConstructorDefaults(): void
    {
        $service = new NaturalLanguageSearchService([
            'now' => new DateTimeImmutable('2026-06-25 12:00:00'),
            'category_terms' => [
                [
                    'field' => 'zielgebiet_fluss_default',
                    'id' => 'cat-rhein',
                    'name' => 'Rhein',
                ],
            ],
            'object_type_terms' => [
                [
                    'id' => '1001',
                    'name' => 'Schiff',
                    'terms' => ['schiffe'],
                ],
            ],
        ]);

        $plan = $service->plan('Rhein Schiffe', [
            'category_terms' => [],
            'object_type_terms' => [],
        ]);

        self::assertArrayNotHasKey('pm-c', $plan['request']);
        self::assertArrayNotHasKey('pm-ot', $plan['request']);
    }

    public function testSearchUsesConstructorOptionsForFilterSettings(): void
    {
        $service = new NaturalLanguageSearchService([
            'category_terms' => [],
            'page_size' => 7,
            'return_filters_only' => true,
        ]);

        $result = $service->search('Sommerurlaub');

        self::assertSame(7, $result['result']['page_size']);
    }
}
