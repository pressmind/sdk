<?php

namespace Pressmind\Tests\Unit\ORM\Object;

use Pressmind\ORM\Object\MediaObject;
use Pressmind\Registry;
use Pressmind\Tests\Unit\AbstractTestCase;

class MediaObjectItineraryTest extends AbstractTestCase
{
    private function createMediaObject(?int $id = null): MediaObject
    {
        return new MediaObject($id, false);
    }

    public function testGetItineraryVariantsReturnsEmptyArrayWhenNoResults(): void
    {
        $mo = $this->createMediaObject();
        $mo->setId(100);
        $result = $mo->getItineraryVariants();
        $this->assertIsArray($result);
        $this->assertCount(0, $result);
    }

    public function testGetItineraryVariantsWithAllFiltersReturnsEmptyArray(): void
    {
        $mo = $this->createMediaObject();
        $mo->setId(100);
        $result = $mo->getItineraryVariants('CODE-1', 7, 50, 'standard');
        $this->assertIsArray($result);
        $this->assertCount(0, $result);
    }

    public function testGetItineraryVariantsWithPartialFilters(): void
    {
        $mo = $this->createMediaObject();
        $mo->setId(100);
        $result = $mo->getItineraryVariants('CODE-1', null, null, null);
        $this->assertIsArray($result);
    }

    public function testGetItineraryStepsReturnsEmptyArrayWhenNoResults(): void
    {
        $mo = $this->createMediaObject();
        $mo->setId(100);
        $result = $mo->getItinerarySteps();
        $this->assertIsArray($result);
        $this->assertCount(0, $result);
    }
}
