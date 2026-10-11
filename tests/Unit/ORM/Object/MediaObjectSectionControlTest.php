<?php

namespace Pressmind\Tests\Unit\ORM\Object;

use Pressmind\ORM\Object\MediaObject;
use Pressmind\Tests\Unit\AbstractTestCase;

class MediaObjectSectionControlTest extends AbstractTestCase
{
    private function mediaObject($sectionControl): MediaObject
    {
        $mediaObject = new MediaObject(null, false);
        $mediaObject->section_control = $sectionControl;
        return $mediaObject;
    }

    public function testControlledOutdatedField(): void
    {
        $mediaObject = $this->mediaObject(['beschreibung' => ['possibly_outdated' => true], 'teaser' => ['possibly_outdated' => false]]);

        $this->assertTrue($mediaObject->isSectionControlled('beschreibung'));
        $this->assertTrue($mediaObject->isPossiblyOutdated('beschreibung'));
        $this->assertTrue($mediaObject->isSectionControlled('teaser'));
        $this->assertFalse($mediaObject->isPossiblyOutdated('teaser'));
    }

    public function testVarNameIsNormalized(): void
    {
        $mediaObject = $this->mediaObject(['beschreibung' => ['possibly_outdated' => true]]);

        $this->assertTrue($mediaObject->isSectionControlled('Beschreibung'));
        $this->assertTrue($mediaObject->isPossiblyOutdated('Beschreibung'));
    }

    public function testUnknownFieldAndEmptyValues(): void
    {
        $this->assertFalse($this->mediaObject(['beschreibung' => ['possibly_outdated' => true]])->isSectionControlled('headline'));
        $this->assertFalse($this->mediaObject(null)->isSectionControlled('beschreibung'));
        $this->assertFalse($this->mediaObject(null)->isPossiblyOutdated('beschreibung'));
        $this->assertFalse($this->mediaObject('{kaputt')->isSectionControlled('beschreibung'));
        $this->assertFalse($this->mediaObject(['beschreibung' => 'yes'])->isSectionControlled('beschreibung'));
    }

    public function testMissingPossiblyOutdatedMeansNotOutdated(): void
    {
        $mediaObject = $this->mediaObject(['beschreibung' => []]);

        $this->assertTrue($mediaObject->isSectionControlled('beschreibung'));
        $this->assertFalse($mediaObject->isPossiblyOutdated('beschreibung'));
    }

    public function testJsonStringFromDatabaseIsDecoded(): void
    {
        $mediaObject = $this->mediaObject('{"beschreibung":{"possibly_outdated":true}}');

        $this->assertTrue($mediaObject->isPossiblyOutdated('beschreibung'));
    }
}
