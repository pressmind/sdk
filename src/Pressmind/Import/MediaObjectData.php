<?php


namespace Pressmind\Import;


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
        $this->_log[] = ' MediaObjectData::import(' . $this->_id_media_object . '): Importing media object data';
        $values = [];
        $linked_media_object_ids = [];
        foreach ($this->_data->data as $data_field) {
            if (is_array($data_field->sections)) {
                foreach ($data_field->sections as $section) {
                    $var_name = HelperFunctions::human_to_machine($data_field->var_name);
                    if(!in_array($var_name, $this->_var_names_to_be_ignored)) {
                        $section_name = $section->name;
                        if(isset($conf['data']['sections']['replace']) && !empty($conf['data']['sections']['replace']['regular_expression'])) {
                            $section_name = preg_replace($conf['data']['sections']['replace']['regular_expression'], $conf['data']['sections']['replace']['replacement'], $section_name);
                        }
                        $column_name = $var_name . '_' . HelperFunctions::human_to_machine($section_name);
                        $section->language = empty($section->language) ? $default_language : $section->language;
                        $language = empty($section->language) ? $default_language : $section->language;
                        $language = strtolower($language);
                        if (!isset($values[$language])) $values[$language] = [];
                        $section_id = $section->id;
                        $value = null;
                        if($data_field->type == 'categorytree' && isset($data_field->value)) {
                            $value = $data_field->value;
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
                        if($data_field->type == 'objectlink' && $this->_import_type == 'mediaobject' && !is_null($value) && is_a($value,'stdClass') && isset($value->objects) && is_array($value->objects) && $this->_import_linked_objects == true) {
                            foreach ($value->objects as $linked_media_object_id) {
                                $linked_media_object_ids[] = $linked_media_object_id;
                            }
                        }
                        $values[$language]['language'] = strtolower($language);
                        $values[$language]['id_media_object'] = $this->_id_media_object;
                        $values[$language][$column_name] = $value;
                    }
                }
            }
            if($data_field->type == 'categorytree' && isset($data_field->value) && isset($data_field->value->id_category)) {
                $category_tree_ids[] = $data_field->value->id_category;
            }
        }
        foreach ($values as $language => $section_data) {
            try {
                $media_object = Factory::createById($this->_data->id_media_objects_data_type);
                $media_object->fromImport($section_data);
                $media_object->create();
                $this->_log[] = ' Importer::_importMediaObjectData(' . $this->_id_media_object . '): Object ' . get_class($media_object) . ' created with ID: ' . $media_object->getId();
                unset($media_object);
                unset($old_object);
            } catch (Exception $e) {
                $this->_log[] = $e->getMessage();
            }
        }
        unset($values);
        $this->_log[] = ' Importer::_importMediaObjectData(' . $this->_id_media_object . '): Heap cleaned up';

        return [
            'linked_media_object_ids' => $linked_media_object_ids,
            'category_tree_ids' => $category_tree_ids
        ];
    }
}
