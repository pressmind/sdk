<?php


namespace Pressmind\Import;

use Pressmind\Log\Writer;
use Pressmind\ORM\Object\MediaType\Factory;
use Exception;
use Pressmind\HelperFunctions;
use Pressmind\Registry;
use stdClass;

class MediaObjectData extends AbstractImport implements ImportInterface
{
    /**
     * @var array
     */
    private $_var_names_to_be_ignored;

    /**
     * @var stdClass
     */
    private $_data;

    /**
     * @var int
     */
    private $_id_media_object;

    /**
     * @var string
     */
    private $_import_type;

    /**
     * @var bool
     */
    private $_import_linked_objects;

    /**
     * MediaObjectData constructor.
     * @param stdClass $data
     * @param integer $id_media_object
     * @param string $import_type
     * @param boolean $import_linked_objects
     */
    public function __construct($data, $id_media_object, $import_type, $import_linked_objects = true)
    {
        $this->_data = $data;
        $this->_id_media_object = $id_media_object;
        $this->_import_type = $import_type;
        $this->_import_linked_objects = $import_linked_objects;
        $this->_var_names_to_be_ignored = [];
    }

    /**
     * @return array
     * @throws Exception
     */
    public function import() {

        $category_tree_ids = [];
        $conf =  Registry::getInstance()->get('config');
        $default_language = $conf['data']['languages']['default'];
        $allowed_media_objects = array_keys($conf['data']['media_types']);
        $this->_log[] = ' MediaObjectData::import(' . $this->_id_media_object . '): Importing media object data';
        $values = [];
        $linked_media_object_ids = [];
        $languages = [];
        foreach ($this->_data->data as $data_field) {
            if (is_array($data_field->sections)) {
                foreach ($data_field->sections as $section) {
                    $var_name = HelperFunctions::human_to_machine($data_field->var_name);
                    $section->language = empty($section->language) ? $default_language : $section->language;
                    $language = empty($section->language) ? $default_language : $section->language;
                    $language = strtolower($language);
                    if(!in_array($var_name, $this->_var_names_to_be_ignored) && in_array($language, $conf['data']['languages']['allowed'])) {
                        $section_name = $section->name;
                        if(isset($conf['data']['sections']['replace']) && !empty($conf['data']['sections']['replace']['regular_expression'])) {
                            $section_name = preg_replace($conf['data']['sections']['replace']['regular_expression'], $conf['data']['sections']['replace']['replacement'], $section_name);
                        }
                        $column_name = $var_name . '_' . HelperFunctions::human_to_machine($section_name);

                        if (!isset($values[$language])) $values[$language] = [];
                        $section_id = $section->id;
                        $value = null;
                        if($data_field->type == 'categorytree' && isset($data_field->value)) {
                            $value = $data_field->value;
                            if(!empty($value)){
                                $value->id_object_type = $this->_data->id_media_objects_data_type;
                            }
                        } else if($data_field->type == 'key_value') {
                            if(!empty($data_field->value)) {
                                $value = [
                                    'columns' => $data_field->columns,
                                    'values' => $data_field->value->$section_id
                                ];
                            }
                        } else if(isset($data_field->value) && isset($data_field->value->$section_id)) {
                            $value = $data_field->value->$section_id;
                        }
                        $import_linked_objects = true;
                        if(!empty($conf['data']['disable_recursive_import'][$this->_data->id_media_objects_data_type]) && is_array($conf['data']['disable_recursive_import'][$this->_data->id_media_objects_data_type]) && in_array($column_name, $conf['data']['disable_recursive_import'][$this->_data->id_media_objects_data_type])){
                            $this->_log[] = Writer::write('                               MediaObjectData::import(' . $this->_id_media_object . '): object_link import is disabled for field "'.$column_name.'". See config (data.disable_recursive_import). This is not an error, just a msg.', Writer::OUTPUT_SCREEN, 'import', Writer::TYPE_INFO);
                            $import_linked_objects = false;
                        }
                        if($data_field->type == 'objectlink' && !is_null($value) && is_a($value,'stdClass') &&
                            isset($value->objects) && is_array($value->objects) && $this->_import_linked_objects == true) {
                            if(in_array($data_field->id_object_type, $allowed_media_objects)) {
                                if($import_linked_objects == true){
                                    foreach ($value->objects as $linked_media_object_id) {
                                        $linked_media_object_ids[] = $linked_media_object_id;
                                    }
                                }
                            }else{
                                $this->_log[] = Writer::write('                               MediaObjectData::import(' . $this->_id_media_object . '): object_link/s ('.$data_field->var_name.') found, but object_type ('.$data_field->id_object_type.') is not allowed or missing. See config (data.media_types). This is not an error, just a msg.', Writer::OUTPUT_SCREEN, 'import', Writer::TYPE_INFO);
                            }
                        }
                        $values[$language]['language'] = strtolower($language);
                        $values[$language]['id_media_object'] = $this->_id_media_object;
                        $values[$language][$column_name] = $value;
                        $languages[] = strtolower($language);
                    }
                }
            }
            if($data_field->type == 'categorytree' && isset($data_field->value) && isset($data_field->value->id_category)) {
                $category_tree_ids[] = $data_field->value->id_category;
            }
        }
        foreach ($values as $language => $section_data) {
            try {
                $media_object_data = Factory::createById($this->_data->id_media_objects_data_type);
                $media_object_data->fromImport($section_data);
                $media_object_data->create();
                $this->_log[] = ' Importer::_importMediaObjectData(' . $this->_id_media_object . '): Object ' . get_class($media_object_data) . ' created with ID: ' . $this->_id_media_object;
                unset($media_object_data);
            } catch (Exception $e) {
                $this->_log[] = $e->getMessage();
            }
        }
        unset($values);
        $this->_log[] = ' Importer::_importMediaObjectData(' . $this->_id_media_object . '): Heap cleaned up';

        return [
            'linked_media_object_ids' => $linked_media_object_ids,
            'category_tree_ids' => $category_tree_ids,
            'languages' => array_unique($languages)
        ];
    }
}
