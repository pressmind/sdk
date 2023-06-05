<?php


namespace Pressmind\Import\Mapper;


use stdClass;

class Categorytree implements MapperInterface
{
    /**
     * @param integer $pIdMediaObject
     * @param string $pLanguage
     * @param string $pVarName
     * @param stdClass $pObject
     * @return array
     */
    public function map($pIdMediaObject, $pLanguage, $pVarName, $pObject)
    {
        $result = [];
        $keys = [];
        if(isset($pObject->items) && is_object($pObject->items)) {
            foreach ($pObject->items as $items) {
                foreach ($items as $sub_items) {
                    $level = 1;
                    $level_count = count($sub_items);
                    foreach ($sub_items as $item) {
                        if (in_array($item->id, $keys)) {
                            $level_count -= 1;
                            continue;
                        }
                        $mapped_object = new stdClass();
                        $mapped_object->id_media_object = $pIdMediaObject;
                        $mapped_object->section_name = '';
                        $mapped_object->language = $pLanguage;
                        $mapped_object->var_name = $pVarName;
                        $mapped_object->id_tree = $pObject->id_category;
                        $mapped_object->id_item = $item->id;
                        $mapped_object->is_tail = $level === $level_count;
                        $mapped_object->id_object_type = $pObject->id_object_type;
                        $keys[] = $item->id;
                        $result[] = $mapped_object;
                        $level++;
                    }
                }
            }
        }
        return $result;
    }
}
