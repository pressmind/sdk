<?php

namespace Pressmind\Search\Condition\MongoDB;

class ObjectType
{
    /**
     * @var mixed  integer, integer[]
     */
    private $_id_object_types;

    /**
     * @param integer $idObjectType
     */
    public function __construct($idObjectTypes)
    {
        if(is_array($idObjectTypes) && count($idObjectTypes) == 1){
            $this->_id_object_types = $idObjectTypes[0]; // prevents $in search if not necessary
        }
        $this->_id_object_types = $idObjectTypes;
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
            if(is_array($this->_id_object_types)){
                return ['id_object_type' => ['$in' => $this->_id_object_types]];
            }else{
                return ['id_object_type' => $this->_id_object_types];
            }
        }
        return null;
    }
}
