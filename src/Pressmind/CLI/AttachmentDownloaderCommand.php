<?php

namespace Pressmind\CLI;

use Exception;
use Pressmind\HelperFunctions;
use Pressmind\Log\Writer;
use Pressmind\ORM\Object\Attachment;
use Pressmind\Registry;

/**
 * Downloads attachment binaries where download_successful is false (FileStorage sync + WYSIWYG retries).
 *
 * Usage (from theme after bootstrap):
 *   php cli/attachment_downloader.php
 *   php bin/attachment-downloader
 */
class AttachmentDownloaderCommand extends AbstractCommand
{
    private const PROCESS_NAME = 'attachment_downloader';

    protected function execute(): int
    {
        $config = Registry::getInstance()->get('config');

        Writer::write('Attachment downloader started', Writer::OUTPUT_BOTH, self::PROCESS_NAME, Writer::TYPE_INFO);

        $result = Attachment::listAll(['download_successful' => 0]);

        Writer::write('Downloading up to ' . count($result) . ' attachment(s)', Writer::OUTPUT_BOTH, self::PROCESS_NAME, Writer::TYPE_INFO);

        /** @var Attachment $attachment */
        foreach ($result as $attachment) {
            if (empty($attachment->drive_url)) {
                Writer::write(
                    'Skipping attachment ' . $attachment->id . ' (empty drive_url)',
                    Writer::OUTPUT_BOTH,
                    self::PROCESS_NAME,
                    Writer::TYPE_INFO
                );
                continue;
            }
            try {
                Writer::write(
                    'Downloading ' . $attachment->getStoragePath() . ' from ' . $attachment->drive_url,
                    Writer::OUTPUT_BOTH,
                    self::PROCESS_NAME,
                    Writer::TYPE_INFO
                );
                $attachment->downloadOriginal();
                Writer::write(
                    'Saved to ' . HelperFunctions::replaceConstantsFromConfig($config['file_handling']['storage']['bucket']) . '/' . $attachment->getStoragePath(),
                    Writer::OUTPUT_BOTH,
                    self::PROCESS_NAME,
                    Writer::TYPE_INFO
                );
            } catch (Exception $e) {
                Writer::write($e->getMessage(), Writer::OUTPUT_BOTH, self::PROCESS_NAME, Writer::TYPE_ERROR);
            }
        }

        return 0;
    }
}
