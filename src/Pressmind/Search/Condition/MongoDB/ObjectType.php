<?php

namespace Pressmind\Search\Condition\MongoDB;

class ObjectType
{
    /**
     * @var integer
     */
    private $_id_object_type;

    /**
     * @param integer $idObjectType
     */
    public function __construct($idObjectType)
    {
        $this->_id_object_type = $idObjectType;
    }

    /**
     * @return string
     */
    public function getType(){
        return (new \ReflectionClass($this))->getShortName();
    }

    public function getQuery($type = 'first_match')
    {
        if($type == 'first_match') {
            return ['id_object_type' => $this->_id_object_type];
        }
        return null;
    }
}
