<?php

namespace Pressmind\Tests\Unit\ORM\Object;

use DateTime;
use Pressmind\DB\Adapter\AdapterInterface;
use Pressmind\ORM\Object\MediaObject;
use Pressmind\ORM\Object\Touristic\Booking\Package;
use Pressmind\ORM\Object\Touristic\Date;
use Pressmind\ORM\Object\Touristic\Date\LoyaltyPoints;
use Pressmind\ORM\Object\Touristic\Transport;
use Pressmind\Registry;
use Pressmind\Tests\Unit\AbstractTestCase;

/**
 * Guards the loyalty points that insertCheapestPrice() writes per cheapest price row.
 *
 * Same setup as MediaObjectAllowZeroPricesTest: one date_transport booking package with one date
 * and an outward and return transport, assigned in memory. The mocked database records the rows
 * written to pmt2core_cheapest_price_speed.
 */
class MediaObjectLoyaltyPointsTest extends AbstractTestCase
{
    /**
     * @var array[] rows passed to batchInsert() for pmt2core_cheapest_price_speed
     */
    private $insertedRows = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->insertedRows = [];
        Registry::getInstance()->add('db', $this->createRecordingDb());
    }

    private function createRecordingDb(): AdapterInterface
    {
        $db = $this->createMock(AdapterInterface::class);
        $db->method('fetchAll')->willReturn([]);
        $db->method('fetchRow')->willReturn(null);
        $db->method('fetchOne')->willReturn(null);
        $db->method('getAffectedRows')->willReturn(0);
        $db->method('getTablePrefix')->willReturn('pmt2core_');
        $db->method('inTransaction')->willReturn(false);
        $db->method('delete')->willReturn(null);
        $db->method('batchInsert')->willReturnCallback(function ($table, $columns, $rows) {
            if (strpos($table, 'cheapest_price_speed') !== false) {
                foreach ($rows as $row) {
                    $this->insertedRows[] = array_combine($columns, $row);
                }
            }
            return count($rows);
        });
        return $db;
    }

    private function createRule(string $calculation, int $points, ?float $amount, string $recipient): LoyaltyPoints
    {
        $rule = new LoyaltyPoints();
        $rule->id = 'lp-1';
        $rule->id_date = 'date-1';
        $rule->id_booking_package = 'bp-1';
        $rule->id_media_object = 100;
        $rule->recipient = $recipient;
        $rule->calculation = $calculation;
        $rule->points = $points;
        $rule->amount = $amount;
        return $rule;
    }

    private function createTransport(string $id, int $way, float $price): Transport
    {
        $transport = new Transport();
        $transport->id = $id;
        $transport->id_date = 'date-1';
        $transport->id_media_object = 100;
        $transport->id_booking_package = 'bp-1';
        $transport->type = 'BUS';
        $transport->way = $way;
        $transport->price = $price;
        $transport->state = 3;
        $transport->quota = 99;
        $transport->transport_group = 'Busreise';
        $transport->dont_use_for_offers = false;
        $transport->use_earlybird = false;
        return $transport;
    }

    /**
     * @param LoyaltyPoints[] $loyaltyPoints
     */
    private function insertCheapestPrice(float $outward, float $return, array $loyaltyPoints): array
    {
        $departure = new DateTime('+30 days');
        $departure->setTime(0, 0, 0);

        $date = new Date();
        $date->id = 'date-1';
        $date->id_media_object = 100;
        $date->id_booking_package = 'bp-1';
        $date->departure = $departure;
        $date->arrival = clone $departure;
        $date->state = 1;
        $date->season = 'A';
        $date->transports = [
            $this->createTransport('tr-1', 1, $outward),
            $this->createTransport('tr-2', 2, $return),
        ];
        $date->loyalty_points = $loyaltyPoints;

        $package = new Package();
        $package->id = 'bp-1';
        $package->id_media_object = 100;
        $package->name = 'Bus trip';
        $package->duration = 1;
        $package->price_mix = 'date_transport';
        $package->dates = [$date];

        $mo = new MediaObject(null, false);
        $mo->setId(100);
        $mo->booking_packages = [$package];
        $mo->insertCheapestPrice();

        $this->assertCount(1, $this->insertedRows);
        return $this->insertedRows[0];
    }

    public function testUnitRuleGrantsPointsPerFullAmount(): void
    {
        $row = $this->insertCheapestPrice(22, 22, [$this->createRule('U', 100, 40.00, 'T')]);
        $this->assertEquals(44, $row['price_total']);
        $this->assertSame(100, $row['loyalty_points']);
        $this->assertSame('T', $row['loyalty_points_recipient']);
    }

    public function testUnitRuleCalculatesInCents(): void
    {
        $row = $this->insertCheapestPrice(0.35, 0.35, [$this->createRule('U', 1, 0.10, 'T')]);
        $this->assertEquals(0.7, $row['price_total']);
        $this->assertSame(6.0, floor(0.7 / 0.1), 'Float division would grant one point less');
        $this->assertSame(7, $row['loyalty_points']);
    }

    public function testUnitRuleWithZeroAmountGrantsZeroPoints(): void
    {
        $row = $this->insertCheapestPrice(22, 22, [$this->createRule('U', 100, 0, 'T')]);
        $this->assertSame(0, $row['loyalty_points']);
        $this->assertSame('T', $row['loyalty_points_recipient']);
    }

    public function testFixRuleGrantsPoints(): void
    {
        $row = $this->insertCheapestPrice(22, 22, [$this->createRule('F', 250, null, 'A')]);
        $this->assertSame(250, $row['loyalty_points']);
        $this->assertSame('A', $row['loyalty_points_recipient']);
    }

    public function testDateWithoutRuleLeavesFieldsEmpty(): void
    {
        $row = $this->insertCheapestPrice(22, 22, []);
        $this->assertArrayHasKey('loyalty_points', $row);
        $this->assertArrayHasKey('loyalty_points_recipient', $row);
        // insertCheapestPrice() sets loyalty_points to null; the ORM output filter for integer
        // columns (ORM\Filter\Output\IntegerFilter) writes null as 0, like every other integer column.
        // A date without a rule is therefore recognised by loyalty_points_recipient = null.
        $this->assertSame(0, $row['loyalty_points']);
        $this->assertNull($row['loyalty_points_recipient']);
    }
}
