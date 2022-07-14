<?php


namespace Pressmind\Storage\Provider;

use Pressmind\Storage\File;
use Pressmind\Storage\ProviderInterface;

class Factory
{
    /**
     * @param array $storage
     * @return ProviderInterface
     */
    public static function create($storage) {
        $class_name = 'Pressmind\Storage\Provider\\' . ucfirst($storage['provider']);
        $provider = new $class_name($storage);
        return $provider;
    }
}
