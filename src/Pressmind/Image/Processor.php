<?php


namespace Pressmind\Image;


use Pressmind\Image\Processor\AdapterInterface;

class Processor
{
    private $_log = [];

    /**
     * @var AdapterInterface
     */
    private $_adapter;

    public function __construct()
    {

    }

    /**
     * @return array
     */
    public function getLog()
    {
        return $this->_log;
    }
}
