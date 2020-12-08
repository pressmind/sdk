<?php


namespace Pressmind\Import\Mapper;


use stdClass;

class Location implements MapperInterface
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
        if(is_array($pObject)) {
            foreach ($pObject as $object) {
                $mapped_object = new stdClass();
                $mapped_object->id_media_object = $pIdMediaObject;
                $mapped_object->section_name = '';
                $mapped_object->language = $pLanguage;
                $mapped_object->var_name = $pVarName;
                $mapped_object->lat = isset($object->lat) ? $object->lat : null;
                $mapped_object->lng = isset($object->lat) ? $object->lng : null;
                $mapped_object->address = $object->address;
                $mapped_object->title = $object->title;
                $result[] = $mapped_object;
            }
        }
        return $result;
    }
}
