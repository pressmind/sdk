<?php


namespace Pressmind\Search\Condition;


use ReflectionClass;
use ReflectionException;

class Brand implements ConditionInterface
{
    /**
     * @var array
     */
    private $_brands = [];

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
     * @param array $pBrands
     */
    public function __construct($pBrands = [])
    {
        $this->_brands = $pBrands;
    }

    /**
     * @return string
     */
    public function getSQL()
    {
        $brand_strings = [];
        $term_counter = 0;
        foreach ($this->_brands as $brand) {
            $term_counter++;
            $brand_strings[] = 'pmt2core_media_objects.id_brand = :brand' . $term_counter;
            $this->_values[':brand' . $term_counter] = $brand;
        }
        $sql = "(" . implode(' OR ', $brand_strings) . ")";
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
     * @param $pBrands
     * @return Brand
     */
    public static function create($pBrands)
    {
        $object = new self($pBrands);
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
        $this->_brands = $config->brands;
    }

    /**
     * @return array
     */
    public function getConfig() {
        return [
            'brands' => $this->_brands,
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
