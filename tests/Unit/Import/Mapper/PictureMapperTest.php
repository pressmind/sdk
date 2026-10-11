<?php

namespace Pressmind\Tests\Unit\Import\Mapper;

use Pressmind\Import\Mapper\Picture;
use Pressmind\Tests\Unit\AbstractTestCase;

class PictureMapperTest extends AbstractTestCase
{
    public function testMapReturnsEmptyWhenNotArray(): void
    {
        $mapper = new Picture();
        $result = $mapper->map(1, 'de', 'var', (object) []);
        $this->assertIsArray($result);
        $this->assertCount(0, $result);
    }

    public function testMapSkipsItemWithEmptyFilename(): void
    {
        $mapper = new Picture();
        $input = [
            (object) [
                'id_media_object' => 1,
                'caption' => '',
                'title' => '',
                'alt' => '',
                'uri' => null,
                'copyright' => '',
                'image' => (object) [
                    'filename' => '',
                    'visibility' => 0,
                    'width' => 100,
                    'height' => 100,
                    'filesize' => 0,
                    'links' => (object) [
                        'web' => (object) [
                            'url' => 'https://example.com/img.jpg',
                            'mime_type' => 'image/jpeg',
                        ],
                    ],
                ],
            ],
        ];
        $result = $mapper->map(42, 'de', 'gallery', $input);
        $this->assertCount(0, $result);
    }

    public function testMapSkipsItemWithVisibility10(): void
    {
        $mapper = new Picture();
        $input = [
            (object) [
                'id_media_object' => 1,
                'caption' => 'Cap',
                'title' => 'T',
                'alt' => 'A',
                'uri' => null,
                'copyright' => '',
                'image' => (object) [
                    'filename' => 'img.jpg',
                    'visibility' => 10,
                    'width' => 100,
                    'height' => 100,
                    'filesize' => 0,
                    'links' => (object) [
                        'web' => (object) [
                            'url' => 'https://example.com/img.jpg',
                            'mime_type' => 'image/jpeg',
                        ],
                    ],
                ],
            ],
        ];
        $result = $mapper->map(42, 'de', 'gallery', $input);
        $this->assertCount(0, $result);
    }

    public function testMapReturnsMappedPicture(): void
    {
        $mapper = new Picture();
        $input = [
            (object) [
                'id_media_object' => 101,
                'caption' => 'Caption',
                'title' => 'Title',
                'alt' => 'Alt',
                'uri' => 'https://example.com/uri',
                'copyright' => 'CC',
                'disabled' => false,
                'image' => (object) [
                    'filename' => 'img.jpg',
                    'visibility' => 0,
                    'is_ai' => true,
                    'ai_disclosure' => 'modified',
                    'width' => 800,
                    'height' => 600,
                    'filesize' => 1024,
                    'links' => (object) [
                        'web' => (object) [
                            'url' => 'https://example.com/img.jpg',
                            'mime_type' => 'image/jpeg',
                        ],
                    ],
                ],
            ],
        ];
        $result = $mapper->map(42, 'de', 'gallery', $input);
        $this->assertCount(1, $result);
        $mapped = $result[0];
        $this->assertSame(42, $mapped->id_media_object);
        $this->assertSame(101, $mapped->id_picture);
        $this->assertSame('de', $mapped->language);
        $this->assertSame('Caption', $mapped->caption);
        $this->assertSame('Title', $mapped->title);
        $this->assertTrue($mapped->is_ai);
        $this->assertSame('modified', $mapped->ai_disclosure);
        $this->assertSame('image/jpeg', $mapped->mime_type);
    }

    public function testMapDefaultsMissingAiDisclosureFieldsToNull(): void
    {
        $mapper = new Picture();
        $input = [
            (object) [
                'id_media_object' => 101,
                'caption' => '',
                'title' => '',
                'alt' => '',
                'uri' => null,
                'copyright' => '',
                'image' => (object) [
                    'filename' => 'img.jpg',
                    'visibility' => 0,
                    'width' => 800,
                    'height' => 600,
                    'filesize' => 1024,
                    'links' => (object) [
                        'web' => (object) [
                            'url' => 'https://example.com/img.jpg',
                            'mime_type' => 'image/jpeg',
                        ],
                    ],
                ],
            ],
        ];

        $mapped = $mapper->map(42, 'de', 'gallery', $input)[0];

        $this->assertObjectHasProperty('is_ai', $mapped);
        $this->assertNull($mapped->is_ai);
        $this->assertObjectHasProperty('ai_disclosure', $mapped);
        $this->assertNull($mapped->ai_disclosure);
    }

    public function testMapAddsPictureSectionsWhenPresent(): void
    {
        $mapper = new Picture();
        $input = [
            (object) [
                'id_media_object' => 101,
                'caption' => '',
                'title' => '',
                'alt' => '',
                'uri' => null,
                'copyright' => '',
                'image' => (object) [
                    'filename' => 'x.jpg',
                    'visibility' => 0,
                    'width' => 1,
                    'height' => 1,
                    'filesize' => 0,
                    'links' => (object) [
                        'web' => (object) [
                            'url' => 'https://example.com/x.jpg',
                            'mime_type' => 'image/jpeg',
                        ],
                    ],
                ],
                'picture_sections' => (object) [
                    '1' => (object) [
                        'name' => 'Section A',
                        'link' => 'https://example.com/section.jpg',
                        'img_width' => 100,
                        'img_height' => 100,
                        'img_x' => 0,
                        'img_y' => 0,
                    ],
                ],
            ],
        ];
        $result = $mapper->map(42, 'de', 'gallery', $input);
        $this->assertCount(1, $result);
        $this->assertCount(1, $result[0]->sections);
        $this->assertSame('section_a', $result[0]->sections[0]->section_name);
    }

    public function testMapSkipsPictureSectionWithEmptyName(): void
    {
        $mapper = new Picture();
        $input = [
            (object) [
                'id_media_object' => 101,
                'caption' => '',
                'title' => '',
                'alt' => '',
                'uri' => null,
                'copyright' => '',
                'image' => (object) [
                    'filename' => 'x.jpg',
                    'visibility' => 0,
                    'width' => 1,
                    'height' => 1,
                    'filesize' => 0,
                    'links' => (object) [
                        'web' => (object) [
                            'url' => 'https://example.com/x.jpg',
                            'mime_type' => 'image/jpeg',
                        ],
                    ],
                ],
                'picture_sections' => (object) [
                    '1' => (object) [
                        'name' => '',
                        'link' => 'https://example.com/s.jpg',
                        'img_width' => 1,
                        'img_height' => 1,
                        'img_x' => 0,
                        'img_y' => 0,
                    ],
                ],
            ],
        ];
        $result = $mapper->map(42, 'de', 'gallery', $input);
        $this->assertCount(1, $result);
        $this->assertCount(0, $result[0]->sections);
    }

    private const CLIP_URL = 'https://image-archive.pressmind.io/clip/video.mp4';

    private function motionClipInput(bool $withVideoKey, $video = null): array
    {
        $image = (object) [
            'filename' => 'clip.mp4',
            'visibility' => 30,
            'width' => 1280,
            'height' => 720,
            'filesize' => 4718592,
            'links' => (object) [
                'web' => (object) [
                    'url' => 'https://images.pressmind.io/getImages?api=key&id=4052278&v=web',
                    'mime_type' => 'image/jpeg',
                ],
            ],
        ];
        if ($withVideoKey) {
            $image->video = $video;
        }
        return [
            (object) [
                'id_media_object' => 4052278,
                'caption' => '',
                'title' => '',
                'alt' => '',
                'uri' => null,
                'copyright' => '',
                'disabled' => false,
                'image' => $image,
            ],
        ];
    }

    private function clipVideo(string $url = self::CLIP_URL): \stdClass
    {
        return (object) [
            'url' => $url,
            'mime_type' => 'video/mp4',
            'width' => 1280,
            'height' => 720,
            'duration' => 6.04,
            'filesize' => 4718592,
        ];
    }

    public function testMapTakesOverMotionClipVideo(): void
    {
        $mapped = (new Picture())->map(42, 'de', 'gallery', $this->motionClipInput(true, $this->clipVideo()))[0];

        $this->assertSame(self::CLIP_URL, $mapped->video_tmp_url);
        $this->assertSame('42_4052278_' . md5(self::CLIP_URL) . '.mp4', $mapped->video_file_name);
        $this->assertSame('video/mp4', $mapped->video_mime_type);
        $this->assertSame(1280, $mapped->video_width);
        $this->assertSame(720, $mapped->video_height);
        $this->assertSame(6.04, $mapped->video_duration);
        $this->assertSame(4718592, $mapped->video_file_size);
        $this->assertFalse($mapped->video_download_successful);
        // the still image is still imported as regular picture (poster)
        $this->assertSame('https://images.pressmind.io/getImages?api=key&id=4052278&v=web', $mapped->tmp_url);
        $this->assertSame('image/jpeg', $mapped->mime_type);
    }

    public function testReplacedClipGetsNewVideoFileName(): void
    {
        $old = (new Picture())->map(42, 'de', 'gallery', $this->motionClipInput(true, $this->clipVideo()))[0];
        $new = (new Picture())->map(42, 'de', 'gallery', $this->motionClipInput(true, $this->clipVideo('https://image-archive.pressmind.io/clip-v2/video.mp4')))[0];

        $this->assertNotSame($old->video_file_name, $new->video_file_name);
    }

    /**
     * @return array<string, array{0: bool, 1: mixed}>
     */
    public static function noVideoProvider(): array
    {
        return [
            'key missing' => [false, null],
            'video null' => [true, null],
            'empty url' => [true, (object) ['url' => '', 'mime_type' => 'video/mp4']],
            'url not a string' => [true, (object) ['url' => ['x'], 'mime_type' => 'video/mp4']],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('noVideoProvider')]
    public function testMapLeavesVideoFieldsNullWithoutVideo(bool $withVideoKey, $video): void
    {
        $mapped = (new Picture())->map(42, 'de', 'gallery', $this->motionClipInput($withVideoKey, $video))[0];

        foreach (['video_tmp_url', 'video_file_name', 'video_mime_type', 'video_width', 'video_height', 'video_duration', 'video_file_size', 'video_download_successful'] as $property) {
            $this->assertObjectHasProperty($property, $mapped);
            $this->assertNull($mapped->$property, $property);
        }
        $this->assertSame('42_4052278.' . \Pressmind\HelperFunctions::getExtensionFromMimeType('image/jpeg'), $mapped->file_name);
    }
}
