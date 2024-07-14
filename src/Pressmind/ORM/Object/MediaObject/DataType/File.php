<?php

namespace Pressmind\ORM\Object\MediaObject\DataType;
use Exception;
use Pressmind\HelperFunctions;
use Pressmind\ORM\Object\AbstractObject;
use Pressmind\Registry;
use Pressmind\Storage\Bucket;

/**
 * Class File
 * @package Pressmind\ORM\Object\MediaObject\DataType
 * @property integer $id
 * @property integer $id_media_object
 * @property string $section_name
 * @property string $language
 * @property string $var_name
 * @property integer $id_file
 * @property integer $file_size
 * @property string $file_name
 * @property string $mime_type
 * @property string $description
 * @property string $tmp_url
 * @property string $download_successful
 */
class File extends AbstractObject
{
    protected $_definitions = [
        'class' => [
            'name' => self::class
        ],
        'database' => [
            'table_name' => 'pmt2core_media_object_files',
            'primary_key' => 'id',
        ],
        'properties' => [
            'id' => [
                'title' => 'id',
                'name' => 'id',
                'type' => 'integer',
                'required' => true,
                'filters' => null,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 22,
                    ],
                    [
                        'name' => 'unsigned',
                        'params' => null,
                    ]
                ],
            ],
            'id_media_object' => [
                'title' => 'id_media_object',
                'name' => 'id_media_object',
                'type' => 'integer',
                'required' => true,
                'filters' => null,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 22,
                    ],
                    [
                        'name' => 'unsigned',
                        'params' => null,
                    ]
                ],
                'index' => [
                    'id_media_object' => 'index'
                ]
            ],
            'section_name' => [
                'title' => 'section_name',
                'name' => 'section_name',
                'type' => 'string',
                'required' => false,
                'filters' => null,
                'validators' => null,
            ],
            'language' => [
                'title' => 'language',
                'name' => 'language',
                'type' => 'string',
                'required' => true,
                'filters' => null,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 32,
                    ]
                ],
                'index' => [
                    'language' => 'index'
                ]
            ],
            'var_name'  => [
                'title' => 'var_name',
                'name' => 'var_name',
                'type' => 'string',
                'required' => true,
                'filters' => null,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 255,
                    ]
                ],
                'index' => [
                    'var_name' => 'index'
                ]
            ],
            'id_file' => [
                'title' => 'id_file',
                'name' => 'id_file',
                'type' => 'string',
                'required' => true,
                'filters' => null,
                'validators' => null,
            ],
            'file_name'  => [
                'title' => 'file_name',
                'name' => 'file_name',
                'type' => 'string',
                'required' => false,
                'filters' => null,
                'validators' => null,
            ],
            'file_size' => [
                'title' => 'file_size',
                'name' => 'file_size',
                'type' => 'integer',
                'required' => false,
                'filters' => null,
                'validators' => null,
            ],
            'mime_type'  => [
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
            'description'  => [
                'title' => 'description',
                'name' => 'description',
                'type' => 'string',
                'required' => false,
                'filters' => null,
                'validators' => null,
            ],
            'tmp_url'  => [
                'title' => 'tmp_url',
                'name' => 'tmp_url',
                'type' => 'string',
                'required' => false,
                'filters' => null,
                'validators' => null,
            ],
            'download_successful'  => [
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
     * @return string
     */
    public function getUri() {
        $config = Registry::getInstance()->get('config');
        return HelperFunctions::replaceConstantsFromConfig($config['file_handling']['http_src']) . '/' . $this->file_name;
    }

    /**
     * @return \Pressmind\Storage\File
     */
    public function getFile()
    {
        $config = Registry::getInstance()->get('config');
        $bucket = new Bucket($config['file_handling']['storage']);
        $file = new \Pressmind\Storage\File($bucket);
        $file->name = $this->file_name;
        return $file;
    }

    /**
     * @return \Pressmind\Storage\File
     * @throws Exception
     */
    public function getBinaryFile() {
        $file = $this->getFile();
        $file->read();
        return $file;
    }

    /**
     * @throws Exception
     */
    public function downloadOriginal()
    {
        $downloader = new \Pressmind\File\Downloader();
        $storage_file = $downloader->download($this->tmp_url, $this->file_name);
        $storage_file->save();
        $this->file_size = $storage_file->filesize();
        if($this->file_size == 0) {
            throw new Exception('Download failed, filesize is 0');
        }
        $this->download_successful = true;
        $this->mime_type = $storage_file->getMimetype();
        $this->update();
    }
}
