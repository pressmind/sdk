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
     * @param boolean $debug
     * @return string
     */
    public function getUri($derivativeName = null, $force_webp = false, $sectionName = null, $debug = false) {
        $config = Registry::getInstance()->get('config');
        $is_section = ($this instanceof Section);
        if($debug) {
            $object_type = $is_section ? 'Section' : 'Picture';
            Writer::write('getUri() called for ' . $object_type . ' ID: ' . $this->getId() . ', derivativeName: ' . ($derivativeName ?? 'null') . ', sectionName: ' . ($sectionName ?? 'null') . ', force_webp: ' . ($force_webp ? 'true' : 'false') . ', download_successful: ' . ($this->download_successful ? 'true' : 'false'), WRITER::OUTPUT_BOTH, 'picture_debug', Writer::TYPE_INFO);
            if($is_section) {
                Writer::write('This is a Section object. Section details: section_name=' . ($this->section_name ?? 'NULL') . ', file_name=' . ($this->file_name ?? 'NULL'), WRITER::OUTPUT_BOTH, 'picture_debug', Writer::TYPE_INFO);
            }
        }
        if($this->download_successful == false) {
            if($debug) {
                Writer::write('download_successful is false, returning getTmpUri()', WRITER::OUTPUT_BOTH, 'picture_debug', Writer::TYPE_INFO);
            }
            return $this->getTmpUri($derivativeName, $sectionName);
        }
        if(is_null($derivativeName)) {
            if($debug) {
                Writer::write('derivativeName is null, returning original file_name: ' . $this->file_name, WRITER::OUTPUT_BOTH, 'picture_debug', Writer::TYPE_INFO);
            }
            return HelperFunctions::replaceConstantsFromConfig($config['image_handling']['http_src']) . '/'  . $this->file_name;
        }
        if(!is_null($sectionName)){
            if($debug) {
                Writer::write('Section delegation requested: sectionName=' . $sectionName . ', derivativeName=' . $derivativeName, WRITER::OUTPUT_BOTH, 'picture_debug', Writer::TYPE_INFO);
                // Zeige verfügbare Sections vor der Suche
                if(!is_null($this->sections) && is_array($this->sections)) {
                    Writer::write('Available sections before search:', WRITER::OUTPUT_BOTH, 'picture_debug', Writer::TYPE_INFO);
                    foreach($this->sections as $idx => $sec) {
                        Writer::write('  Section[' . $idx . ']: section_name=' . ($sec->section_name ?? 'NULL') . ', id=' . $sec->getId(), WRITER::OUTPUT_BOTH, 'picture_debug', Writer::TYPE_INFO);
                    }
                } else {
                    Writer::write('sections relation is ' . (is_null($this->sections) ? 'NULL' : 'not an array'), WRITER::OUTPUT_BOTH, 'picture_debug', Writer::TYPE_INFO);
                }
            }
            $section = $this->getSection($sectionName, $debug);
            if(!is_null($section)){
                if($debug) {
                    Writer::write('Section found! Delegating to section->getUri()', WRITER::OUTPUT_BOTH, 'picture_debug', Writer::TYPE_INFO);
                    Writer::write('Section details: id=' . $section->getId() . ', section_name=' . ($section->section_name ?? 'NULL') . ', file_name=' . ($section->file_name ?? 'NULL') . ', download_successful=' . ($section->download_successful ? 'true' : 'false'), WRITER::OUTPUT_BOTH, 'picture_debug', Writer::TYPE_INFO);
                    Writer::write('Calling section->getUri() with: derivativeName=' . $derivativeName . ', force_webp=' . ($force_webp ? 'true' : 'false') . ', sectionName=null, debug=true', WRITER::OUTPUT_BOTH, 'picture_debug', Writer::TYPE_INFO);
                }
                return $section->getUri($derivativeName, $force_webp, null, $debug);
            } else {
                if($debug) {
                    Writer::write('Section NOT found! Cannot delegate to section->getUri(). Will continue with Picture-level derivative search.', WRITER::OUTPUT_BOTH, 'picture_debug', Writer::TYPE_INFO);
                }
            }
        }
        if($debug) {
            $relation_type = $is_section ? 'Section-derivatives (id_image_section)' : 'Picture-derivatives (id_image)';
            Writer::write('derivatives relation status (' . $relation_type . '): ' . (is_null($this->derivatives) ? 'NULL' : (is_array($this->derivatives) ? 'ARRAY with ' . count($this->derivatives) . ' items' : gettype($this->derivatives))), WRITER::OUTPUT_BOTH, 'picture_debug', Writer::TYPE_INFO);
            if(is_array($this->derivatives)) {
                Writer::write('Available derivatives in relation:', WRITER::OUTPUT_BOTH, 'picture_debug', Writer::TYPE_INFO);
                foreach($this->derivatives as $idx => $der) {
                    Writer::write('  Derivative[' . $idx . ']: id=' . $der->getId() . ', name=' . $der->name . ', file_name=' . $der->file_name . ', id_image=' . ($der->id_image ?? 'NULL') . ', id_image_section=' . ($der->id_image_section ?? 'NULL'), WRITER::OUTPUT_BOTH, 'picture_debug', Writer::TYPE_INFO);
                }
            } else {
                Writer::write('No derivatives in relation (will query database)', WRITER::OUTPUT_BOTH, 'picture_debug', Writer::TYPE_INFO);
            }
        }
        if($derivative = $this->hasDerivative($derivativeName, $debug)) {
            if($debug) {
                Writer::write('Derivative found: name=' . $derivative->name . ', file_name=' . $derivative->file_name . ', id=' . $derivative->getId(), WRITER::OUTPUT_BOTH, 'picture_debug', Writer::TYPE_INFO);
            }
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
                if($debug) {
                    Writer::write('WebP version requested, returning: ' . $uri, WRITER::OUTPUT_BOTH, 'picture_debug', Writer::TYPE_INFO);
                }
            }
            if($debug) {
                Writer::write('Returning URI: ' . $uri, WRITER::OUTPUT_BOTH, 'picture_debug', Writer::TYPE_INFO);
            }
            return $uri;
        } else {
            if($debug) {
                Writer::write('Derivative NOT found, checking for fallback...', WRITER::OUTPUT_BOTH, 'picture_debug', Writer::TYPE_INFO);
            }
            if(!is_null($this->derivatives) && is_array($this->derivatives) && count($this->derivatives) > 0) {
                $fallback_derivative = $this->derivatives[0];
                if($debug) {
                    Writer::write('Using fallback derivative: name=' . $fallback_derivative->name . ', file_name=' . $fallback_derivative->file_name, WRITER::OUTPUT_BOTH, 'picture_debug', Writer::TYPE_INFO);
                }
                $uri = HelperFunctions::replaceConstantsFromConfig($config['image_handling']['http_src']) . '/' . $fallback_derivative->file_name;
                if(
                    (
                        $config['image_handling']['processor']['webp_support'] == true &&
                        isset($config['image_handling']['processor']['derivatives'][$fallback_derivative->name]['webp_create']) &&
                        $config['image_handling']['processor']['derivatives'][$fallback_derivative->name]['webp_create'] == true &&
                        defined('WEBP_SUPPORT') && WEBP_SUPPORT === true
                    ) || $force_webp == true
                ) {
                    $path_info = pathinfo($uri);
                    $uri = str_replace($path_info['extension'], 'webp', $uri);
                }
                if($debug) {
                    Writer::write('Returning fallback URI: ' . $uri, WRITER::OUTPUT_BOTH, 'picture_debug', Writer::TYPE_INFO);
                }
                return $uri;
            }
            if($debug) {
                Writer::write('No derivatives available, returning getTmpUri()', WRITER::OUTPUT_BOTH, 'picture_debug', Writer::TYPE_INFO);
            }
            return $this->getTmpUri($derivativeName, $sectionName);
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
        $hasCropCoordinates = isset($parsed_query['cw']) || isset($parsed_query['cx']) || isset($parsed_query['cy']);
        if(!is_null($derivativeName) && !$hasCropCoordinates) {
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
     * @param boolean $debug
     * @return bool|Derivative
     */
    public function hasDerivative($derivativeName, $debug = false)
    {
        $is_section = ($this instanceof Section);
        $object_type = $is_section ? 'Section' : 'Picture';
        if($debug) {
            Writer::write('hasDerivative() called for ' . $object_type . ' ID: ' . $this->getId() . ', derivativeName: ' . $derivativeName, WRITER::OUTPUT_BOTH, 'picture_debug', Writer::TYPE_INFO);
            if($is_section) {
                Writer::write('This is a Section. Section details: section_name=' . ($this->section_name ?? 'NULL') . ', file_name=' . ($this->file_name ?? 'NULL'), WRITER::OUTPUT_BOTH, 'picture_debug', Writer::TYPE_INFO);
            }
        }
        if(!is_null($this->derivatives) && is_array($this->derivatives)) {
            if($debug) {
                $relation_type = $is_section ? 'Section-derivatives (id_image_section)' : 'Picture-derivatives (id_image)';
                Writer::write('Checking ' . count($this->derivatives) . ' derivatives in loaded relation (' . $relation_type . ')...', WRITER::OUTPUT_BOTH, 'picture_debug', Writer::TYPE_INFO);
            }
            foreach ($this->derivatives as $idx => $derivative) {
                if($debug) {
                    Writer::write('  Checking derivative[' . $idx . ']: id=' . $derivative->getId() . ', name=' . $derivative->name . ' (looking for: ' . $derivativeName . ')', WRITER::OUTPUT_BOTH, 'picture_debug', Writer::TYPE_INFO);
                }
                if($derivative->name == $derivativeName) {
                    if($debug) {
                        Writer::write('  MATCH FOUND in relation! Returning derivative ID: ' . $derivative->getId(), WRITER::OUTPUT_BOTH, 'picture_debug', Writer::TYPE_INFO);
                    }
                    return $derivative;
                }
            }
            if($debug) {
                Writer::write('No match found in loaded relation', WRITER::OUTPUT_BOTH, 'picture_debug', Writer::TYPE_INFO);
            }
        } else {
            if($debug) {
                Writer::write('derivatives relation is ' . (is_null($this->derivatives) ? 'NULL' : 'not an array'), WRITER::OUTPUT_BOTH, 'picture_debug', Writer::TYPE_INFO);
            }
        }
        if(empty($this->getId())) {
            if($debug) {
                Writer::write($object_type . ' ID is empty, cannot query database', WRITER::OUTPUT_BOTH, 'picture_debug', Writer::TYPE_INFO);
            }
            return false;
        }
        if($debug) {
            $search_field = $is_section ? 'id_image_section' : 'id_image';
            Writer::write('Querying database for derivative: ' . $search_field . '=' . $this->getId() . ', name=' . $derivativeName . ($is_section ? ' (Section: ' . ($this->section_name ?? 'NULL') . ')' : ' (Picture)'), WRITER::OUTPUT_BOTH, 'picture_debug', Writer::TYPE_INFO);
        }
        if($is_section) {
            $derivative = Derivative::listOne([
                'id_image_section' => $this->getId(),
                'name' => $derivativeName
            ]);
        } else {
            $derivative = Derivative::listOne([
                'id_image' => $this->getId(),
                'name' => $derivativeName
            ]);
        }
        if($debug && !$derivative) {
            if($is_section) {
                $all_section_derivatives = Derivative::listAll(['id_image_section' => $this->getId()]);
                Writer::write('Found ' . count($all_section_derivatives) . ' derivatives with id_image_section=' . $this->getId() . ':', WRITER::OUTPUT_BOTH, 'picture_debug', Writer::TYPE_INFO);
                foreach($all_section_derivatives as $idx => $der) {
                    Writer::write('  Derivative[' . $idx . ']: id=' . $der->getId() . ', name=' . $der->name . ', file_name=' . $der->file_name . ', id_image=' . ($der->id_image ?? 'NULL') . ', id_image_section=' . $der->id_image_section, WRITER::OUTPUT_BOTH, 'picture_debug', Writer::TYPE_INFO);
                }
            } else {
                $all_derivatives = Derivative::listAll(['id_image' => $this->getId()]);
                Writer::write('Derivative NOT found. Found ' . count($all_derivatives) . ' total derivatives with id_image=' . $this->getId() . ':', WRITER::OUTPUT_BOTH, 'picture_debug', Writer::TYPE_INFO);
                if(count($all_derivatives) > 0) {
                    foreach($all_derivatives as $idx => $der) {
                        Writer::write('  Derivative[' . $idx . ']: id=' . $der->getId() . ', name=' . $der->name . ', file_name=' . $der->file_name . ', id_image=' . ($der->id_image ?? 'NULL') . ', id_image_section=' . ($der->id_image_section ?? 'NULL') . ', download_successful=' . ($der->download_successful ? 'true' : 'false'), WRITER::OUTPUT_BOTH, 'picture_debug', Writer::TYPE_INFO);
                    }
                } else {
                    Writer::write('  No derivatives found in database for this Picture', WRITER::OUTPUT_BOTH, 'picture_debug', Writer::TYPE_INFO);
                }
            }
        }
        
        if($derivative) {
            if($debug) {
                Writer::write('Derivative found in database! ID: ' . $derivative->getId() . ', file_name: ' . $derivative->file_name . ', id_image: ' . ($derivative->id_image ?? 'NULL') . ', id_image_section: ' . ($derivative->id_image_section ?? 'NULL'), WRITER::OUTPUT_BOTH, 'picture_debug', Writer::TYPE_INFO);
            }
            return $derivative;
        }
        
        if($debug) {
            Writer::write('Derivative NOT found in database either (checked id_image and ' . ($this instanceof Section ? 'id_image_section' : 'only id_image') . ')', WRITER::OUTPUT_BOTH, 'picture_debug', Writer::TYPE_INFO);
        }
        
        return false;
    }

    /**
     * @param string $sectionName
     * @param boolean $debug
     * @return Section|null
     */
    public function getSection($sectionName, $debug = false) {
        if($debug) {
            Writer::write('getSection() called for Picture ID: ' . $this->getId() . ', looking for sectionName: ' . $sectionName, WRITER::OUTPUT_BOTH, 'picture_debug', Writer::TYPE_INFO);
            Writer::write('sections relation status: ' . (is_null($this->sections) ? 'NULL' : (is_array($this->sections) ? 'ARRAY with ' . count($this->sections) . ' items' : gettype($this->sections))), WRITER::OUTPUT_BOTH, 'picture_debug', Writer::TYPE_INFO);
        }
        
        if(is_array($this->sections) !== false) {
            if($debug) {
                Writer::write('Available sections:', WRITER::OUTPUT_BOTH, 'picture_debug', Writer::TYPE_INFO);
                foreach($this->sections as $idx => $section) {
                    Writer::write('  Section[' . $idx . ']: id=' . $section->getId() . ', section_name=' . ($section->section_name ?? 'NULL') . ', file_name=' . ($section->file_name ?? 'NULL') . ', download_successful=' . ($section->download_successful ? 'true' : 'false'), WRITER::OUTPUT_BOTH, 'picture_debug', Writer::TYPE_INFO);
                }
            }
            foreach ($this->sections as $section) {
                if($section->section_name == $sectionName) {
                    if($debug) {
                        Writer::write('Section FOUND! ID: ' . $section->getId() . ', section_name: ' . $section->section_name, WRITER::OUTPUT_BOTH, 'picture_debug', Writer::TYPE_INFO);
                    }
                    return $section;
                }
            }
            if($debug) {
                Writer::write('Section NOT FOUND in loaded sections. Searched for: ' . $sectionName, WRITER::OUTPUT_BOTH, 'picture_debug', Writer::TYPE_INFO);
            }
        } else {
            if($debug) {
                Writer::write('sections is not an array, cannot search for section', WRITER::OUTPUT_BOTH, 'picture_debug', Writer::TYPE_INFO);
            }
        }
        return null;
    }

    /**
     * @param boolean $output_to_log
     * @return array|void
     */
    public function info($output_to_log = true) {
        $info = [
            'picture' => [
                'id' => $this->getId(),
                'id_media_object' => $this->id_media_object ?? null,
                'file_name' => $this->file_name ?? null,
                'download_successful' => $this->download_successful ?? false,
                'width' => $this->width ?? null,
                'height' => $this->height ?? null,
                'file_size' => $this->file_size ?? null,
            ],
            'derivatives' => [],
            'sections' => []
        ];
        $picture_derivatives = Derivative::listAll(['id_image' => $this->getId()]);
        foreach($picture_derivatives as $derivative) {
            $info['derivatives'][] = [
                'id' => $derivative->getId(),
                'name' => $derivative->name ?? null,
                'file_name' => $derivative->file_name ?? null,
                'width' => $derivative->width ?? null,
                'height' => $derivative->height ?? null,
                'download_successful' => $derivative->download_successful ?? false,
                'id_image' => $derivative->id_image ?? null,
                'id_image_section' => $derivative->id_image_section ?? null,
            ];
        }
        $sections = Section::listAll(['id_image' => $this->getId()]);
        foreach($sections as $section) {
            $section_info = [
                'id' => $section->getId(),
                'id_media_object' => $section->id_media_object ?? null,
                'section_name' => $section->section_name ?? null,
                'file_name' => $section->file_name ?? null,
                'download_successful' => $section->download_successful ?? false,
                'width' => $section->width ?? null,
                'height' => $section->height ?? null,
                'file_size' => $section->file_size ?? null,
                'derivatives' => []
            ];
            $section_derivatives = Derivative::listAll(['id_image_section' => $section->getId()]);
            foreach($section_derivatives as $section_derivative) {
                $section_info['derivatives'][] = [
                    'id' => $section_derivative->getId(),
                    'name' => $section_derivative->name ?? null,
                    'file_name' => $section_derivative->file_name ?? null,
                    'width' => $section_derivative->width ?? null,
                    'height' => $section_derivative->height ?? null,
                    'download_successful' => $section_derivative->download_successful ?? false,
                    'id_image' => $section_derivative->id_image ?? null,
                    'id_image_section' => $section_derivative->id_image_section ?? null,
                ];
            }

            $info['sections'][] = $section_info;
        }

        if($output_to_log) {
            Writer::write('=== PICTURE INFO ===', WRITER::OUTPUT_BOTH, 'picture_info', Writer::TYPE_INFO);
            Writer::write('Picture ID: ' . $info['picture']['id'], WRITER::OUTPUT_BOTH, 'picture_info', Writer::TYPE_INFO);
            Writer::write('MediaObject ID: ' . ($info['picture']['id_media_object'] ?? 'N/A'), WRITER::OUTPUT_BOTH, 'picture_info', Writer::TYPE_INFO);
            Writer::write('File Name: ' . ($info['picture']['file_name'] ?? 'N/A'), WRITER::OUTPUT_BOTH, 'picture_info', Writer::TYPE_INFO);
            Writer::write('Download Successful: ' . ($info['picture']['download_successful'] ? 'true' : 'false'), WRITER::OUTPUT_BOTH, 'picture_info', Writer::TYPE_INFO);
            Writer::write('Dimensions: ' . ($info['picture']['width'] ?? 'N/A') . 'x' . ($info['picture']['height'] ?? 'N/A'), WRITER::OUTPUT_BOTH, 'picture_info', Writer::TYPE_INFO);
            Writer::write('', WRITER::OUTPUT_BOTH, 'picture_info', Writer::TYPE_INFO);

            Writer::write('PICTURE DERIVATIVES (' . count($info['derivatives']) . '):', WRITER::OUTPUT_BOTH, 'picture_info', Writer::TYPE_INFO);
            if(count($info['derivatives']) > 0) {
                foreach($info['derivatives'] as $idx => $der) {
                    Writer::write('  [' . $idx . '] ID: ' . $der['id'] . ' | Name: ' . ($der['name'] ?? 'N/A') . ' | File: ' . ($der['file_name'] ?? 'N/A') . ' | Size: ' . ($der['width'] ?? 'N/A') . 'x' . ($der['height'] ?? 'N/A') . ' | Download: ' . ($der['download_successful'] ? 'OK' : 'FAILED'), WRITER::OUTPUT_BOTH, 'picture_info', Writer::TYPE_INFO);
                }
            } else {
                Writer::write('  No derivatives found', WRITER::OUTPUT_BOTH, 'picture_info', Writer::TYPE_INFO);
            }
            Writer::write('', WRITER::OUTPUT_BOTH, 'picture_info', Writer::TYPE_INFO);

            Writer::write('SECTIONS (' . count($info['sections']) . '):', WRITER::OUTPUT_BOTH, 'picture_info', Writer::TYPE_INFO);
            if(count($info['sections']) > 0) {
                foreach($info['sections'] as $section_idx => $section) {
                    Writer::write('  Section [' . $section_idx . ']:', WRITER::OUTPUT_BOTH, 'picture_info', Writer::TYPE_INFO);
                    Writer::write('    ID: ' . $section['id'] . ' | Name: ' . ($section['section_name'] ?? 'N/A') . ' | File: ' . ($section['file_name'] ?? 'N/A') . ' | Download: ' . ($section['download_successful'] ? 'OK' : 'FAILED'), WRITER::OUTPUT_BOTH, 'picture_info', Writer::TYPE_INFO);
                    Writer::write('    Derivatives (' . count($section['derivatives']) . '):', WRITER::OUTPUT_BOTH, 'picture_info', Writer::TYPE_INFO);
                    if(count($section['derivatives']) > 0) {
                        foreach($section['derivatives'] as $der_idx => $der) {
                            Writer::write('      [' . $der_idx . '] ID: ' . $der['id'] . ' | Name: ' . ($der['name'] ?? 'N/A') . ' | File: ' . ($der['file_name'] ?? 'N/A') . ' | Size: ' . ($der['width'] ?? 'N/A') . 'x' . ($der['height'] ?? 'N/A') . ' | Download: ' . ($der['download_successful'] ? 'OK' : 'FAILED'), WRITER::OUTPUT_BOTH, 'picture_info', Writer::TYPE_INFO);
                        }
                    } else {
                        Writer::write('      No derivatives found', WRITER::OUTPUT_BOTH, 'picture_info', Writer::TYPE_INFO);
                    }
                }
            } else {
                Writer::write('  No sections found', WRITER::OUTPUT_BOTH, 'picture_info', Writer::TYPE_INFO);
            }
            Writer::write('=== END PICTURE INFO ===', WRITER::OUTPUT_BOTH, 'picture_info', Writer::TYPE_INFO);
        }

        return $info;
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
                // download_successful is set by the caller (e.g. ImageProcessorCommand) only after derivatives are created successfully
                return $storage_file;
            } catch (Exception $e) {
                $last_error = $e->getMessage();
                Writer::write('ID ' . $this->getId() . ': Downloading image from ' . $download_url . ' failed at try ' . $retry_counter . '. Error: ' . $last_error, WRITER::OUTPUT_SCREEN, 'image_processor', Writer::TYPE_ERROR);
                return $this->downloadOriginal(false, ($retry_counter + 1), $last_error);
            } catch (S3Exception $e) {
                $last_error = $e->getMessage();
                return $this->downloadOriginal(false, ($retry_counter + 1), $last_error);
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
     * @throws Exception
     */
    public function ensureDerivatives()
    {
        $config = Registry::getInstance()->get('config');
        if(empty($config['image_handling']['processor']['derivatives'])) {
            return;
        }
        $is_section = ($this instanceof Section);
        $id_field = $is_section ? 'id_image_section' : 'id_image';
        foreach ($config['image_handling']['processor']['derivatives'] as $derivative_name => $derivative_config) {
            $extensions = ['jpg'];
            if(!empty($derivative_config['webp_create'])){
                $extensions[] = 'webp';
            }
            foreach($extensions as $extension) {
                $derivative_file_name = pathinfo($this->file_name, PATHINFO_FILENAME) . '_' . $derivative_name . '.' . $extension;
                $existing_derivative = Derivative::listOne([
                    $id_field => $this->getId(),
                    'name' => $derivative_name
                ]);
                if($existing_derivative) {
                    $File = new File(new Bucket($config['image_handling']['storage']));
                    $File->name = $derivative_file_name;
                    if($File->exists()) {
                        if($existing_derivative->download_successful == false) {
                            $existing_derivative->download_successful = true;
                            $existing_derivative->update();
                        }
                    } else {
                        if($existing_derivative->download_successful == true) {
                            $existing_derivative->download_successful = false;
                            $existing_derivative->update();
                        }
                    }
                } else {
                    $File = new File(new Bucket($config['image_handling']['storage']));
                    $File->name = $derivative_file_name;
                    $derivative = new Derivative();
                    if($is_section) {
                        $derivative->id_image_section = $this->getId();
                    } else {
                        $derivative->id_image = $this->getId();
                    }
                    $derivative->id_media_object = $this->id_media_object;
                    $derivative->name = $derivative_name;
                    $derivative->file_name = $derivative_file_name;
                    $derivative->width = $derivative_config['max_width'] ?? null;
                    $derivative->height = $derivative_config['max_height'] ?? null;
                    $derivative->download_successful = $File->exists() ? true : false;
                    try {
                        $derivative->create();
                    } catch (Exception $e) {
                        Writer::write('Failed to create derivative entry: ' . $e->getMessage(), WRITER::OUTPUT_FILE, 'import', Writer::TYPE_WARNING);
                    }
                }
            }
        }
    }

    /**
     * @throws Exception
     */
    public function create()
    {
        parent::create();
        $this->_ensureDerivativesAfterSave();
        return true;
    }

    /**
     * @throws Exception
     */
    public function update()
    {
        parent::update();
        $this->_ensureDerivativesAfterSave();
        return true;
    }

    /**
     * Interne Methode, die nach create() und update() aufgerufen wird
     * @throws Exception
     */
    private function _ensureDerivativesAfterSave()
    {
        try {
            $this->ensureDerivatives();
            if(!empty($this->sections) && is_array($this->sections)) {
                foreach($this->sections as $section) {
                    if($section instanceof Section) {
                        $section->ensureDerivatives();
                    }
                }
            }
        } catch (Exception $e) {
            Writer::write('Failed to ensure derivatives for Picture ID ' . $this->getId() . ': ' . $e->getMessage(), WRITER::OUTPUT_FILE, 'import', Writer::TYPE_WARNING);
        }
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
