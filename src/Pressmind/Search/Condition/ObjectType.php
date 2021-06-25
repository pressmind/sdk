<?php


namespace Pressmind\Search\Condition;


use Pressmind\Registry;

class ObjectType implements ConditionInterface
{
    /**
     * @var array
     */
    private $_values = [];

    /**
     * @var integer
     */
    private $_sort = 4;

    /**
     * @var integer
     */
    private $_object_type_id;

    public function __construct($pObjectTypeId = null)
    {
        $this->_object_type_id = $pObjectTypeId;
    }

    public function getSQL()
    {
        if(is_array($this->_object_type_id)) {
            $placeholder_names = [];
            foreach ($this->_object_type_id as $key => $value) {
                $placeholder_names[] = ':object_type_id' . $key;
            }
            $sql = "pmt2core_media_objects.id_object_type IN (" . implode(', ', $placeholder_names) . ")";
        } else {
            $sql = "pmt2core_media_objects.id_object_type = :object_type_id";
        }
        return $sql;
    }

    public function getSort()
    {
        return $this->_sort;
    }

    public function getValues()
    {
        if(is_array($this->_object_type_id)) {
            $values = [];
            foreach ($this->_object_type_id as $key => $value) {
                $values[':object_type_id' . $key] = $value;
            }
            return $values;
        }
        return [':object_type_id' => $this->_object_type_id];
    }

    public function getJoins()
    {
        return null;//'INNER JOIN pmt2core_media_object_tree_items on pmt2core_media_objects.id = pmt2core_media_object_tree_items.id_media_object';
    }

    /**
     * @return string|null
     */
    public function getJoinType()
    {
        return null;
    }

    /**
     * @return string|null
     */
    public function getSubselectJoinTable()
    {
        return null;
    }

    public function getAdditionalFields()
    {
        return null;
    }

    /**
     * @param string[]|int[]|string|int $mediaObjectType
     * @return ObjectType
     */
    public static function create($mediaObjectType)
    {
        if(is_array($mediaObjectType)) {
            $old = $mediaObjectType;
            $mediaObjectType = [];
            foreach ($old as $media_object_type) {
                if(is_string($media_object_type)) {
                    $conf = Registry::getInstance()->get('config');
                    foreach ($conf['data']['media_types'] as $media_object_type_id => $media_object_type_name) {
                        if($media_object_type == $media_object_type_name) {
                            $mediaObjectType[] = $media_object_type_id;
                        }
                    }
                }
            }
        }
        if(is_string($mediaObjectType)) {
            $conf = Registry::getInstance()->get('config');
            foreach ($conf['data']['media_types'] as $media_object_type_id => $media_object_type_name) {
                if($mediaObjectType == $media_object_type_name) {
                    $mediaObjectType = $media_object_type_id;
                }
            }
        }
        $object = new self($mediaObjectType);
        return $object;
    }

    public function setConfig($config)
    {
        $this->_object_type_id = $config->object_type_id;
    }
}
