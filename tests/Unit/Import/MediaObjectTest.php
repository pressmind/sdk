<?php

namespace Pressmind\Tests\Unit\Import;

use Pressmind\Import\MediaObject;
use Pressmind\Tests\Unit\AbstractTestCase;

class MediaObjectTest extends AbstractTestCase
{
    public function testConstructorAndGetters(): void
    {
        $import = new MediaObject();
        $this->assertInstanceOf(MediaObject::class, $import);
        $this->assertIsArray($import->getLog());
        $this->assertIsArray($import->getErrors());
        $this->assertCount(0, $import->getErrors());
    }

    public function testExtractSectionControlKeepsOnlyControlledFields(): void
    {
        $fields = [
            (object) ['var_name' => 'beschreibung', 'section_control' => true, 'possibly_outdated' => true],
            (object) ['var_name' => 'headline', 'section_control' => false, 'possibly_outdated' => false],
            (object) ['var_name' => 'Leistungen Text', 'section_control' => true],
        ];

        $this->assertSame([
            'beschreibung' => ['possibly_outdated' => true],
            'leistungen_text' => ['possibly_outdated' => false],
        ], MediaObject::extractSectionControl($fields));
    }

    public function testExtractSectionControlIsNullWithoutControlledFields(): void
    {
        $this->assertNull(MediaObject::extractSectionControl([
            (object) ['var_name' => 'headline', 'type' => 'text'],
            (object) ['var_name' => 'teaser', 'section_control' => false, 'possibly_outdated' => false],
        ]));
        $this->assertNull(MediaObject::extractSectionControl([]));
        $this->assertNull(MediaObject::extractSectionControl(null));
        $this->assertNull(MediaObject::extractSectionControl([(object) ['section_control' => true]]));
    }
}
