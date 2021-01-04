<?php
namespace Pressmind\ORM\Object\MediaType;

use Pressmind\HelperFunctions;
use Pressmind\Registry;

class Factory {
    /**
     * @param $pMediaTypeName
     * @return AbstractMediaType
     */
    public static function create($pMediaTypeName, $pReadRelations = false) {
        $class_name = 'Custom\MediaType\\' . $pMediaTypeName;
        $object = new $class_name(null, $pReadRelations);
        return $object;
    }

    /**
     * @param $pMediaTypeId
     * @return AbstractMediaType
     */
    public static function createById($pMediaTypeId, $pReadRelations = false) {
        $config = Registry::getInstance()->get('config');
        $media_type_name = ucfirst(HelperFunctions::human_to_machine($config['data']['media_types'][$pMediaTypeId]));
        return self::create($media_type_name, $pReadRelations);
    }

    public function readFromCache($pMediaTypeId, $data) {
        $config = Registry::getInstance()->get('config');
        $media_type_name = ucfirst(HelperFunctions::human_to_machine($config['data']['media_types'][$pMediaTypeId]));
        $class_name = 'Custom\MediaType\\' . $media_type_name;
        $object = new $class_name(null);
        $object->fromCache($data);
        //print_r($object->toStdClass());
        return $object;
    }
}
