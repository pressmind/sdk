<?php


namespace Pressmind\Search\Condition;

class DurationRange implements ConditionInterface
{
    /**
     * @var integer
     */
    private $_sort = 2;

    /**
     * @var integer
     */
    public $durationFrom;

    /**
     * @var integer
     */
    public $durationTo;


    public function __construct($durationFrom = null, $durationTo = null)
    {
        $this->durationFrom = $durationFrom;
        $this->durationTo = $durationTo;
    }

    public function getSQL()
    {
        return 'pmt2core_cheapest_price_speed.duration BETWEEN :duration_from AND :duration_to';
    }

    public function getSort()
    {
        return $this->_sort;
    }

    public function getJoins()
    {
        return 'INNER JOIN (SELECT pmt2core_cheapest_price_speed.id_media_object, MIN(pmt2core_cheapest_price_speed.price_total) as cheapest_price_total
                     FROM pmt2core_cheapest_price_speed
                     WHERE ###CONDITIONS### GROUP BY pmt2core_cheapest_price_speed.id_media_object) cheapest_price_speed on pmt2core_media_objects.id = cheapest_price_speed.id_media_object';
    }

    public function getAdditionalFields()
    {
        return null;
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

    public function getValues()
    {
        return [':duration_from' => $this->durationFrom, ':duration_to' => $this->durationTo];
    }

    public static function create($pDurationFrom = null, $pDurationTo = null)
    {
        $object = new self($pDurationFrom, $pDurationTo);
        return $object;
    }

    public function setConfig($config)
    {
        $this->durationFrom = $config->durationFrom;
        $this->durationTo = $config->durationTo;
    }

    /**
     * @return array
     */
    public function getConfig() {
        return [
            'durationFrom' => $this->durationFrom,
            'durationTo' => $this->durationTo
        ];
    }

    /**
     * @return array
     * @throws \ReflectionException
     */
    public function toJson() {
        $data = [
            'type' => (new \ReflectionClass($this))->getShortName(),
            'config' => $this->getConfig()
        ];
        return $data;
    }
}
