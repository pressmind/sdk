<?php


namespace Pressmind\Search\Condition;


use ReflectionClass;
use ReflectionException;

class State implements ConditionInterface
{
    /**
     * @var array
     */
    private $_states = [];

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
     * @param array $pStates
     */
    public function __construct($pStates = [])
    {
        $this->_states = $pStates;
    }

    /**
     * @return string
     */
    public function getSQL()
    {
        $state_strings = [];
        $term_counter = 0;
        foreach ($this->_states as $state) {
            $term_counter++;
            $state_strings[] = 'pmt2core_media_objects.state = :state' . $term_counter;
            $this->_values[':state' . $term_counter] = $state;
        }
        $sql = "(" . implode(' OR ', $state_strings) . ")";
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
     * @param $pStates
     * @return State
     */
    public static function create($pStates)
    {
        $object = new self($pStates);
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
        $this->_states = $config->states;
    }

    /**
     * @return array
     */
    public function getConfig() {
        return [
            'states' => $this->_states,
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
