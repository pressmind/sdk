<?php


namespace Pressmind\Search\Condition;


use Pressmind\HelperFunctions;

class HousingOption implements ConditionInterface
{

    /**
     * @var integer
     */
    private $_sort = 6;

    /**
     * @var integer|null
     */
    public $occupancy;

    /**
     * @var array
     */
    public $status;

    /**
     * HousingOption constructor.
     * @param null $occupancy
     * @param array $status
     */
    public function __construct($occupancy = null, $status = [HelperFunctions::HOUSING_OPTION_STATUS_ACTIVE, HelperFunctions::HOUSING_OPTION_STATUS_LOW, HelperFunctions::HOUSING_OPTION_STATUS_REQUEST]) {
        $this->occupancy = $occupancy;
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
        if(!is_null($this->occupancy)) {
            $conditions[] = '(:occupancy BETWEEN pmt2core_cheapest_price_speed.option_occupancy_min AND pmt2core_cheapest_price_speed.option_occupancy_max OR :occupancy = pmt2core_cheapest_price_speed.option_occupancy)';
        }
        if(!empty($this->status)) {
            $conditions[] = 'pmt2core_cheapest_price_speed.state IN (:status)';
        }
        return implode(' AND ', $conditions);
    }

    /**
     * @return array
     */
    public function getValues()
    {
        $values = [];
        if(!is_null($this->occupancy)) {
            $values[':occupancy'] = $this->occupancy;
        }
        if(!empty($this->status)) {
            $values[':status'] = implode(',', $this->status);
        }
        return $values;
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
        return 'INNER JOIN pmt2core_cheapest_price_speed on pmt2core_media_objects.id = pmt2core_cheapest_price_speed.id_media_object';
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
        $this->occupancy = isset($config->occupancy) ? $config->occupancy: null;
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
            'occupancy' => $this->occupancy,
            'status' => $this->status
        ];
    }

    /**
     * @param null $occupancy
     * @param array $status
     * @return HousingOption
     */
    public static function create($occupancy = null, $status = [HelperFunctions::HOUSING_OPTION_STATUS_ACTIVE, HelperFunctions::HOUSING_OPTION_STATUS_LOW, HelperFunctions::HOUSING_OPTION_STATUS_REQUEST])
    {
        return new self($occupancy, $status);
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
