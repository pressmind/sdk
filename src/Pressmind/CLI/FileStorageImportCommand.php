<?php

namespace Pressmind\CLI;

use Exception;
use Pressmind\Import\FileStorage;
use Pressmind\Log\Writer;

/**
 * Standalone FileStorage metadata import (+ optional attachment downloads).
 *
 * Usage (from theme after bootstrap):
 *   php cli/file_storage_import.php
 *   php cli/file_storage_import.php --force
 *   php cli/file_storage_import.php --folder=<folderId>
 *   php cli/file_storage_import.php --no-download
 */
class FileStorageImportCommand extends AbstractCommand
{
    protected function execute(): int
    {
        $force = $this->hasOption('force');
        $folderOpt = $this->getOption('folder');
        $rootFolder = ($folderOpt !== null && $folderOpt !== true && $folderOpt !== '') ? (string) $folderOpt : null;
        $removeOrphans = $rootFolder === null;
        $noDownload = $this->hasOption('no-download');

        try {
            $importer = new FileStorage($force, $rootFolder, $removeOrphans);
            $importer->import();
            foreach ($importer->getLog() as $line) {
                if ($line !== null && $line !== '') {
                    Writer::write((string) $line, Writer::OUTPUT_BOTH, 'import', Writer::TYPE_INFO);
                }
            }
            foreach ($importer->getErrors() as $err) {
                Writer::write((string) $err, Writer::OUTPUT_BOTH, 'import', Writer::TYPE_ERROR);
            }
        } catch (Exception $e) {
            Writer::write($e->getMessage(), Writer::OUTPUT_BOTH, 'import', Writer::TYPE_ERROR);
            return 1;
        }

        if (!$noDownload) {
            $dl = new AttachmentDownloaderCommand();
            $dl->run(['file_storage_import.php']);
        }

        return 0;
    }
}
