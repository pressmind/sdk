<?php
namespace Pressmind\Cache\Adapter;

class Factory {
    /**
     * @param $pAdapterName
     * @return AdapterInterface
     */
    public static function create($pAdapterName) {
        $class_name = '\Pressmind\Cache\Adapter\\' . $pAdapterName;
        $object = new $class_name();
        return $object;
    }
}
