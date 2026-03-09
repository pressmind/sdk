<?php

namespace Pressmind\Tests\Unit\Import\Mapper;

use Pressmind\Import\Mapper\File;
use Pressmind\Tests\Unit\AbstractTestCase;

class FileMapperTest extends AbstractTestCase
{
    public function testMapReturnsEmptyArrayWhenNotArray(): void
    {
        $mapper = new File();
        $result = $mapper->map(1, 'de', 'var', (object) []);
        $this->assertIsArray($result);
        $this->assertCount(0, $result);
    }

    public function testMapReturnsMappedFileEntries(): void
    {
        $mapper = new File();
        $input = [
            (object) [
                'id_file' => 101,
                'filename' => 'doc.pdf',
                'filesize' => 1024,
                'description' => 'A file',
                'download' => 'https://example.com/doc.pdf',
            ],
        ];
        $result = $mapper->map(42, 'en', 'documents', $input);
        $this->assertCount(1, $result);
        $mapped = $result[0];
        $this->assertSame(42, $mapped->id_media_object);
        $this->assertSame('en', $mapped->language);
        $this->assertSame('documents', $mapped->var_name);
        $this->assertSame(101, $mapped->id_file);
        $this->assertSame('doc.pdf', $mapped->file_name);
        $this->assertSame(1024, $mapped->file_size);
        $this->assertSame('A file', $mapped->description);
        $this->assertSame('https://example.com/doc.pdf', $mapped->tmp_url);
        $this->assertFalse($mapped->download_successful);
    }
}
