<?php

namespace Pressmind\Tests\Unit\Import;

use Pressmind\Import\MyContent;
use Pressmind\Tests\Unit\AbstractTestCase;

class MyContentTest extends AbstractTestCase
{
    public function testConstructorAndGetters(): void
    {
        $import = new MyContent([]);
        $this->assertInstanceOf(MyContent::class, $import);
        $this->assertIsArray($import->getLog());
        $this->assertIsArray($import->getErrors());
    }

    public function testImportWithEmptyData(): void
    {
        $import = new MyContent([]);
        $import->import();
        $this->assertCount(0, $import->getErrors());
    }

    public function testImportWithOneItem(): void
    {
        $data = [
            (object) [
                'id' => 1,
                'id_media_object' => 100,
                'section_name' => 'main',
                'var_name' => 'content',
                'language' => 'de',
                'value' => 'Hello',
            ],
        ];
        $import = new MyContent($data);
        $import->import();
        $this->assertCount(0, $import->getErrors());
    }
}
