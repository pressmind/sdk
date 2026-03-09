<?php

namespace Pressmind\Tests\Unit\Search;

use PHPUnit\Framework\TestCase;
use Pressmind\Search\CalendarFilter;

class CalendarFilterTest extends TestCase
{
    private array $getBackup = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->getBackup = $_GET;
        $_GET = [];
    }

    protected function tearDown(): void
    {
        $_GET = $this->getBackup;
        parent::tearDown();
    }

    public function testDefaultPropertiesAreNull(): void
    {
        $filter = new CalendarFilter();
        $this->assertNull($filter->id);
        $this->assertNull($filter->id_booking_package);
        $this->assertNull($filter->housing_package_code_ibe);
        $this->assertNull($filter->occupancy);
        $this->assertNull($filter->transport_type);
        $this->assertNull($filter->duration);
        $this->assertNull($filter->airport);
        $this->assertNull($filter->startingpoint_id_city);
        $this->assertNull($filter->housing_package_id_name);
        $this->assertNull($filter->id_housing_package);
        $this->assertNull($filter->agency);
    }

    public function testInitFromGetReturnsFalseWhenGetIsEmpty(): void
    {
        $filter = new CalendarFilter();
        $this->assertFalse($filter->initFromGet());
    }

    public function testInitFromGetReturnsTrueWhenParamIsSet(): void
    {
        $_GET = ['id' => '42'];
        $filter = new CalendarFilter();
        $this->assertTrue($filter->initFromGet());
        $this->assertSame('42', $filter->id);
    }

    public function testInitFromGetSanitizesSpecialCharacters(): void
    {
        $_GET = [
            'id' => '123<script>alert(1)</script>',
            'agency' => 'foo bar&baz=1',
        ];
        $filter = new CalendarFilter();
        $filter->initFromGet();
        $this->assertSame('123scriptalert1script', $filter->id);
        $this->assertSame('foobarbaz1', $filter->agency);
    }

    public function testInitFromGetSetsMultipleParams(): void
    {
        $_GET = [
            'id' => '99',
            'id_booking_package' => '200',
            'occupancy' => '2',
            'transport_type' => 'BUS',
            'duration' => '7',
            'airport' => 'FRA',
            'startingpoint_id_city' => '500',
            'housing_package_id_name' => 'DZ',
            'id_housing_package' => '10',
            'agency' => 'test-agency',
            'housing_package_code_ibe' => 'HP-001',
        ];
        $filter = new CalendarFilter();
        $result = $filter->initFromGet();

        $this->assertTrue($result);
        $this->assertSame('99', $filter->id);
        $this->assertSame('200', $filter->id_booking_package);
        $this->assertSame('2', $filter->occupancy);
        $this->assertSame('BUS', $filter->transport_type);
        $this->assertSame('7', $filter->duration);
        $this->assertSame('FRA', $filter->airport);
        $this->assertSame('500', $filter->startingpoint_id_city);
        $this->assertSame('DZ', $filter->housing_package_id_name);
        $this->assertSame('10', $filter->id_housing_package);
        $this->assertSame('test-agency', $filter->agency);
        $this->assertSame('HP-001', $filter->housing_package_code_ibe);
    }

    public function testInitFromGetIgnoresNonWhitelistedParams(): void
    {
        $_GET = [
            'id' => '1',
            'evil_param' => 'should_be_ignored',
            'password' => 'secret',
        ];
        $filter = new CalendarFilter();
        $result = $filter->initFromGet();

        $this->assertTrue($result);
        $this->assertSame('1', $filter->id);
        $this->assertObjectNotHasProperty('evil_param', $filter);
        $this->assertObjectNotHasProperty('password', $filter);
    }
}
