<?php


namespace Pressmind\Storage\Provider;

use Pressmind\Storage\File;
use Pressmind\Storage\ProviderInterface;

class Factory
{
    /**
     * @param $providerName
     * @param File $file
     * @return ProviderInterface
     */
    public static function create($providerName) {
        $class_name = 'Pressmind\Storage\Provider\\' . ucfirst($providerName);
        $provider = new $class_name();
        return $provider;
    }
}
