<?php

namespace Pressmind\CLI;

use Exception;
use Pressmind\HelperFunctions;
use Pressmind\Log\Writer;
use Pressmind\ORM\Object\MediaObject\DataType\File;
use Pressmind\Registry;

/**
 * File Downloader Command
 *
 * Downloads files with download_successful=0 from Media Object File data type.
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

        return 0;
    }
}
