<?php

namespace Pressmind\ORM\Object;

use Exception;
use Pressmind\HelperFunctions;
use Pressmind\Registry;
use Pressmind\Storage\Bucket;

/**
 * Class Attachment
 * Global/Systemweit verfügbare Attachments aus Text/WYSIWYG-Feldern.
 * Ein Attachment kann von mehreren MediaObjects referenziert werden.
 * Die Verknüpfung erfolgt über die AttachmentToMediaObject-Tabelle.
 *
 * @package Pressmind\ORM\Object
 * @property string $id
 * @property string $name
 * @property string $path
 * @property string $hash
 * @property string $mime_type
 * @property integer $file_size
 * @property string $drive_url
 * @property string $folder_id
 * @property string $description
 * @property string $tmp_url
 * @property boolean $download_successful
 */
class Attachment extends AbstractObject
{
    protected $_dont_use_autoincrement_on_primary_key = true;
    protected $_replace_into_on_create = true;

    protected $_definitions = [
        'class' => [
            'name' => self::class
        ],
        'database' => [
            'table_name' => 'pmt2core_attachments',
            'primary_key' => 'id',
        ],
        'properties' => [
            'id' => [
                'title' => 'id',
                'name' => 'id',
                'type' => 'string',
                'required' => true,
                'filters' => null,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 64,
                    ]
                ],
            ],
            'name' => [
                'title' => 'name',
                'name' => 'name',
                'type' => 'string',
                'required' => true,
                'filters' => null,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 512,
                    ]
                ],
            ],
            'path' => [
                'title' => 'path',
                'name' => 'path',
                'type' => 'string',
                'required' => false,
                'filters' => null,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 512,
                    ]
                ],
            ],
            'hash' => [
                'title' => 'hash',
                'name' => 'hash',
                'type' => 'string',
                'required' => false,
                'filters' => null,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 128,
                    ]
                ],
            ],
            'mime_type' => [
                'title' => 'mime_type',
                'name' => 'mime_type',
                'type' => 'string',
                'required' => false,
                'filters' => null,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 255,
                    ]
                ],
            ],
            'file_size' => [
                'title' => 'file_size',
                'name' => 'file_size',
                'type' => 'integer',
                'required' => false,
                'filters' => null,
                'validators' => null,
            ],
            'drive_url' => [
                'title' => 'drive_url',
                'name' => 'drive_url',
                'type' => 'string',
                'required' => false,
                'filters' => null,
                'validators' => null,
            ],
            'folder_id' => [
                'title' => 'folder_id',
                'name' => 'folder_id',
                'type' => 'string',
                'required' => false,
                'filters' => null,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 64,
                    ]
                ],
            ],
            'description' => [
                'title' => 'description',
                'name' => 'description',
                'type' => 'string',
                'required' => false,
                'filters' => null,
                'validators' => null,
            ],
            'tmp_url' => [
                'title' => 'tmp_url',
                'name' => 'tmp_url',
                'type' => 'string',
                'required' => false,
                'filters' => null,
                'validators' => null,
            ],
            'download_successful' => [
                'title' => 'download_successful',
                'name' => 'download_successful',
                'type' => 'boolean',
                'required' => false,
                'filters' => null,
                'validators' => null,
                'default_value' => false
            ]
        ]
    ];

    /**
     * Returns the full path including filename
     * @return string e.g. "/pdf/foo/document.pdf"
     */
    public function getFullPath()
    {
        return $this->path . $this->name;
    }

    /**
     * Returns the storage path (relative to bucket)
     * @return string e.g. "attachments/pdf/foo/document.pdf"
     */
    public function getStoragePath()
    {
        return 'attachments' . $this->path . $this->name;
    }

    /**
     * Returns the public URI for this attachment
     * @return string
     */
    public function getUri()
    {
        $config = Registry::getInstance()->get('config');
        return HelperFunctions::replaceConstantsFromConfig($config['file_handling']['http_src']) . '/attachments' . $this->path . $this->name;
    }

    /**
     * Returns the Storage\File object for this attachment
     * @return \Pressmind\Storage\File
     */
    public function getFile()
    {
        $config = Registry::getInstance()->get('config');
        $bucket = new Bucket($config['file_handling']['storage']);
        $file = new \Pressmind\Storage\File($bucket);
        $file->name = $this->getStoragePath();
        return $file;
    }

    /**
     * Returns the binary file content
     * @return \Pressmind\Storage\File
     * @throws Exception
     */
    public function getBinaryFile()
    {
        $file = $this->getFile();
        $file->read();
        return $file;
    }

    /**
     * Downloads the original file from drive_url and saves it to storage
     * @throws Exception
     */
    public function downloadOriginal()
    {
        $downloader = new \Pressmind\File\Downloader();
        // Force download to overwrite potentially corrupted files
        $storage_file = $downloader->download($this->drive_url, $this->getStoragePath(), true);
        $storage_file->save();
        $this->file_size = $storage_file->filesize();
        if ($this->file_size == 0) {
            throw new Exception('Download failed, filesize is 0');
        }
        $this->download_successful = true;
        $this->mime_type = $storage_file->getMimetype();
        $this->update();
    }

    /**
     * Deletes the file from storage
     * @return bool
     */
    public function deleteFile()
    {
        try {
            $file = $this->getFile();
            if ($file->exists()) {
                $file->delete();
            }
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}
