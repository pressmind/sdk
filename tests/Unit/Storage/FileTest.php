<?php

namespace Pressmind\Tests\Unit\Storage;

use Pressmind\Storage\Bucket;
use Pressmind\Storage\File;
use Pressmind\Tests\Unit\AbstractTestCase;

/**
 * Unit tests for Pressmind\Storage\File.
 * Uses a mock bucket to avoid real I/O.
 */
class FileTest extends AbstractTestCase
{
    /** @var Bucket */
    private $bucket;

    /** @var File */
    private $file;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bucket = $this->createMock(Bucket::class);
        $this->file = new File($this->bucket);
        $this->file->name = 'test-file.txt';
        $this->file->content = 'hello world';
    }

    public function testGetBucket(): void
    {
        $this->assertSame($this->bucket, $this->file->getBucket());
    }

    public function testSaveCallsBucketAddFile(): void
    {
        $this->bucket->expects($this->once())
            ->method('addFile')
            ->with($this->identicalTo($this->file))
            ->willReturn(true);
        $this->file->save();
        $this->assertSame(md5('hello world'), $this->file->hash);
    }

    public function testExistsCallsBucketFileExists(): void
    {
        $this->bucket->expects($this->once())
            ->method('fileExists')
            ->with($this->identicalTo($this->file))
            ->willReturn(true);
        $this->assertTrue($this->file->exists());
    }

    public function testReadCallsBucketReadFile(): void
    {
        $this->file->content = null;
        $this->bucket->expects($this->once())
            ->method('readFile')
            ->with($this->identicalTo($this->file))
            ->willReturnCallback(function ($f) {
                $f->content = 'read content';
                return $f;
            });
        $result = $this->file->read();
        $this->assertSame($this->file, $result);
        $this->assertSame('read content', $this->file->content);
    }

    public function testFilesizeCallsBucketFilesize(): void
    {
        $this->bucket->expects($this->once())
            ->method('filesize')
            ->with($this->identicalTo($this->file))
            ->willReturn(42);
        $this->assertSame(42, $this->file->filesize());
    }

    public function testSetModeCallsBucketSetFileMode(): void
    {
        $this->file->mode = 0644;
        $this->bucket->expects($this->once())
            ->method('setFileMode')
            ->with($this->identicalTo($this->file))
            ->willReturn(true);
        $this->assertTrue($this->file->setMode(0644));
    }

    public function testDeleteCallsBucketRemoveFile(): void
    {
        $this->bucket->expects($this->once())
            ->method('removeFile')
            ->with($this->identicalTo($this->file))
            ->willReturn(true);
        $result = $this->file->delete();
        $this->assertTrue($result);
    }

    public function testAddTagGetTags(): void
    {
        $this->file->addTag('tag1');
        $this->file->addTag('tag2');
        $this->assertSame(['tag1', 'tag2'], $this->file->getTags());
    }

    public function testGetMimetypeFromContent(): void
    {
        $this->file->mimetype = null;
        $this->file->content = 'plain text';
        $mime = $this->file->getMimetype();
        $this->assertIsString($mime);
        $this->assertSame('text/plain', $this->file->mimetype);
    }

    public function testGetMimetypeUsesExistingMimetype(): void
    {
        $this->file->mimetype = 'image/png';
        $this->assertSame('image/png', $this->file->getMimetype());
    }
}
