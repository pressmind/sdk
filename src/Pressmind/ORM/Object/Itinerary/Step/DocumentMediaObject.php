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
use Pressmind\Storage\File;

/**
 * TODO id_picture is missing, this is not required but in some cases very useful.
 * Class DocumentMediaObject
 * @package Pressmind\ORM\Object\Itinerary\Variant\Step
 * @property integer $id
 * @property integer $id_step
 * @property integer $id_media_object
 * @property string $copyright
 * @property string $caption
 * @property string $alt
 * @property string $uri
 * @property string $title
 * @property string $file_name
 * @property integer $sort
 * @property string $tmp_url
 * @property boolean $download_successful
 * @property string $mime_type
 * @property string $code
 * @property string $name
 * @property string $tags
 * @property integer $width
 * @property integer $height
 * @property integer $filesize
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
            'uri' => [
                'title' => 'uri',
                'name' => 'uri',
                'type' => 'string',
                'required' => false,
                'filters' => null,
                'validators' => null,
            ],
            'title' => [
                'title' => 'title',
                'name' => 'title',
                'type' => 'string',
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
            'code' => [
                'title' => 'code',
                'name' => 'code',
                'type' => 'string',
                'required' => false,
                'validators' => null,
                'filters' => null
            ],
            'name' => [
                'title' => 'name',
                'name' => 'name',
                'type' => 'string',
                'required' => false,
                'validators' => null,
                'filters' => null
            ],
            'tags' => [
                'title' => 'tags',
                'name' => 'tags',
                'type' => 'string',
                'required' => false,
                'validators' => null,
                'filters' => null
            ],
            'width' => [
                'title' => 'width',
                'name' => 'width',
                'type' => 'integer',
                'required' => false,
                'validators' => null,
                'filters' => null
            ],
            'height' => [
                'title' => 'height',
                'name' => 'height',
                'type' => 'integer',
                'required' => false,
                'validators' => null,
                'filters' => null
            ],
            'filesize' => [
                'title' => 'filesize',
                'name' => 'filesize',
                'type' => 'integer',
                'required' => false,
                'validators' => null,
                'filters' => null
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

    /**
     * Override ensureDerivatives to use the correct Derivative class for Itinerary images.
     * The parent Picture::ensureDerivatives() uses Picture\Derivative which writes to the wrong table
     * (pmt2core_media_object_image_derivatives instead of pmt2core_itinerary_step_document_media_object_derivatives).
     *
     * @throws Exception
     */
    public function ensureDerivatives()
    {
        $config = Registry::getInstance()->get('config');
        if (empty($config['image_handling']['processor']['derivatives'])) {
            return;
        }
        foreach ($config['image_handling']['processor']['derivatives'] as $derivative_name => $derivative_config) {
            $extensions = ['jpg'];
            if (!empty($derivative_config['webp_create'])) {
                $extensions[] = 'webp';
            }
            foreach ($extensions as $extension) {
                $derivative_file_name = pathinfo($this->file_name, PATHINFO_FILENAME) . '_' . $derivative_name . '.' . $extension;
                $existing_derivative = Derivative::listOne([
                    'id_document_media_object' => $this->getId(),
                    'name' => $derivative_name
                ]);
                if ($existing_derivative) {
                    $File = new File(new Bucket($config['image_handling']['storage']));
                    $File->name = $derivative_file_name;
                    if ($File->exists()) {
                        if ($existing_derivative->download_successful == false) {
                            $existing_derivative->download_successful = true;
                            $existing_derivative->update();
                        }
                    } else {
                        if ($existing_derivative->download_successful == true) {
                            $existing_derivative->download_successful = false;
                            $existing_derivative->update();
                        }
                    }
                } else {
                    $File = new File(new Bucket($config['image_handling']['storage']));
                    $File->name = $derivative_file_name;
                    $derivative = new Derivative();
                    $derivative->id_document_media_object = $this->getId();
                    $derivative->name = $derivative_name;
                    $derivative->file_name = $derivative_file_name;
                    $derivative->width = $derivative_config['max_width'] ?? null;
                    $derivative->height = $derivative_config['max_height'] ?? null;
                    $derivative->download_successful = $File->exists() ? true : false;
                    try {
                        $derivative->create();
                    } catch (Exception $e) {
                        Writer::write('Failed to create derivative entry: ' . $e->getMessage(), Writer::OUTPUT_FILE, 'import', Writer::TYPE_WARNING);
                    }
                }
            }
        }
    }
}
