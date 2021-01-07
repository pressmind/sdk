<?php


namespace Pressmind\Search\Condition;


use ReflectionClass;
use ReflectionException;

class Pool implements ConditionInterface
{
    /**
     * @var array
     */
    private $_pools = [];

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
     * @param array $pPools
     */
    public function __construct($pPools = [])
    {
        $this->_pools = $pPools;
    }

    /**
     * @return string
     */
    public function getSQL()
    {
        $pool_strings = [];
        $term_counter = 0;
        foreach ($this->_pools as $pool) {
            $term_counter++;
            $pool_strings[] = 'pmt2core_media_objects.id_pool = :pool' . $term_counter;
            $this->_values[':pool' . $term_counter] = $pool;
        }
        $sql = "(" . implode(' OR ', $pool_strings) . ")";
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
     * @param $pPools
     * @return Pool
     */
    public static function create($pPools)
    {
        $object = new self($pPools);
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
        $this->_pools = $config->pools;
    }

    /**
     * @return array
     */
    public function getConfig() {
        return [
            'pools' => $this->_pools,
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
