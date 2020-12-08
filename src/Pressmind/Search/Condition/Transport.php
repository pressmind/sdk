<?php


namespace Pressmind\Search\Condition;


class Transport implements ConditionInterface
{

    /**
     * @var array
     */
    private $_transport_types = [];

    /**
     * @var array
     */
    private $_values = [];

    /**
     * @var int
     */
    private $_sort = 7;

    /**
     * Transport constructor.
     * @param $pItemIds
     */
    public function __construct($pTransportTypes = null)
    {
        $this->_transport_types = $pTransportTypes;
    }

    public function getSQL()
    {
        $transport_type_strings = [];
        $term_counter = 0;
        foreach ($this->_transport_types as $transport_type) {
            $term_counter++;
            $transport_type_strings[] = 'pmt2core_cheapest_price_speed.transport_type = :transport'. $term_counter;
            $this->_values[':transport' . $term_counter] = $transport_type;
        }
        $sql = implode(' OR ', $transport_type_strings);
        return $sql;
    }

    public function getSort()
    {
        return $this->_sort;
    }

    public function getValues()
    {
        return $this->_values;
    }

    public function getJoins()
    {
        return 'INNER JOIN pmt2core_cheapest_price_speed on pmt2core_media_objects.id = pmt2core_cheapest_price_speed.id_media_object';
    }

    public function getAdditionalFields()
    {
        return null;
    }

    public static function create($pTransportTypes)
    {
        $object = new self($pTransportTypes);
        return $object;
    }

    /**
     * @param \stdClass $config
     */
    public function setConfig($config) {
        $this->_transport_types = $config->transport_types;
    }

    public function getConfig() {
        return [
            'transport_types' => $this->_transport_types
        ];
    }

    public function toJson() {
        $data = [
            'type' => get_class($this),
            'config' => $this->getConfig()
        ];
        return $data;
    }
}
