<?php


namespace Pressmind\ORM\Object\Itinerary\Step;


use \Exception;
use Pressmind\Image\Downloader;
use Pressmind\Image\Processor\Adapter\WebPicture;
use Pressmind\Image\Processor\Config;
use Pressmind\ORM\Object\AbstractObject;
use Pressmind\ORM\Object\Itinerary\Step\DocumentMediaObject\Derivative;
use Pressmind\Registry;

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
 * @property string $uri
 * @property Derivative[] $derivatives
 */
class DocumentMediaObject extends AbstractObject
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
            'uri' => [
                'title' => 'uri',
                'name' => 'uri',
                'type' => 'string',
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

    public function hasDerivative($derivativeName) {
        foreach ($this->derivatives as $derivative) {
            if($derivative->name == $derivativeName) {
                return $derivative;
            }
        }
        return false;
    }

    public function getUri($derivativeName)
    {
        $config = Registry::getInstance()->get('config');
        if(!isset($config['image_processor']['derivatives'][$derivativeName])) {
            throw new Exception('Derivative ' . $derivativeName . ' is not set in configuration');
        }
        if($derivative = $this->hasDerivative($derivativeName)) {
            $uri = $config['image_processor']['image_http_path'] . $derivative->file_name;
        } else {
            $uri = $config['image_processor']['image_http_path'] . $this->_generateDerivative($derivativeName)->file_name;
        }
        if($config['image_processor']['webp_support'] == true && $config['image_processor']['derivatives'][$derivativeName]['webp_create'] == true && defined('WEBP_SUPPORT') && WEBP_SUPPORT === true) {
            $path_info = pathinfo($uri);
            if(file_exists(BASE_PATH . DIRECTORY_SEPARATOR . $config['image_processor']['image_file_path'] . DIRECTORY_SEPARATOR . str_replace($path_info['extension'], 'webp', $derivative->file_name))) {
                $uri = str_replace($path_info['extension'], 'webp', $uri);
            }
        }
        return $uri;
    }

    private function _generateDerivative($derivativeName)
    {
        $config = Registry::getInstance()->get('config');
        $derivative_config = Config::create($derivativeName, $config['image_processor']['derivatives'][$derivativeName]);
        $tmp_url = 'https://api.pressmind.net/image.php?api=' . $config['rest']['client']['api_key'] . '&id=' . $this->id_media_object . '&w=' . $derivative_config->max_width . '&h=' . $derivative_config->max_height;
        $download = new Downloader();
        $newname = $download->download($tmp_url, BASE_PATH . DIRECTORY_SEPARATOR . $config['image_processor']['image_file_path'], 'itinerary_' . $this->id_media_object. '_' . $derivativeName);
        $derivative = new Derivative();
        $derivative->name = $derivativeName;
        $derivative->id_document_media_object = $this->getId();
        $derivative->file_name = $newname;
        $derivative->width = $derivative_config->max_width;
        $derivative->height = $derivative_config->max_height;
        $derivative->create();
        $webp_processor = new WebPicture();
        $webp_processor->process($derivative_config, BASE_PATH . DIRECTORY_SEPARATOR . $config['image_processor']['image_file_path'] . DIRECTORY_SEPARATOR . str_replace('_' . $derivativeName, '', $derivative->file_name), $derivative_config->name);
        return $derivative;
    }
}
