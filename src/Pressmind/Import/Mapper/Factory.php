<?php
namespace Pressmind\Import\Mapper;


class Factory {
    /**
     * @param $pMediaTypeName
     * @return MapperInterface|boolean
     */
    public static function create($pMapperName) {
        if(file_exists(__DIR__ . DIRECTORY_SEPARATOR . $pMapperName . '.php')) {
            $class_name = '\Pressmind\Import\Mapper\\' . $pMapperName;
            $object = new $class_name();
            return $object;
        }
        return false;
    }
}
