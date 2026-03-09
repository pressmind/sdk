<?php

namespace Pressmind\Tests\Unit\Search\MongoDB;

use Pressmind\Search\MongoDB\Calendar;
use Pressmind\Tests\Unit\AbstractTestCase;

/**
 * Unit tests for Search\MongoDB\Calendar: getCollectionName (pure logic).
 * No real MongoDB; we use a stub that avoids parent::__construct() so no DB is used.
 */
class CalendarTest extends AbstractTestCase
{
    /**
     * Calendar stub that does not connect to MongoDB; only getCollectionName is tested.
     */
    private function createCalendarStub(): Calendar
    {
        $stub = $this->getMockBuilder(Calendar::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();

        $ref = new \ReflectionClass(Calendar::class);
        $config = [
            'search' => [
                'build_for' => [1 => [['origin' => 0, 'language' => 'de']]],
                'touristic' => ['occupancies' => []],
                'calendar' => [],
            ],
        ];
        $agenciesProp = $ref->getProperty('_agencies');
        $agenciesProp->setAccessible(true);
        $agenciesProp->setValue($stub, [null]);

        $configProp = $ref->getProperty('_config');
        $configProp->setAccessible(true);
        $configProp->setValue($stub, $config);

        return $stub;
    }

    public function testGetCollectionNameDefault(): void
    {
        $calendar = $this->createCalendarStub();
        $name = $calendar->getCollectionName(0, null, null);
        $this->assertSame('calendar_origin_0', $name);
    }

    public function testGetCollectionNameWithLanguage(): void
    {
        $calendar = $this->createCalendarStub();
        $name = $calendar->getCollectionName(0, 'de', null);
        $this->assertSame('calendar_de_origin_0', $name);
    }

    public function testGetCollectionNameWithAgency(): void
    {
        $calendar = $this->createCalendarStub();
        $name = $calendar->getCollectionName(1, 'en', 'AG1');
        $this->assertSame('calendar_en_origin_1_agency_AG1', $name);
    }
}
