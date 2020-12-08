<?php


namespace Pressmind\Search;


use Pressmind\ORM\Object\AbstractObject;
use Pressmind\ORM\Object\MediaObject;

class AbstractTemplate
{
    protected $_object;

    /**
     * Reise_SearchTeaser constructor.
     * @param AbstractObject $object
     */
    public function __construct($object)
    {
        $this->_object = $object;
    }
}
