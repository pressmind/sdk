<?php

namespace Pressmind\Tests\Unit\ORM\Object;

use Pressmind\DB\Adapter\AdapterInterface;
use Pressmind\ORM\Object\MediaObject;
use Pressmind\ORM\Object\Touristic\Booking\Package;
use Pressmind\ORM\Object\Touristic\Date;
use Pressmind\Registry;
use Pressmind\Tests\Unit\AbstractTestCase;
use stdClass;

class MediaObjectValidationTest extends AbstractTestCase
{
    private function createMediaObject(?int $id = null): MediaObject
    {
        return new MediaObject($id, false);
    }

    // --- isAPrimaryType / isAPrimaryObject ---

    public function testIsAPrimaryTypeReturnsTrueWhenInConfig(): void
    {
        $config = Registry::getInstance()->get('config');
        $config['data'] = $config['data'] ?? [];
        $config['data']['primary_media_type_ids'] = [1, 2, 3];
        Registry::getInstance()->add('config', $config);

        $mo = $this->createMediaObject();
        $mo->id_object_type = 2;
        $this->assertTrue($mo->isAPrimaryType());
    }

    public function testIsAPrimaryTypeReturnsFalseWhenNotInConfig(): void
    {
        $config = Registry::getInstance()->get('config');
        $config['data'] = $config['data'] ?? [];
        $config['data']['primary_media_type_ids'] = [1, 2, 3];
        Registry::getInstance()->add('config', $config);

        $mo = $this->createMediaObject();
        $mo->id_object_type = 99;
        $this->assertFalse($mo->isAPrimaryType());
    }

    public function testIsAPrimaryObjectReturnsTrueWhenInConfig(): void
    {
        $config = Registry::getInstance()->get('config');
        $config['data'] = $config['data'] ?? [];
        $config['data']['primary_media_type_ids'] = [10, 20];
        Registry::getInstance()->add('config', $config);

        $mo = $this->createMediaObject();
        $mo->id_object_type = 10;
        $this->assertTrue($mo->isAPrimaryObject());
    }

    public function testIsAPrimaryObjectReturnsFalseWhenNotInConfig(): void
    {
        $config = Registry::getInstance()->get('config');
        $config['data'] = $config['data'] ?? [];
        $config['data']['primary_media_type_ids'] = [10, 20];
        Registry::getInstance()->add('config', $config);

        $mo = $this->createMediaObject();
        $mo->id_object_type = 99;
        $this->assertFalse($mo->isAPrimaryObject());
    }

    // --- hasOffers ---

    public function testHasOffersReturnsFalseWhenNoCheapestPriceSpeed(): void
    {
        $mo = $this->createMediaObject();
        $mo->setId(100);
        $this->assertFalse($mo->hasOffers());
    }

    public function testHasOffersReturnsTrueWhenCheapestPriceSpeedExists(): void
    {
        $row = new stdClass();
        $row->id = 1;
        $row->id_media_object = 100;

        $db = $this->createMock(AdapterInterface::class);
        $db->method('fetchAll')->willReturn([$row]);
        $db->method('fetchRow')->willReturn(null);
        $db->method('fetchOne')->willReturn(null);
        $db->method('getAffectedRows')->willReturn(0);
        $db->method('getTablePrefix')->willReturn('pmt2core_');
        $db->method('inTransaction')->willReturn(false);
        $db->method('execute')->willReturn(null);
        $db->method('insert')->willReturn(null);
        $db->method('replace')->willReturn(null);
        $db->method('update')->willReturn(null);
        $db->method('delete')->willReturn(null);
        $db->method('truncate')->willReturn(null);
        $db->method('batchInsert')->willReturn(1);
        $db->method('beginTransaction')->willReturn(null);
        $db->method('commit')->willReturn(null);
        $db->method('rollback')->willReturn(null);

        Registry::getInstance()->add('db', $db);

        $mo = $this->createMediaObject();
        $mo->setId(100);
        $this->assertTrue($mo->hasOffers());
    }

    // --- validateBookingPackages ---

    public function testValidateBookingPackagesReturnsErrorWhenNoPackages(): void
    {
        $mo = $this->createMediaObject();
        $mo->setId(100);
        $result = $mo->validateBookingPackages();
        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertStringContainsString('No Booking Packages found', $result[0]);
    }

    public function testValidateBookingPackagesWithPrefixIncludesPrefix(): void
    {
        $mo = $this->createMediaObject();
        $mo->setId(100);
        $result = $mo->validateBookingPackages('  >> ');
        $this->assertStringContainsString('  >> ', $result[0]);
    }

    // --- validate ---

    public function testValidateReturnsEarlyForNonPrimaryObject(): void
    {
        $config = Registry::getInstance()->get('config');
        $config['data'] = $config['data'] ?? [];
        $config['data']['primary_media_type_ids'] = [1];
        Registry::getInstance()->add('config', $config);

        $mo = $this->createMediaObject();
        $mo->setId(100);
        $mo->name = 'Non-Primary Test';
        $mo->id_object_type = 99;
        $result = $mo->validate();
        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertStringContainsString('Not a primary Object', $result[1]);
    }

    // --- render ---

    public function testRenderWithoutCacheCallsViewRender(): void
    {
        $config = Registry::getInstance()->get('config');
        $config['cache'] = ['enabled' => false, 'types' => []];
        $config['data'] = $config['data'] ?? [];
        $config['data']['media_types'] = [1 => 'Reise'];
        $config['view_scripts'] = ['base_path' => sys_get_temp_dir()];
        Registry::getInstance()->add('config', $config);

        $mo = $this->createMediaObject();
        $mo->setId(1);
        $mo->id_object_type = 1;

        $viewScript = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'Reise_Detail.php';
        file_put_contents($viewScript, '<?php echo "rendered";');

        try {
            $result = $mo->render('Detail');
            $this->assertSame('rendered', $result);
        } finally {
            @unlink($viewScript);
        }
    }

    // --- getExtraOptions ---

    public function testGetExtraOptionsReturnsEmptyWhenNoBookingPackages(): void
    {
        $mo = $this->createMediaObject();
        $mo->setId(100);
        $mo->booking_packages = [];
        $result = $mo->getExtraOptions();
        $this->assertIsArray($result);
        $this->assertCount(0, $result);
    }

    private function createOptionStub(string $name, float $price, int $order, int $auto_book = 0, int $required = 0, string $description_long = ''): stdClass
    {
        $opt = new stdClass();
        $opt->name = $name;
        $opt->description_long = $description_long;
        $opt->order = $order;
        $opt->price = $price;
        $opt->auto_book = $auto_book;
        $opt->required = $required;
        return $opt;
    }

    private function createMediaObjectWithOptions(array $options, ?string $price_mix = null, int $duration = 7): MediaObject
    {
        $date = $this->createMock(Date::class);
        $date->departure = '2026-06-01';
        $date->method('getAllOptionsButExcludePriceMixOptions')->willReturn($options);

        $package = new Package(null, false);
        $package->price_mix = $price_mix;
        $package->duration = $duration;
        $package->dates = [$date];

        $mo = $this->createMediaObject();
        $mo->setId(100);
        $mo->booking_packages = [$package];
        return $mo;
    }

    public function testGetExtraOptionsGroupsByOptionName(): void
    {
        $options = [
            $this->createOptionStub('Transfer', 25.00, 1, 0, 0, 'Airport transfer'),
            $this->createOptionStub('Transfer', 30.00, 1, 0, 0, 'Airport transfer'),
            $this->createOptionStub('Insurance', 15.00, 2, 0, 0, 'Travel insurance'),
        ];
        $mo = $this->createMediaObjectWithOptions($options);
        $result = $mo->getExtraOptions();

        $this->assertCount(2, $result);

        $names = array_column($result, 'name');
        $this->assertContains('Transfer', $names);
        $this->assertContains('Insurance', $names);
    }

    public function testGetExtraOptionsFiltersAutoBookZeroPrice(): void
    {
        $options = [
            $this->createOptionStub('Free Extra', 0, 1, 1, 0),
            $this->createOptionStub('Paid Extra', 50.00, 2),
        ];
        $mo = $this->createMediaObjectWithOptions($options);
        $result = $mo->getExtraOptions(null, true);

        $this->assertCount(1, $result);
        $this->assertSame('Paid Extra', $result[0]['name']);
    }

    public function testGetExtraOptionsFiltersAutoBookAndRequired(): void
    {
        $options = [
            $this->createOptionStub('Mandatory Extra', 20.00, 1, 1, 1),
            $this->createOptionStub('Optional Extra', 50.00, 2),
        ];
        $mo = $this->createMediaObjectWithOptions($options);
        $result = $mo->getExtraOptions(null, false, true);

        $this->assertCount(1, $result);
        $this->assertSame('Optional Extra', $result[0]['name']);
    }
}
