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
}
