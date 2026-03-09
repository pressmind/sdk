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
        $this->assertSame('image/jpeg', $mapped->mime_type);
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
}
