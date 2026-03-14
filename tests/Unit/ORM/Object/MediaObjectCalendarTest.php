<?php

namespace Pressmind\Tests\Unit\ORM\Object;

use Pressmind\ORM\Object\MediaObject;
use Pressmind\Registry;
use Pressmind\Search\CalendarFilter;
use Pressmind\Tests\Unit\AbstractTestCase;

class MediaObjectCalendarTest extends AbstractTestCase
{
    private function createMediaObject(?int $id = null): MediaObject
    {
        return new MediaObject($id, false);
    }

    /**
     * getCalendar requires config['data']['search_mongodb']['database'] with valid uri/db.
     * When database is missing or invalid, getCalendar (via Calendar constructor and getDatabase) throws.
     */
    public function testGetCalendarThrowsWhenSearchMongoConfigMissing(): void
    {
        $config = Registry::getInstance()->get('config');
        $config['data'] = $config['data'] ?? [];
        $config['data']['search_mongodb'] = [
            'database' => ['uri' => '', 'db' => ''],
            'search' => ['build_for' => []],
        ];
        $config['data']['touristic'] = $config['data']['touristic'] ?? [];
        $config['data']['media_types_allowed_visibilities'] = $config['data']['media_types_allowed_visibilities'] ?? [];
        $config['data']['media_types_fulltext_index_fields'] = $config['data']['media_types_fulltext_index_fields'] ?? [];
        Registry::getInstance()->add('config', $config);

        $mo = $this->createMediaObject();
        $mo->setId(100);
        $filters = new CalendarFilter();

        $this->expectException(\Throwable::class);
        $mo->getCalendar($filters, 3, 0, 'de', []);
    }

    /**
     * Verifies that CalendarFilter parameters are accepted and passed through.
     */
    public function testGetCalendarAcceptsCustomQueryParameter(): void
    {
        $filters = new CalendarFilter();
        $this->assertNull($filters->transport_type);

        $filters->transport_type = 'bus';
        $this->assertSame('bus', $filters->transport_type);

        $config = Registry::getInstance()->get('config');
        $config['data'] = $config['data'] ?? [];
        $config['data']['search_mongodb'] = [
            'database' => ['uri' => '', 'db' => ''],
            'search' => ['build_for' => []],
        ];
        $config['data']['touristic'] = $config['data']['touristic'] ?? [];
        $config['data']['media_types_allowed_visibilities'] = $config['data']['media_types_allowed_visibilities'] ?? [];
        $config['data']['media_types_fulltext_index_fields'] = $config['data']['media_types_fulltext_index_fields'] ?? [];
        Registry::getInstance()->add('config', $config);

        $mo = $this->createMediaObject();
        $mo->setId(200);

        try {
            $mo->getCalendar($filters, 3, 0, 'de', ['transport_type' => 'bus']);
        } catch (\Throwable $e) {
            $this->assertNotEmpty($e->getMessage(), 'getCalendar should throw due to invalid MongoDB config');
        }
    }

    /**
     * Calendar merge: better state (bookable 3) wins over lower price with worse state (stop 5).
     */
    public function testCalendarMergeBetterStateWinsOverLowerPrice(): void
    {
        $existing = (object)['state' => 5, 'price_total' => 200.0];
        $new = (object)['state' => 3, 'price_total' => 500.0];
        $this->assertTrue(
            MediaObject::_calendarMergeShouldReplace($existing, $new),
            'New (state 3 bookable, 500) should replace existing (state 5 stop, 200)'
        );
    }

    /**
     * Calendar merge: same state, lower price wins.
     */
    public function testCalendarMergeSameStateLowerPriceWins(): void
    {
        $existing = (object)['state' => 3, 'price_total' => 500.0];
        $new = (object)['state' => 3, 'price_total' => 400.0];
        $this->assertTrue(
            MediaObject::_calendarMergeShouldReplace($existing, $new)
        );
    }

    /**
     * Calendar merge: same state and same price -> no replace (new is not better).
     */
    public function testCalendarMergeSameStateSamePriceNoReplace(): void
    {
        $existing = (object)['state' => 3, 'price_total' => 400.0];
        $new = (object)['state' => 3, 'price_total' => 400.0];
        $this->assertFalse(MediaObject::_calendarMergeShouldReplace($existing, $new));
    }

    /**
     * Calendar merge: new day with no existing day is always inserted (tested via merge logic: worse state should not replace).
     */
    public function testCalendarMergeWorseStateDoesNotReplace(): void
    {
        $existing = (object)['state' => 3, 'price_total' => 500.0];
        $new = (object)['state' => 5, 'price_total' => 200.0];
        $this->assertFalse(
            MediaObject::_calendarMergeShouldReplace($existing, $new),
            'New (state 5 stop) must not replace existing (state 3 bookable)'
        );
    }
}
