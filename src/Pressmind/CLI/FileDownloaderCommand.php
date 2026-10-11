<?php

namespace Pressmind\CLI;

use Exception;
use Pressmind\HelperFunctions;
use Pressmind\Log\Writer;
use Pressmind\ORM\Object\MediaObject\DataType\File;
use Pressmind\ORM\Object\MediaObject\DataType\Picture;
use Pressmind\Registry;

/**
 * File Downloader Command
 *
 * Downloads files with download_successful=0 from Media Object File data type.
 * Also downloads pending Motion Clip videos of Media Object pictures (video_download_successful=0).
 *
 * Usage:
 *   php cli/file_downloader.php
 *   php bin/file-downloader
 */
class FileDownloaderCommand extends AbstractCommand
{
    private const PROCESS_NAME = 'file_downloader';

    protected function execute(): int
    {
        $config = Registry::getInstance()->get('config');

        Writer::write('File downloader started', Writer::OUTPUT_FILE, self::PROCESS_NAME, Writer::TYPE_INFO);

        $result = File::listAll(['download_successful' => 0]);

        Writer::write('Downloading ' . count($result) . ' files', Writer::OUTPUT_FILE, self::PROCESS_NAME, Writer::TYPE_INFO);

        /** @var File $file */
        foreach ($result as $file) {
            try {
                Writer::write('Downloading file from ' . $file->tmp_url, Writer::OUTPUT_FILE, self::PROCESS_NAME, Writer::TYPE_INFO);
                $file->downloadOriginal();
                Writer::write(
                    'File downloaded to ' . HelperFunctions::replaceConstantsFromConfig($config['file_handling']['storage']['bucket']) . '/' . $file->file_name,
                    Writer::OUTPUT_FILE,
                    self::PROCESS_NAME,
                    Writer::TYPE_INFO
                );
            } catch (Exception $e) {
                Writer::write($e->getMessage(), Writer::OUTPUT_FILE, self::PROCESS_NAME, Writer::TYPE_ERROR);
            }
        }

        $videos = Picture::listAll(['video_tmp_url' => 'IS NOT NULL', 'video_download_successful' => 0]);

        Writer::write('Downloading ' . count($videos) . ' videos', Writer::OUTPUT_FILE, self::PROCESS_NAME, Writer::TYPE_INFO);

        /** @var Picture $picture */
        foreach ($videos as $picture) {
            try {
                Writer::write('Downloading video from ' . $picture->video_tmp_url, Writer::OUTPUT_FILE, self::PROCESS_NAME, Writer::TYPE_INFO);
                $picture->downloadVideo();
                Writer::write(
                    'Video downloaded to ' . HelperFunctions::replaceConstantsFromConfig($config['file_handling']['storage']['bucket']) . '/' . $picture->video_file_name,
                    Writer::OUTPUT_FILE,
                    self::PROCESS_NAME,
                    Writer::TYPE_INFO
                );
            } catch (Exception $e) {
                Writer::write('Video download for picture ID ' . $picture->getId() . ' failed: ' . $e->getMessage(), Writer::OUTPUT_FILE, self::PROCESS_NAME, Writer::TYPE_ERROR);
            }
        }

        return 0;
    }
}
