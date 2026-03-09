<?php

namespace Pressmind\Tests\Unit\ORM\Object;

use Pressmind\ORM\Object\MediaObject;
use Pressmind\Registry;
use Pressmind\Tests\Unit\AbstractTestCase;
use stdClass;

class MediaObjectBookingLinkTest extends AbstractTestCase
{
    private function createCheapestPriceSpeed(array $overrides = []): stdClass
    {
        $cps = new stdClass();
        $cps->id_media_object = 100;
        $cps->id_booking_package = 200;
        $cps->id_date = 300;
        $cps->id_option = null;
        $cps->transport_type = null;
        $cps->id_transport_1 = null;
        $cps->id_transport_2 = null;
        foreach ($overrides as $key => $value) {
            $cps->$key = $value;
        }
        return $cps;
    }

    public function testGetBookingLinkBasicParams(): void
    {
        $cps = $this->createCheapestPriceSpeed();
        $link = MediaObject::getBookingLink($cps);
        $this->assertStringContainsString('imo=100', $link);
        $this->assertStringContainsString('idbp=200', $link);
        $this->assertStringContainsString('idd=300', $link);
    }

    public function testGetBookingLinkWithOption(): void
    {
        $cps = $this->createCheapestPriceSpeed(['id_option' => 'OPT-42']);
        $link = MediaObject::getBookingLink($cps);
        $this->assertStringContainsString('iho[OPT-42]=1', $link);
    }

    public function testGetBookingLinkWithoutOptionOmitsIho(): void
    {
        $cps = $this->createCheapestPriceSpeed();
        $link = MediaObject::getBookingLink($cps);
        $this->assertStringNotContainsString('iho[', $link);
    }

    public function testGetBookingLinkWithTransport(): void
    {
        $cps = $this->createCheapestPriceSpeed([
            'transport_type' => 'flight',
            'id_transport_1' => 'T1',
            'id_transport_2' => 'T2',
        ]);
        $link = MediaObject::getBookingLink($cps);
        $this->assertStringContainsString('idt1=T1', $link);
        $this->assertStringContainsString('idt2=T2', $link);
        $this->assertStringContainsString('tt=flight', $link);
    }

    public function testGetBookingLinkWithDcAndBookingType(): void
    {
        $cps = $this->createCheapestPriceSpeed();
        $link = MediaObject::getBookingLink($cps, null, 'DISCOUNT10', 'fix');
        $this->assertStringContainsString('dc=DISCOUNT10', $link);
        $this->assertStringContainsString('t=fix', $link);
    }

    public function testGetBookingLinkWithUrl(): void
    {
        $cps = $this->createCheapestPriceSpeed();
        $url = 'https://example.com/detail/my-trip';
        $link = MediaObject::getBookingLink($cps, $url);
        $this->assertStringContainsString('url=' . base64_encode($url), $link);
    }

    public function testGetBookingLinkWithDontHideOptions(): void
    {
        $cps = $this->createCheapestPriceSpeed();
        $link = MediaObject::getBookingLink($cps, null, null, null, true);
        $this->assertStringContainsString('hodh=1', $link);
    }

    public function testGetBookingLinkWithoutDontHideOptionsOmitsHodh(): void
    {
        $cps = $this->createCheapestPriceSpeed();
        $link = MediaObject::getBookingLink($cps);
        $this->assertStringNotContainsString('hodh=', $link);
    }

    public function testGetBookingLinkUsesConfigEndpoint(): void
    {
        $config = Registry::getInstance()->get('config');
        $config['ib3'] = ['endpoint' => 'https://booking.example.com/'];
        Registry::getInstance()->add('config', $config);

        $cps = $this->createCheapestPriceSpeed();
        $link = MediaObject::getBookingLink($cps);
        $this->assertStringStartsWith('https://booking.example.com/?', $link);
    }

    public function testGetBookingLinkWithEmptyEndpoint(): void
    {
        $config = Registry::getInstance()->get('config');
        unset($config['ib3']);
        Registry::getInstance()->add('config', $config);

        $cps = $this->createCheapestPriceSpeed();
        $link = MediaObject::getBookingLink($cps);
        $this->assertStringStartsWith('/?', $link);
    }
}
