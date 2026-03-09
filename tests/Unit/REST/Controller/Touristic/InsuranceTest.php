<?php

namespace Pressmind\Tests\Unit\REST\Controller\Touristic;

use Exception;
use Pressmind\Tests\Unit\AbstractTestCase;
use Pressmind\REST\Controller\Touristic\Insurance;

class InsuranceTest extends AbstractTestCase
{
    public function testCalculatePricesThrowsWhenIdMediaObjectMissing(): void
    {
        $controller = new Insurance();
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('id_media_object');
        $controller->calculatePrices([]);
    }

    public function testCalculatePricesThrowsWhenPricePersonMissing(): void
    {
        $controller = new Insurance();
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('price_person');
        $controller->calculatePrices(['id_media_object' => 1]);
    }

    public function testCalculatePricesThrowsWhenDurationNightsMissing(): void
    {
        $controller = new Insurance();
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('duration_nights');
        $controller->calculatePrices([
            'id_media_object' => 1,
            'price_person' => 100,
        ]);
    }

    public function testCalculatePricesThrowsWhenDateStartMissing(): void
    {
        $controller = new Insurance();
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('date_start');
        $controller->calculatePrices([
            'id_media_object' => 1,
            'price_person' => 100,
            'duration_nights' => 7,
        ]);
    }

    public function testCalculatePricesThrowsWhenDateEndMissing(): void
    {
        $controller = new Insurance();
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('date_end');
        $controller->calculatePrices([
            'id_media_object' => 1,
            'price_person' => 100,
            'duration_nights' => 7,
            'date_start' => '2025-06-01',
        ]);
    }

    public function testCalculatePricesThrowsWhenDateStartInvalidFormat(): void
    {
        $controller = new Insurance();
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('date_start');
        $controller->calculatePrices([
            'id_media_object' => 1,
            'price_person' => 100,
            'duration_nights' => 7,
            'date_start' => '01-06-2025',
            'date_end' => '2025-06-08',
        ]);
    }

    public function testCalculatePricesThrowsWhenDateEndInvalidFormat(): void
    {
        $controller = new Insurance();
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('date_end');
        $controller->calculatePrices([
            'id_media_object' => 1,
            'price_person' => 100,
            'duration_nights' => 7,
            'date_start' => '2025-06-01',
            'date_end' => '08/06/2025',
        ]);
    }
}
