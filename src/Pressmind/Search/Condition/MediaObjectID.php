<?php


namespace Pressmind\Search\Condition;


use ReflectionClass;
use ReflectionException;

class MediaObjectID implements ConditionInterface
{
    /**
     * @var array
     */
    private $_ids = [];

    /**
     * @var array
     */
    private $_values = [];

    /**
     * @var int
     */
    private $_sort = 6;

    /**
     * Category constructor.
     * @param $pIds
     */
    public function __construct($pIds = [])
    {
        $this->_ids = $pIds;
    }

    /**
     * @return string
     */
    public function getSQL()
    {
        $id_strings = [];
        $term_counter = 0;
        foreach ($this->_ids as $id) {
            $term_counter++;
            $id_strings[] = ':media_object_id' . $term_counter;
            $this->_values[':media_object_id' . $term_counter] = $id;
        }
        $sql = "pmt2core_media_objects.id IN (" . implode(',', $id_strings) . ")";
        return $sql;
    }

    /**
     * @return int
     */
    public function getSort()
    {
        return $this->_sort;
    }

    /**
     * @return array
     */
    public function getValues()
    {
        return $this->_values;
    }

    /**
     * @param $pIds
     * @return MediaObjectID
     */
    public static function create($pIds)
    {
        $object = new self($pIds);
        return $object;
    }

    /**
     * @return string|null
     */
    public function getJoins()
    {
        return null;
    }

    /**
     * @return string|null
     */
    public function getAdditionalFields()
    {
        return null;
    }

    /**
     * @param \stdClass $config
     */
    public function setConfig($config)
    {
        $this->_ids = $config->ids;
    }

    /**
     * @return array
     */
    public function getConfig() {
        return [
            'ids' => $this->_ids,
        ];
    }

    /**
     * @return array
     * @throws ReflectionException
     */
    public function toJson() {
        $data = [
            'type' => (new ReflectionClass($this))->getShortName(),
            'config' => $this->getConfig()
        ];
        return $data;
    }
}
