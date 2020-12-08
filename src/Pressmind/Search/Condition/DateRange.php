<?php


namespace Pressmind\Search\Condition;

use DateTime;
use stdClass;

/**
 * Class DateRange
 * @package Search
 */
class DateRange implements ConditionInterface
{
    /**
     * @var integer
     */
    private $_sort = 2;

    /**
     * @var DateTime
     */
    public $dateFrom;

    /**
     * @var DateTime
     */
    public $dateTo;

    /**
     * DateRange constructor.
     * @param null|DateTime $pDateFrom
     * @param null|DateTime $pDateTo
     */
    public function __construct($pDateFrom = null, $pDateTo = null)
    {
        $this->dateFrom = $pDateFrom;
        $this->dateTo = $pDateTo;
    }

    /**
     * @return string
     */
    public function getSQL()
    {
        return 'pmt2core_cheapest_price_speed.date_departure BETWEEN :date_from AND :date_to';
    }

    /**
     * @return int
     */
    public function getSort()
    {
        return $this->_sort;
    }

    /**
     * @return string|null
     */
    public function getJoins()
    {
        return 'INNER JOIN pmt2core_cheapest_price_speed on pmt2core_media_objects.id = pmt2core_cheapest_price_speed.id_media_object';
    }

    /**
     * @return string|null
     */
    public function getAdditionalFields()
    {
        return null;
    }

    /**
     * @return array
     */
    public function getValues()
    {
        return [':date_from' => $this->dateFrom->format('Y-m-d 00:00:00'), ':date_to' => $this->dateTo->format('Y-m-d 00:00:00')];
    }

    /**
     * @param null $pDateFrom
     * @param null $pDateTo
     * @return DateRange
     */
    public static function create($pDateFrom = null, $pDateTo = null)
    {
        $object = new self($pDateFrom, $pDateTo);
        return $object;
    }

    /**
     * @param stdClass $config
     */
    public function setConfig($config) {
        $this->dateFrom = DateTime::createFromFormat('Y-m-d H:i:s', $config->dateFrom);
        $this->dateTo = DateTime::createFromFormat('Y-m-d H:i:s', $config->dateTo);
    }

    /**
     * @return array
     */
    public function getConfig() {
        return [
            'dateFrom' => $this->dateFrom->format('Y-m-d 00:00:00'),
            'dateTo' => $this->dateTo->format('Y-m-d 00:00:00')
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
