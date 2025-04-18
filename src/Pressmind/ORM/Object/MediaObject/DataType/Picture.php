<?php

namespace Pressmind\ORM\Object\MediaObject\DataType;
use Aws\S3\Exception\S3Exception;
use Exception;
use Pressmind\HelperFunctions;
use Pressmind\Image\Downloader;
use Pressmind\Image\Processor;
use Pressmind\Log\Writer;
use Pressmind\ORM\Object\AbstractObject;
use Pressmind\ORM\Object\MediaObject\DataType\Picture\Derivative;
use Pressmind\ORM\Object\MediaObject\DataType\Picture\Section;
use Pressmind\Registry;
use Pressmind\Storage\Bucket;
use Pressmind\Storage\File;

/**
 * Class Picture
 * @package Pressmind\ORM\Object\MediaObject\DataType
 * @property integer $id
 * @property integer $id_picture
 * @property integer $id_media_object
 * @property string $section_name
 * @property string $var_name
 * @property string $file_name
 * @property integer $width
 * @property integer $height
 * @property integer $file_size
 * @property string $uri
 * @property string $caption
 * @property string $title
 * @property string $alt
 * @property string $copyright
 * @property boolean $disabled
 * @property integer $sort
 * @property string $tmp_url
 * @property boolean $download_successful
 * @property string $mime_type
 * @property Derivative[] $derivatives
 * @property Section[] $sections
 */
class Picture extends AbstractObject
{

    protected $_definitions = [
        'class' => [
            'name' => self::class
        ],
        'database' => [
            'table_name' => 'pmt2core_media_object_images',
            'primary_key' => 'id',
            'order_columns' => ['sort' => 'ASC']
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
            'id_picture' => [
                'title' => 'id_picture',
                'name' => 'id_picture',
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
                    'id_picture' => 'index'
                ]
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
                'required' => false,
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
                    'language' => 'index'
                ]
            ],
            'caption' => [
                'title' => 'caption',
                'name' => 'caption',
                'type' => 'string',
                'required' => false,
                'filters' => null,
                'validators' => null,
            ],
            'file_name' => [
                'title' => 'file_name',
                'name' => 'file_name',
                'type' => 'string',
                'required' => false,
                'filters' => null,
                'validators' => null,
            ],
            'width' => [
                'title' => 'width',
                'name' => 'width',
                'type' => 'integer',
                'required' => false,
                'filters' => null,
                'validators' => null,
            ],
            'height' => [
                'title' => 'height',
                'name' => 'height',
                'type' => 'integer',
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
            'title' => [
                'title' => 'title',
                'name' => 'title',
                'type' => 'string',
                'required' => false,
                'filters' => null,
                'validators' => null,
            ],
            'alt' => [
                'title' => 'alt',
                'name' => 'alt',
                'type' => 'string',
                'required' => false,
                'filters' => null,
                'validators' => null,
            ],
            'uri' => [
                'title' => 'uri',
                'name' => 'uri',
                'type' => 'string',
                'required' => false,
                'filters' => null,
                'validators' => null,
            ],
            'copyright' => [
                'title' => 'copyright',
                'name' => 'copyright',
                'type' => 'string',
                'required' => false,
                'filters' => null,
                'validators' => null,
            ],
            'sort' => [
                'title' => 'sort',
                'name' => 'sort',
                'type' => 'integer',
                'required' => false,
                'filters' => null,
                'validators' => null,
            ],
            'disabled' => [
                'title' => 'disabled',
                'name' => 'disabled',
                'type' => 'boolean',
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
                'required' => false,
                'filters' => null,
                'validators' => null,
                'relation' => [
                    'type' => 'hasMany',
                    'class' => Derivative::class,
                    'related_id' => 'id_image',
                ],
            ],
            'sections' => [
                'title' => 'sections',
                'name' => 'sections',
                'type' => 'relation',
                'required' => false,
                'filters' => null,
                'validators' => null,
                'relation' => [
                    'type' => 'hasMany',
                    'class' => Section::class,
                    'related_id' => 'id_image',
                    'on_save_related_properties' => [
                        'id' => 'id_image'
                    ],
                ],
            ],
        ]
    ];

    /**
     * @param string $derivativeName
     * @param boolean $force_webp
     * @param string $sectionName (the pressmind image "cropping/cutting"-name)
     * @return string
     */
    public function getUri($derivativeName = null, $force_webp = false, $sectionName = null) {
        $config = Registry::getInstance()->get('config');
        if($this->download_successful == false) {
            return $this->getTmpUri($derivativeName, $sectionName);
        }
        if(is_null($derivativeName)) {
            return HelperFunctions::replaceConstantsFromConfig($config['image_handling']['http_src']) . '/'  . $this->file_name;
        }
        if(!is_null($sectionName)){
            $section = $this->getSection($sectionName);
            if(!is_null($section)){
                return $section->getUri($derivativeName, $force_webp);
            }
        }
        if($derivative = $this->hasDerivative($derivativeName)) {
            $uri = HelperFunctions::replaceConstantsFromConfig($config['image_handling']['http_src']) . '/' . $derivative->file_name;
            if(
                (
                    $config['image_handling']['processor']['webp_support'] == true &&
                    $config['image_handling']['processor']['derivatives'][$derivativeName]['webp_create'] == true &&
                    defined('WEBP_SUPPORT') && WEBP_SUPPORT === true
                ) || $force_webp == true
            ) {
                $path_info = pathinfo($uri);
                $uri = str_replace($path_info['extension'], 'webp', $uri);
            }
            return $uri;
        } else {
            return HelperFunctions::replaceConstantsFromConfig($config['image_handling']['http_src']) . '/'  . $this->file_name;
        }
    }
    /**
     * @param $derivativeName
     * @param bool $asHTMLAttributes
     * @return array|string
     */
    public function getSizes($derivativeName, $asHTMLAttributes = true) {
        $config = Registry::getInstance()->get('config');
        $str = [];
        $array = [];
        if(!empty($config['image_handling']['processor']['derivatives'][$derivativeName]['max_width'])) {
            $str[] = 'width="' .$config['image_handling']['processor']['derivatives'][$derivativeName]['max_width'] . '"';
            $array['width'] = $config['image_handling']['processor']['derivatives'][$derivativeName]['max_width'];
        }
        if(!empty($config['image_handling']['processor']['derivatives'][$derivativeName]['max_height'])) {
            $str[] = 'height="' .$config['image_handling']['processor']['derivatives'][$derivativeName]['max_height'] . '"';
            $array['height'] = $config['image_handling']['processor']['derivatives'][$derivativeName]['max_height'];
        }
        if($asHTMLAttributes === true) {
            return implode(' ', $str);
        }
        return $array;
    }

    /**
     * @param string $derivativeName
     * @param string $sectionName
     * @return string
     */
    public function getTmpUri($derivativeName = null, $sectionName = null)
    {
        $config = Registry::getInstance()->get('config');
        $image_width = $this->width;
        $image_height = $this->height;
        $image_max_width = $config['image_handling']['processor']['derivatives'][$derivativeName]['max_width'] ?? 1980;
        $image_max_height = $config['image_handling']['processor']['derivatives'][$derivativeName]['max_height'] ?? 1980;
        $onlyheight = false;
        $onlywidth = false;
        $crop = $config['image_handling']['processor']['derivatives'][$derivativeName]['crop'] ?? false;
        if(!empty($image_height) && !$crop && !empty($config['image_handling']['processor']['derivatives'][$derivativeName]['preserve_aspect_ratio'])) {
            $aspectRatio = $image_width / $image_height;
            if ($image_max_width / $aspectRatio < $image_max_height) {
                $onlywidth = true;
            } else {
                $onlyheight = true;
            }
        }
        $tmp_url = $this->tmp_url;
        if(!is_null($sectionName)){
            $section = $this->getSection($sectionName);
            if(!is_null($section)){
                $tmp_url = $section->tmp_url;
            }
        }
        $parsed_query = [];
        $parsed_url = parse_url($tmp_url);
        parse_str($parsed_url['query'], $parsed_query);
        unset($parsed_query['v']);
        if(!is_null($derivativeName)) {
            if($crop) {
                $parsed_query['w'] = $image_max_width;
                $parsed_query['h'] = $image_max_height;
            } else {
                if($onlywidth || !$onlyheight){
                    $parsed_query['w'] = $image_max_width;
                    unset($parsed_query['h']);
                }
                if($onlyheight ||!$onlywidth){
                    $parsed_query['h'] = $image_max_height;
                    unset($parsed_query['w']);
                }
            }
        }
        return $parsed_url['scheme'] . '://' . $parsed_url['host'] . $parsed_url['path'] . '?' . http_build_query($parsed_query);
    }



    /**
     * @param $derivativeName
     * @return bool|Derivative
     */
    public function hasDerivative($derivativeName)
    {
        if(is_null($this->derivatives)) {
            return false;
        }
        foreach ($this->derivatives as $derivative) {
            if($derivative->name == $derivativeName) {
                return $derivative;
            }
        }
        return false;
    }

    /**
     * @param string $sectionName
     * @return Section|null
     */
    public function getSection($sectionName) {
        if(is_array($this->sections) !== false) {
            foreach ($this->sections as $section) {
                if($section->section_name == $sectionName) {
                    return $section;
                }
            }
        }
        return null;
    }

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
        if($retry_counter > 0 && $max_retries >= $retry_counter) {
            Writer::write('ID ' . $this->getId() . ': Retry No. ' . $retry_counter . ' of downloading image from ' . $download_url, WRITER::TYPE_ERROR, 'image_processor', Writer::TYPE_INFO);
        }
        if($max_retries >= $retry_counter) {
            try {
                $this->_checkMimetype($this->mime_type);
                $storage_file = $downloader->download($download_url, $this->file_name);
                $storage_file->name = $this->file_name;
                $storage_file->save();
                $this->download_successful = true;
                $this->update();
                return $storage_file;
            } catch (Exception $e) {
                $last_error = $e->getMessage();
                Writer::write('ID ' . $this->getId() . ': Downloading image from ' . $download_url . ' failed at try ' . $retry_counter . '. Error: ' . $last_error, WRITER::OUTPUT_SCREEN, 'image_processor', Writer::TYPE_ERROR);
                $this->downloadOriginal(false, ($retry_counter + 1), $last_error);
            } catch (S3Exception $e) {
                $last_error = $e->getMessage();
            }
        } else {
            $err = 'Download of image ID: ' . $this->id . ' failed! Maximum retries of ' . $max_retries . ' exceeded! Last error: ' . $last_error;
            $downloader->writeLockFile($this->file_name, $err);
            throw new Exception($err);
        }
    }

    /**
     * @param $mimetype
     * @throws Exception
     */
    protected function _checkMimetype($mimetype) {
        $allowed_mimetypes = [
            'image/jpeg',
            'image/jpg',
            'image/gif',
            'image/png',
        ];
        if(!in_array($mimetype, $allowed_mimetypes)) {
            throw new Exception('Mimetype ' . $mimetype . ' is not allowed for images');
        }
    }

    /**
     * @throws Exception
     */
    public function removeDerivatives()
    {
        foreach ($this->derivatives as $derivative) {
            $derivative->delete();
        }
    }

    /**
     * @param Processor\Config $derivative_config
     * @param Processor\AdapterInterface $image_processor
     * @param \Pressmind\Storage\File $image
     * @throws Exception
     */
    public function createDerivative($derivative_config, $image_processor, $image)
    {
        $derivative_binary_file = $image_processor->process($derivative_config, $image, $derivative_config->name);
        $derivative = new Derivative();
        $derivative->id_image = $this->getId();
        $derivative->id_media_object = $this->id_media_object;
        $derivative->name = $derivative_config->name;
        $derivative->file_name = $derivative_binary_file->name;
        $derivative->download_successful = true;
        $derivative->width = $derivative_config->max_width;
        $derivative->height = $derivative_config->max_height;
        $derivative->create();
        $derivative_binary_file->save();
        $webp_processor = new Processor\Adapter\WebPicture();
        $webp_processor->process($derivative_config, $derivative_binary_file, $derivative_config->name);
        unset($derivative_binary_file);
    }

    /**
     * @return \Pressmind\Storage\File
     */
    public function getFile()
    {
        $config = Registry::getInstance()->get('config');
        $bucket = new Bucket($config['image_handling']['storage']);
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
     * @return bool
     */
    public function exists(){
        $config = Registry::getInstance()->get('config');
        $file = new File(new Bucket($config['image_handling']['storage']));
        $file->name = $this->file_name;
        return $file->exists();
    }
}
