<?php

namespace Pressmind\Tests\Unit\Import;

use PHPUnit\Framework\TestCase;
use Pressmind\Import\FileStorage;
use stdClass;

class FileStorageTest extends TestCase
{
    public function testMapApiFileToAttachmentStateMapsFields(): void
    {
        $file = json_decode(file_get_contents(
            dirname(__DIR__, 2) . '/Fixtures/filestorage/getFiles_one.response.json'
        ))->result[0];

        $mapped = FileStorage::mapApiFileToAttachmentState($file);

        $this->assertSame('fixture-file-001', $mapped['id']);
        $this->assertSame('Sample Document.pdf', $mapped['name']);
        $this->assertSame('/pdf/', $mapped['path']);
        $this->assertSame('c598b5eb86ceea54a5c97aab29111198', $mapped['hash']);
        $this->assertSame('application/pdf', $mapped['mime_type']);
        $this->assertSame(1758968, $mapped['file_size']);
        $this->assertSame('https://example.test/drive/files/fixture-folder-001/sample-document.pdf', (string) $mapped['drive_url']);
        $this->assertSame('fixture-folder-001', $mapped['folder_id']);
        $this->assertNull($mapped['description']);
    }

    public function testMapApiFileNormalizesPathWithoutLeadingSlash(): void
    {
        $file = new stdClass();
        $file->_id = 'a';
        $file->name = 'x.pdf';
        $file->path = 'docs';
        $file->hash = 'h';
        $file->mimeType = 'application/pdf';
        $file->fileSize = 1;
        $file->drive_url = 'https://example.com/f';
        $file->description = null;

        $mapped = FileStorage::mapApiFileToAttachmentState($file);
        $this->assertSame('/docs/', $mapped['path']);
    }

    public function testMapApiFileEmptyIdSkippedByUpsertContract(): void
    {
        $file = new stdClass();
        $file->_id = '';
        $file->name = 'n';
        $mapped = FileStorage::mapApiFileToAttachmentState($file);
        $this->assertSame('', $mapped['id']);
    }
}
