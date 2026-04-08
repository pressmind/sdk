<?php

namespace Pressmind\Import;

use Exception;
use Pressmind\DB\Scaffolder\Mysql;
use Pressmind\Log\Writer;
use Pressmind\ORM\Object\Attachment;
use stdClass;

/**
 * Imports all files from Pressmind FileStorage API into {@see Attachment} rows (metadata only).
 * Binary download is deferred: {@see Attachment::$download_successful} stays false until
 * {@see \Pressmind\CLI\AttachmentDownloaderCommand} or theme cli/attachment_downloader.php runs.
 */
class FileStorage extends AbstractImport implements ImportInterface
{
    private bool $force;

    /**
     * When set, only this folder subtree is imported (no full-tree orphan removal).
     */
    private ?string $rootFolderId;

    private bool $removeOrphansOnFullRun;

    /**
     * @param bool $force Skip hash comparison and always update metadata + mark for re-download
     * @param string|null $rootFolderId Import only this folder and descendants (null = entire storage)
     * @param bool $removeOrphansOnFullRun When true and $rootFolderId is null, delete synced FileStorage rows missing from API
     */
    public function __construct(bool $force = false, ?string $rootFolderId = null, bool $removeOrphansOnFullRun = true)
    {
        parent::__construct();
        $this->force = $force;
        $this->rootFolderId = $rootFolderId;
        $this->removeOrphansOnFullRun = $removeOrphansOnFullRun;
    }

    /**
     * Map API file object to attachment field values (for tests and internal use).
     *
     * @return array<string, mixed>
     */
    public static function mapApiFileToAttachmentState(stdClass $file): array
    {
        $folderId = null;
        if (isset($file->folder) && is_object($file->folder) && isset($file->folder->_id)) {
            $folderId = (string) $file->folder->_id;
        }
        $path = isset($file->path) ? (string) $file->path : '/';
        if ($path !== '' && $path[0] !== '/') {
            $path = '/' . $path;
        }
        if ($path !== '' && $path !== '/' && substr($path, -1) !== '/') {
            $path .= '/';
        }
        if ($path === '') {
            $path = '/';
        }
        return [
            'id' => isset($file->_id) ? (string) $file->_id : '',
            'name' => isset($file->name) ? (string) $file->name : '',
            'path' => $path,
            'hash' => isset($file->hash) ? (string) $file->hash : null,
            'mime_type' => isset($file->mimeType) ? (string) $file->mimeType : null,
            'file_size' => isset($file->fileSize) ? (int) $file->fileSize : 0,
            'drive_url' => isset($file->drive_url) ? (string) $file->drive_url : null,
            'folder_id' => $folderId,
            'description' => isset($file->description) && $file->description !== null ? (string) $file->description : null,
        ];
    }

    /**
     * @return true
     * @throws Exception
     */
    public function import()
    {
        $this->_log[] = Writer::write(
            $this->_getElapsedTimeAndHeap() . ' FileStorage::import() starting',
            Writer::OUTPUT_BOTH,
            'import',
            Writer::TYPE_INFO
        );

        try {
            $scaffolder = new Mysql(new Attachment());
            $scaffolder->run(false);
        } catch (Exception $e) {
            $this->_errors[] = '[FileStorage] Schema ensure failed: ' . $e->getMessage();
            throw $e;
        }

        $client = $this->getClient();
        $importedIds = [];

        try {
            $stats = $client->sendRequest('FileStorage', 'getStats', []);
            $this->assertResponseOk($stats);
            if (isset($stats->result) && is_object($stats->result)) {
                $this->_log[] = Writer::write(
                    $this->_getElapsedTimeAndHeap() . ' FileStorage::import() getStats totalFiles=' . ($stats->result->totalFiles ?? '?'),
                    Writer::OUTPUT_BOTH,
                    'import',
                    Writer::TYPE_INFO
                );
            }

            if ($this->rootFolderId !== null) {
                $this->walkFolder($this->rootFolderId, $client, $importedIds);
            } else {
                $rootResponse = $client->sendRequest('FileStorage', 'getFolders', []);
                $this->assertListResult($rootResponse);
                $folders = $rootResponse->result;
                if (count($folders) === 0) {
                    $flatFiles = $client->sendRequest('FileStorage', 'getFiles', []);
                    $this->assertListResult($flatFiles);
                    foreach ($flatFiles->result as $file) {
                        if (!is_object($file) || empty($file->_id)) {
                            continue;
                        }
                        $this->upsertAttachmentFromApi($file, $importedIds);
                    }
                } else {
                    foreach ($folders as $folder) {
                        if (!isset($folder->_id)) {
                            continue;
                        }
                        $this->walkFolder((string) $folder->_id, $client, $importedIds);
                    }
                }
            }

            if ($this->removeOrphansOnFullRun && $this->rootFolderId === null) {
                $this->removeStaleFileStorageAttachments($importedIds);
            }

            $this->_log[] = Writer::write(
                $this->_getElapsedTimeAndHeap() . ' FileStorage::import() finished, ' . count($importedIds) . ' file(s)',
                Writer::OUTPUT_BOTH,
                'import',
                Writer::TYPE_INFO
            );
        } catch (Exception $e) {
            $this->_errors[] = '[FileStorage] ' . $e->getMessage();
            $this->_log[] = Writer::write(
                $this->_getElapsedTimeAndHeap() . ' FileStorage::import() failed: ' . $e->getMessage(),
                Writer::OUTPUT_BOTH,
                'import',
                Writer::TYPE_ERROR
            );
            throw $e;
        }

        return true;
    }

    /**
     * @param array<string> $importedIds
     */
    private function walkFolder(string $folderId, $client, array &$importedIds): void
    {
        $filesResponse = $client->sendRequest('FileStorage', 'getFiles', ['folderId' => $folderId]);
        $this->assertListResult($filesResponse);
        foreach ($filesResponse->result as $file) {
            if (!is_object($file) || empty($file->_id)) {
                continue;
            }
            $this->upsertAttachmentFromApi($file, $importedIds);
        }

        $childResponse = $client->sendRequest('FileStorage', 'getFolders', ['parentId' => $folderId]);
        $this->assertListResult($childResponse);
        foreach ($childResponse->result as $child) {
            if (!isset($child->_id)) {
                continue;
            }
            $this->walkFolder((string) $child->_id, $client, $importedIds);
        }
    }

    /**
     * @param array<string> $importedIds
     */
    private function upsertAttachmentFromApi(stdClass $file, array &$importedIds): void
    {
        $mapped = self::mapApiFileToAttachmentState($file);
        if ($mapped['id'] === '' || $mapped['name'] === '') {
            return;
        }
        $importedIds[$mapped['id']] = true;

        $attachment = new Attachment();
        $attachment->read($mapped['id']);
        $exists = !empty($attachment->id);
        $existingHash = $exists ? $attachment->hash : null;

        if (!$this->force && $exists && $mapped['hash'] !== null && $existingHash === $mapped['hash']) {
            return;
        }

        $attachment->id = $mapped['id'];
        $attachment->name = $mapped['name'];
        $attachment->path = $mapped['path'];
        $attachment->hash = $mapped['hash'];
        $attachment->mime_type = $mapped['mime_type'];
        $attachment->file_size = $mapped['file_size'];
        $attachment->drive_url = $mapped['drive_url'];
        $attachment->folder_id = $mapped['folder_id'];
        $attachment->description = $mapped['description'];
        $attachment->tmp_url = $mapped['drive_url'];
        $attachment->synced_from_file_storage = true;
        $attachment->download_successful = false;

        if ($exists) {
            $attachment->update();
        } else {
            $attachment->create();
        }
    }

    /**
     * @param array<string, true> $importedIds
     */
    private function removeStaleFileStorageAttachments(array $importedIds): void
    {
        $ids = array_keys($importedIds);
        $candidates = Attachment::listAll(['synced_from_file_storage' => 1]);
        foreach ($candidates as $row) {
            $aid = is_object($row) ? $row->id : null;
            if ($aid === null || $aid === '') {
                continue;
            }
            if (in_array((string) $aid, $ids, true)) {
                continue;
            }
            try {
                $att = new Attachment();
                $att->read((string) $aid);
                $att->deleteFile();
                $att->delete();
                $this->_log[] = Writer::write(
                    $this->_getElapsedTimeAndHeap() . ' FileStorage::removeStaleFileStorageAttachments() removed ' . $aid,
                    Writer::OUTPUT_BOTH,
                    'import',
                    Writer::TYPE_INFO
                );
            } catch (Exception $e) {
                $this->_errors[] = '[FileStorage] Orphan removal failed for ' . $aid . ': ' . $e->getMessage();
            }
        }
    }

    private function assertResponseOk(stdClass $response): void
    {
        if (isset($response->error) && $response->error) {
            $msg = isset($response->msg) ? (string) $response->msg : 'Unknown API error';
            throw new Exception('FileStorage API error: ' . $msg);
        }
        if (!isset($response->result)) {
            throw new Exception('FileStorage API response missing result');
        }
    }

    private function assertListResult(stdClass $response): void
    {
        $this->assertResponseOk($response);
        if (!is_array($response->result)) {
            throw new Exception('FileStorage API result is not a list');
        }
    }
}
