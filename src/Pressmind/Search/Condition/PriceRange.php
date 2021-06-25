<?php


namespace Pressmind\Search\Condition;

class PriceRange implements ConditionInterface
{
    /**
     * @var integer
     */
    private $_sort = 2;

    /**
     * @var integer
     */
    public $priceFrom;

    /**
     * @var integer
     */
    public $priceTo;


    public function __construct($priceFrom = null, $priceTo = null)
    {
        $this->priceFrom = $priceFrom;
        $this->priceTo = $priceTo;
    }

    public function getSQL()
    {
        return 'pmt2core_cheapest_price_speed.price_total BETWEEN :price_from AND :price_to AND pmt2core_cheapest_price_speed.price_total > 0 AND pmt2core_cheapest_price_speed.date_departure > :now';
    }

    public function getSort()
    {
        return $this->_sort;
    }

    public function getJoins()
    {
        return 'INNER JOIN (SELECT pmt2core_cheapest_price_speed.id_media_object, MIN(pmt2core_cheapest_price_speed.price_total) as cheapest_price_total, pmt2core_cheapest_price_speed.date_departure
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

    public function getAdditionalFields()
    {
        return null;
    }

    public function getValues()
    {
        $now = new \DateTime();
        return [':price_from' => $this->priceFrom, ':price_to' => $this->priceTo, ':now' => $now->format('Y-m-d 00:00:00')];
    }

    public static function create($priceFrom, $priceTo)
    {
        $object = new self($priceFrom, $priceTo);
        return $object;
    }

    /**
     * @param \stdClass $config
     */
    public function setConfig($config) {
        $this->priceFrom = $config->priceFrom;
        $this->priceTo = $config->priceTo;
    }

    /**
     * @return array
     */
    public function getConfig() {
        return [
            'priceFrom' => $this->priceFrom,
            'priceTo' => $this->priceTo
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
