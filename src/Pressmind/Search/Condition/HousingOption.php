<?php


namespace Pressmind\Search\Condition;


use Pressmind\HelperFunctions;

class HousingOption implements ConditionInterface
{

    /**
     * @var integer
     */
    private $_sort = 2;

    /**
     * @var array|null
     */
    public $occupancies;

    /**
     * @var array
     */
    public $status;

    /**
     * @var array
     */
    private $_values = [];

    /**
     * HousingOption constructor.
     * @param null $occupancy
     * @param array $status
     */
    public function __construct($occupancyies = null, $status = [HelperFunctions::HOUSING_OPTION_STATUS_ACTIVE, HelperFunctions::HOUSING_OPTION_STATUS_LOW, HelperFunctions::HOUSING_OPTION_STATUS_REQUEST]) {
        if(!is_array($occupancyies) && !is_null($occupancyies)) {
            $occupancyies = [intval($occupancyies)];
        }
        $this->occupancies = $occupancyies;
        if(!is_array($status)) {
            $status = [intval($status)];
        }
        $this->status = $status;
    }

    /**
     * @return string
     */
    public function getSQL()
    {
        $conditions = [];
        if(is_array($this->occupancies)) {
            foreach ($this->occupancies as $index => $occupancy) {
                $occupancy_conditions[] = '(:occupancy' . $index . ' BETWEEN pmt2core_cheapest_price_speed.option_occupancy_min AND pmt2core_cheapest_price_speed.option_occupancy_max OR :occupancy' . $index . ' = pmt2core_cheapest_price_speed.option_occupancy)';
                $this->_values[':occupancy' . $index] = $occupancy;
            }
            $conditions[] = '(' . implode(') AND (' , $occupancy_conditions) . ')';
        }
        if(!empty($this->status)) {
            $conditions[] = 'pmt2core_cheapest_price_speed.state IN (:status)';
            $this->_values[':status'] = implode(',', $this->status);
        }
        return implode(' AND ', $conditions);
    }

    /**
     * @return array
     */
    public function getValues()
    {
        return $this->_values;
    }

    /**
     * @return int
     */
    public function getSort()
    {
        return $this->_sort;
    }

    /**
     * @return string
     */
    public function getJoins()
    {
        return 'INNER JOIN (SELECT pmt2core_cheapest_price_speed.id_media_object, MIN(pmt2core_cheapest_price_speed.price_total) as cheapest_price_total
                     FROM pmt2core_cheapest_price_speed
                     WHERE ###CONDITIONS### GROUP BY pmt2core_cheapest_price_speed.id_media_object) cheapest_price_speed on pmt2core_media_objects.id = cheapest_price_speed.id_media_object';
    }

    /**
     * @return string|null
     */
    public function getJoinType()
    {
        return 'SUBSELECT';
    }

    /**
     * @return string|null
     */
    public function getSubselectJoinTable()
    {
        return 'pmt2core_cheapest_price_speed';
    }

    /**
     * @return null
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
        $this->occupancies = isset($config->occupancies) ? $config->occupancies: null;
        $this->status = isset($config->status) ? $config->status : null;
        if(!is_array($this->status)) {
            $this->status = [intval($this->status)];
        }
    }

    /**
     * @return array
     */
    public function getConfig() {
        return [
            'occupancies' => $this->occupancies,
            'status' => $this->status
        ];
    }

    /**
     * @param null $occupancy
     * @param array $status
     * @return HousingOption
     */
    public static function create($occupancies = null, $status = [HelperFunctions::HOUSING_OPTION_STATUS_ACTIVE, HelperFunctions::HOUSING_OPTION_STATUS_LOW, HelperFunctions::HOUSING_OPTION_STATUS_REQUEST])
    {
        return new self($occupancies, $status);
    }

    /**
     * @return array
     */
    public function toJson() {
        return [
            'type' => (new \ReflectionClass($this))->getShortName(),
            'config' => $this->getConfig()
        ];
    }
}
