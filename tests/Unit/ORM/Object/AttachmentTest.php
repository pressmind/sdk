<?php

namespace Pressmind\Tests\Unit\ORM\Object;

use Pressmind\ORM\Object\Attachment;
use Pressmind\Registry;
use Pressmind\Tests\Unit\AbstractTestCase;

class AttachmentTest extends AbstractTestCase
{
    private function createAttachment(string $path, string $name): Attachment
    {
        $attachment = new Attachment(null, false);
        $attachment->path = $path;
        $attachment->name = $name;
        return $attachment;
    }

    public function testGetFullPath(): void
    {
        $attachment = $this->createAttachment('/pdf/foo/', 'document.pdf');
        $this->assertSame('/pdf/foo/document.pdf', $attachment->getFullPath());
    }

    public function testGetStoragePath(): void
    {
        $attachment = $this->createAttachment('/pdf/foo/', 'document.pdf');
        $this->assertSame('attachments/pdf/foo/document.pdf', $attachment->getStoragePath());
    }

    public function testGetUri(): void
    {
        $config = $this->createMockConfig([
            'file_handling' => [
                'http_src' => 'https://cdn.example.com',
                'storage' => [
                    'provider' => 'filesystem',
                    'bucket' => '/tmp/test',
                ],
            ],
        ]);
        Registry::getInstance()->add('config', $config, true);

        $attachment = $this->createAttachment('/pdf/foo/', 'document.pdf');
        $this->assertSame(
            'https://cdn.example.com/attachments/pdf/foo/document.pdf',
            $attachment->getUri()
        );
    }

    public function testDeleteFileReturnsTrue(): void
    {
        $config = $this->createMockConfig([
            'file_handling' => [
                'http_src' => 'https://cdn.example.com',
                'storage' => [
                    'provider' => 'filesystem',
                    'bucket' => '/tmp/test',
                ],
            ],
        ]);
        Registry::getInstance()->add('config', $config, true);

        $storageMock = $this->getMockBuilder(\Pressmind\Storage\File::class)
            ->disableOriginalConstructor()
            ->getMock();
        $storageMock->method('exists')->willReturn(false);

        $attachment = $this->getMockBuilder(Attachment::class)
            ->setConstructorArgs([null, false])
            ->onlyMethods(['getFile'])
            ->getMock();
        $attachment->method('getFile')->willReturn($storageMock);

        $this->assertTrue($attachment->deleteFile());
    }
}
