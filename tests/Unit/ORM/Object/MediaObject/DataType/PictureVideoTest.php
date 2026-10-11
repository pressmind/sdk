<?php

namespace Pressmind\Tests\Unit\ORM\Object\MediaObject\DataType;

use Pressmind\ORM\Object\MediaObject\DataType\Picture;
use Pressmind\ORM\Object\MediaObject\DataType\Picture\Section;
use Pressmind\Registry;
use Pressmind\Tests\Unit\AbstractTestCase;

class PictureVideoTest extends AbstractTestCase
{
    private string $bucketDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bucketDir = sys_get_temp_dir() . '/pm-picture-video-test-' . getmypid();
        if (!is_dir($this->bucketDir)) {
            mkdir($this->bucketDir, 0755, true);
        }
        Registry::getInstance()->add('config', $this->createMockConfig([
            'file_handling' => [
                'storage' => ['provider' => 'filesystem', 'bucket' => $this->bucketDir],
                'http_src' => 'https://shop.example/assets/files',
            ],
            'image_handling' => [
                'processor' => ['derivatives' => []],
                'storage' => ['provider' => 'filesystem', 'bucket' => $this->bucketDir],
            ],
        ]));
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->bucketDir . '/*') ?: []);
        @rmdir($this->bucketDir);
        parent::tearDown();
    }

    private function clipPicture(): Picture
    {
        $picture = new Picture();
        $picture->id = 7;
        $picture->id_picture = 4052278;
        $picture->id_media_object = 42;
        $picture->var_name = 'gallery';
        $picture->video_tmp_url = 'https://image-archive.pressmind.io/clip/video.mp4';
        $picture->video_file_name = '42_4052278_' . md5('https://image-archive.pressmind.io/clip/video.mp4') . '.mp4';
        $picture->video_mime_type = 'video/mp4';
        $picture->video_download_successful = false;
        return $picture;
    }

    private function placeInBucket(string $name, string $content): void
    {
        file_put_contents($this->bucketDir . '/' . $name, $content);
    }

    private function mp4(): string
    {
        return (string) file_get_contents(dirname(__DIR__, 5) . '/Fixtures/video/motion-clip.mp4');
    }

    public function testVideoPropertiesAreDefinedAndTyped(): void
    {
        $picture = new Picture();
        $picture->video_tmp_url = 'https://image-archive.pressmind.io/clip/video.mp4';
        $picture->video_file_name = '42_4052278_abc.mp4';
        $picture->video_mime_type = 'video/mp4';
        $picture->video_width = '1280';
        $picture->video_height = 720;
        $picture->video_duration = '6.04';
        $picture->video_file_size = 4718592;
        $picture->video_download_successful = 1;

        $this->assertSame('https://image-archive.pressmind.io/clip/video.mp4', $picture->video_tmp_url);
        $this->assertSame('42_4052278_abc.mp4', $picture->video_file_name);
        $this->assertSame('video/mp4', $picture->video_mime_type);
        $this->assertSame(1280, $picture->video_width);
        $this->assertSame(720, $picture->video_height);
        $this->assertSame(6.04, $picture->video_duration);
        $this->assertSame(4718592, $picture->video_file_size);
        $this->assertTrue($picture->video_download_successful);
    }

    public function testVideoDownloadFlagHasNoDefaultValue(): void
    {
        $definitions = (new Picture())->getPropertyDefinitions();

        $this->assertArrayHasKey('video_download_successful', $definitions);
        $this->assertArrayNotHasKey('default_value', $definitions['video_download_successful']);
        foreach (['video_tmp_url', 'video_file_name', 'video_mime_type', 'video_width', 'video_height', 'video_duration', 'video_file_size', 'video_download_successful'] as $property) {
            $this->assertFalse($definitions[$property]['required'], $property);
        }
    }

    public function testPictureWithoutVideoHasNoVideoUri(): void
    {
        $picture = new Picture();

        $this->assertFalse($picture->hasVideo());
        $this->assertNull($picture->getVideoUri());
    }

    public function testVideoUriIsNullUntilDownloaded(): void
    {
        $picture = $this->clipPicture();

        $this->assertTrue($picture->hasVideo());
        $this->assertNull($picture->getVideoUri());

        $picture->video_download_successful = true;
        $this->assertSame('https://shop.example/assets/files/' . $picture->video_file_name, $picture->getVideoUri());
    }

    public function testDownloadVideoStoresMp4AndSetsFlagAndSize(): void
    {
        $picture = $this->clipPicture();
        // File\Downloader reads an existing target instead of fetching it, so no HTTP is needed here.
        $this->placeInBucket($picture->video_file_name, $this->mp4());

        $picture->downloadVideo();

        $this->assertTrue($picture->video_download_successful);
        $this->assertSame(strlen($this->mp4()), $picture->video_file_size);
        $this->assertTrue($picture->getVideoFile()->exists());
    }

    public function testDownloadVideoRejectsApiMimeTypeOtherThanMp4(): void
    {
        $picture = $this->clipPicture();
        $picture->video_mime_type = 'video/webm';

        try {
            $picture->downloadVideo();
            $this->fail('Expected exception for video/webm');
        } catch (\Exception $e) {
            $this->assertStringContainsString('video/webm', $e->getMessage());
        }
        $this->assertFalse($picture->video_download_successful);
        $this->assertFileDoesNotExist($this->bucketDir . '/' . $picture->video_file_name);
    }

    public function testDownloadVideoRejectsStoredFileThatIsNoMp4(): void
    {
        $picture = $this->clipPicture();
        // unreachable source, so the forced re-download of the invalid stored file fails as well
        $picture->video_tmp_url = 'http://127.0.0.1:9/clip.mp4';
        $this->placeInBucket($picture->video_file_name, '<html>not a video</html>');

        try {
            $picture->downloadVideo();
            $this->fail('Expected exception for non-mp4 content');
        } catch (\Exception $e) {
            $this->assertStringContainsString('expected video/mp4', $e->getMessage());
        }
        $this->assertFalse($picture->video_download_successful);
        $this->assertFileDoesNotExist($this->bucketDir . '/' . $picture->video_file_name);
    }

    public function testDownloadVideoRejectsSizeZero(): void
    {
        $picture = $this->clipPicture();
        $picture->video_tmp_url = 'http://127.0.0.1:9/clip.mp4';
        $this->placeInBucket($picture->video_file_name, '');

        try {
            $picture->downloadVideo();
            $this->fail('Expected exception for an empty video file');
        } catch (\Exception $e) {
            $this->assertStringContainsString('filesize is 0', $e->getMessage());
        }
        $this->assertFalse($picture->video_download_successful);
        $this->assertNull($picture->video_file_size);
        $this->assertFileDoesNotExist($this->bucketDir . '/' . $picture->video_file_name);
    }

    public function testDownloadVideoFailsWithoutVideo(): void
    {
        $this->expectException(\Exception::class);
        (new Picture())->downloadVideo();
    }

    public function testCreateMarksExistingVideoFileAsDownloaded(): void
    {
        $picture = $this->clipPicture();
        $this->placeInBucket($picture->video_file_name, $this->mp4());

        $picture->create();

        $this->assertTrue($picture->video_download_successful);
    }

    public function testCreateKeepsFlagFalseForEmptyVideoFile(): void
    {
        $picture = $this->clipPicture();
        $this->placeInBucket($picture->video_file_name, '');

        $picture->create();

        $this->assertFalse($picture->video_download_successful);
    }

    public function testUpdateKeepsFlagFalseWhenVideoFileIsMissing(): void
    {
        $picture = $this->clipPicture();

        $picture->update();

        $this->assertFalse($picture->video_download_successful);
    }

    public function testSectionSaveIgnoresVideoLogic(): void
    {
        $section = new Section();
        $section->id = 3;
        $section->id_media_object = 42;
        $section->section_name = 'teaser';

        $this->assertFalse($section->hasVideo());
        $this->assertNull($section->getVideoUri());
        $section->create();
        $section->update();
        $this->assertFalse($section->hasProperty('video_file_name'));
    }
}
