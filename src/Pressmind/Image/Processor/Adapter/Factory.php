<?php


namespace Pressmind\Image\Processor\Adapter;


use Pressmind\Image\Processor\AdapterInterface;

class Factory
{
    /**
     * @param string $adapterName
     * @return AdapterInterface
     */
    public static function create($adapterName)
    {
        $adapterName = 'Pressmind\\Image\\Processor\\Adapter\\' . ucfirst($adapterName);
        $adapter = new $adapterName();
        return $adapter;
    }
}
