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
    public function map($pIdMediaObject,$pLanguage, $pVarName, $pObject)
    {
        $result = [];
        $keys = [];
        if(isset($pObject->items) && is_object($pObject->items)) {
            foreach ($pObject->items as $key => $items) {
                foreach ($items as $sub_item) {
                    foreach ($sub_item as $item) {
                        if (!in_array($item->id, $keys)) {
                            $mapped_object = new stdClass();
                            $mapped_object->id_media_object = $pIdMediaObject;
                            $mapped_object->section_name = '';
                            $mapped_object->language = $pLanguage;
                            $mapped_object->var_name = $pVarName;
                            $mapped_object->id_tree = $pObject->id_category;
                            $mapped_object->id_item = $item->id;
                            $keys[] = $item->id;
                            $result[] = $mapped_object;
                        }
                    }
                }
            }
        }
        return $result;
    }
}
