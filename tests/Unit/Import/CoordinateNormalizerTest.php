<?php

namespace Pressmind\Tests\Unit\Import;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Pressmind\Import\CoordinateNormalizer;

class CoordinateNormalizerTest extends TestCase
{
    public function testNormalizesObjectsAndNumericStrings(): void
    {
        $this->assertSame(
            ['lat' => 53.5439, 'lng' => 9.9666],
            CoordinateNormalizer::normalize((object) ['lat' => '53.5439', 'lng' => '9.9666'])
        );
    }

    public function testPreservesZeroCoordinatesAndNull(): void
    {
        $this->assertSame(['lat' => 0.0, 'lng' => 0.0], CoordinateNormalizer::normalize(['lat' => 0, 'lng' => 0]));
        $this->assertNull(CoordinateNormalizer::normalize(null));
    }

    public function testRejectsIncompleteCoordinates(): void
    {
        $this->expectException(InvalidArgumentException::class);
        CoordinateNormalizer::normalize(['lat' => 53.5439]);
    }

    public function testRejectsOutOfRangeCoordinates(): void
    {
        $this->expectException(InvalidArgumentException::class);
        CoordinateNormalizer::normalize(['lat' => 53.5439, 'lng' => 181]);
    }
}
