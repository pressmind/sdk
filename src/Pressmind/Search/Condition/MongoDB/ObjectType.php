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

    public function getQuery($type = '$match')
    {
        if($type == '$match') {
            return [
                'id_object_type' => $this->_id_object_type
            ];
        }
        return null;
    }
}
