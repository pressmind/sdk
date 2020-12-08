<?php


namespace Pressmind\Image\Processor;


use Pressmind\Storage\File;

interface AdapterInterface
{
    /**
     * @param Config $config
     * @param File $file
     * @param string $derivativeName
     * @return File
     */
    public function process($config, $file, $derivativeName);
}
