<?php


namespace Pressmind\Search\Condition;


use DateTime;
use ReflectionClass;

class Validity implements ConditionInterface
{

    /**
     * @var DateTime
     */
    public $validFrom;

    /**
     * @var DateTime
     */
    public $validTo;

    /**
     * @var int
     */
    private $_sort = 6;

    public function __construct($validFrom = null, $validTo = null)
    {
        $this->validFrom = $validFrom;
        $this->validTo = $validTo;
    }

    public function getSQL(): string
    {
        return "((pmt2core_media_objects.valid_from IS NULL OR pmt2core_media_objects.valid_from <= :valid_from) AND (pmt2core_media_objects.valid_to IS NULL OR pmt2core_media_objects.valid_to >= :valid_to))";
    }

    public function getValues(): array
    {
        return ['valid_from' => $this->validFrom->format('Y-m-d H:i:s'), 'valid_to' => $this->validTo->format('Y-m-d H:i:s')];
    }

    public function getSort(): int
    {
        return $this->_sort;
    }

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

    public function getAdditionalFields()
    {
        return null;
    }

    public function setConfig($config)
    {
        $this->validFrom = DateTime::createFromFormat('Y-m-d H:i:s', $config->validFrom);
        $this->validTo = DateTime::createFromFormat('Y-m-d H:i:s', $config->validTo);
    }

    /**
     * @return array
     */
    public function getConfig(): array {
        return [
            'validFrom' => $this->validFrom->format('Y-m-d 00:00:00'),
            'validTo' => $this->validTo->format('Y-m-d 00:00:00')
        ];
    }

    /**
     * @return array
     */
    public function toJson(): array
    {
        return [
            'type' => (new ReflectionClass($this))->getShortName(),
            'config' => $this->getConfig()
        ];
    }

    /**
     * @param null|DateTime $validFrom
     * @param null|DateTime $validTo
     * @return Validity
     */
    public static function create($validFrom = null, $validTo = null): Validity
    {
        return new self($validFrom, $validTo);
    }
}
