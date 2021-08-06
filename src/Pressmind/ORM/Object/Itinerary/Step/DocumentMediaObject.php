<?php


namespace Pressmind\ORM\Object\Itinerary\Step;


use \Exception;
use Pressmind\HelperFunctions;
use Pressmind\Image\Downloader;
use Pressmind\Image\Processor\Adapter\Factory;
use Pressmind\Image\Processor\Adapter\WebPicture;
use Pressmind\Image\Processor\Config;
use Pressmind\Log\Writer;
use Pressmind\ORM\Object\AbstractObject;
use Pressmind\ORM\Object\Itinerary\Step\DocumentMediaObject\Derivative;
use Pressmind\ORM\Object\MediaObject\DataType\Picture;
use Pressmind\Registry;
use Pressmind\Storage\Bucket;

/**
 * Class DocumentMediaObject
 * @package Pressmind\ORM\Object\Itinerary\Variant\Step
 * @property integer $id
 * @property integer $id_step
 * @property integer $id_media_object
 * @property string $copyright
 * @property string $caption
 * @property string $alt
 * @property string $title
 * @property integer $file_size
 * @property string $file_name
 * @property integer $sort
 * @property string $tmp_url
 * @property boolean $download_successful
 * @property string $mime_type
 * @property Derivative[] $derivatives
 */
class DocumentMediaObject extends Picture
{
    protected $_definitions = [
        'class' => [
            'name' => self::class
        ],
        'database' => [
            'table_name' => 'pmt2core_itinerary_step_document_media_objects',
            'primary_key' => 'id'
        ],
        'properties' => [
            'id' => [
                'title' => 'id',
                'name' => 'id',
                'type' => 'integer',
                'required' => true,
                'validators' => null,
                'filters' => null
            ],
            'id_step' => [
                'title' => 'id_step',
                'name' => 'id_step',
                'type' => 'integer',
                'required' => false,
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
                    'id_step' => 'index'
                ],
                'filters' => null
            ],
            'id_media_object' => [
                'title' => 'id_media_object',
                'name' => 'id_media_object',
                'type' => 'integer',
                'required' => false,
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
                ],
                'filters' => null
            ],
            'copyright' => [
                'title' => 'copyright',
                'name' => 'copyright',
                'type' => 'string',
                'required' => false,
                'validators' => null,
                'filters' => null
            ],
            'caption' => [
                'title' => 'caption',
                'name' => 'caption',
                'type' => 'string',
                'required' => false,
                'validators' => null,
                'filters' => null
            ],
            'alt' => [
                'title' => 'alt',
                'name' => 'alt',
                'type' => 'string',
                'required' => false,
                'validators' => null,
                'filters' => null
            ],
            'title' => [
                'title' => 'title',
                'name' => 'title',
                'type' => 'string',
                'required' => false,
                'validators' => null,
                'filters' => null
            ],
            'file_size' => [
                'title' => 'file_size',
                'name' => 'file_size',
                'type' => 'integer',
                'required' => false,
                'validators' => null,
                'filters' => null
            ],
            'file_name' => [
                'title' => 'file_name',
                'name' => 'file_name',
                'type' => 'string',
                'required' => false,
                'validators' => null,
                'filters' => null
            ],
            'sort' => [
                'title' => 'sort',
                'name' => 'sort',
                'type' => 'integer',
                'required' => false,
                'filters' => null,
                'validators' => null,
            ],
            'tmp_url' => [
                'title' => 'tmp_url',
                'name' => 'tmp_url',
                'type' => 'string',
                'required' => false,
                'validators' => null,
                'filters' => null
            ],
            'download_successful'  => [
                'title' => 'download_successful',
                'name' => 'download_successful',
                'type' => 'boolean',
                'required' => false,
                'filters' => null,
                'validators' => null,
                'default_value' => false
            ],
            'mime_type' => [
                'title' => 'mime_type',
                'name' => 'mime_type',
                'type' => 'string',
                'required' => false,
                'filters' => null,
                'validators' => null,
            ],
            'derivatives' => [
                'title' => 'derivatives',
                'name' => 'derivatives',
                'type' => 'relation',
                'relation' => [
                    'type' => 'hasMany',
                    'related_id' => 'id_document_media_object',
                    'class' => Derivative::class,
                    'filters' => null,
                    'on_save_related_properties' => ['id' => 'id_document_media_object']
                ],
                'required' => false,
                'validators' => null,
                'filters' => null
            ]
        ]
    ];

    /**
     * @param bool $use_cache
     * @param integer $retry_counter
     * @return \Pressmind\Storage\File
     * @throws Exception
     */
    public function downloadOriginal($use_cache = true, $retry_counter = 0, $last_error = null)
    {
        $max_retries = 1;
        $download_url = $this->tmp_url;
        if($use_cache == false) {
            $download_url .= '&cache=0';
        }
        $downloader = new Downloader();
        $query = [];
        $url = parse_url($this->tmp_url);
        parse_str($url['query'], $query);
        if($retry_counter > 0 && $max_retries >= $retry_counter) {
            Writer::write('ID ' . $this->getId() . ': Retry No. ' . $retry_counter . ' of downloading itinerary image from ' . $download_url, WRITER::OUTPUT_FILE, 'image_processor', Writer::TYPE_INFO);
        }
        $tmp_file_name = empty($this->file_name) ? $this->id_step . '_' . $this->id_media_object . '.tmp' : $this->file_name;
        if($max_retries >= $retry_counter) {
            try {
                $storage_file = $downloader->download($download_url, $tmp_file_name);
                $mime_type = $storage_file->getMimetype();
                $this->_checkMimetype($mime_type);
                $new_file_name = 'itinerary_' . $this->id_step . '_' . $this->id_media_object . '.' . HelperFunctions::getExtensionFromMimeType($storage_file->getMimetype());
                $this->download_successful = true;
                $this->mime_type = $storage_file->getMimetype();
                $storage_file->name = $new_file_name;
                $this->file_name = $new_file_name;
                $storage_file->save();
                $this->update();
                return $storage_file;
            } catch (Exception $e) {
                $last_error = $e->getMessage();
                Writer::write('ID ' . $this->getId() . ': Downloading itinerary image from ' . $download_url . ' failed at try ' . $retry_counter . '. Error: ' . $last_error, WRITER::OUTPUT_FILE, 'image_processor', Writer::TYPE_ERROR);
                $this->downloadOriginal(false, ($retry_counter + 1), $last_error);
            } catch (S3Exception $e) {
                $last_error = $e->getMessage();
            }
        } else {
            throw new Exception('Download of itinerary image ID: ' . $this->id . ' failed! Maximum retries of ' . $max_retries . ' exceeded! Last error: ' . $last_error);
        }
    }

    /**
     * @param \Pressmind\Image\Processor\Config $derivative_config
     * @param \Pressmind\Image\Processor\AdapterInterface $image_processor
     * @param \Pressmind\Storage\File $image
     * @throws Exception
     */
    public function createDerivative($derivative_config, $image_processor, $image)
    {
        $derivative_binary_file = $image_processor->process($derivative_config, $image, $derivative_config->name);
        $derivative = new Derivative();
        $derivative->id_document_media_object= $this->getId();
        $derivative->name = $derivative_config->name;
        $derivative->file_name = $derivative_binary_file->name;
        $derivative->width = $derivative_config->max_width;
        $derivative->height = $derivative_config->max_height;
        $derivative->create();
        $derivative_binary_file->save();
        $webp_processor = new \Pressmind\Image\Processor\Adapter\WebPicture();
        $webp_processor->process($derivative_config, $derivative_binary_file, $derivative_config->name);
        unset($derivative_binary_file);
    }
}
