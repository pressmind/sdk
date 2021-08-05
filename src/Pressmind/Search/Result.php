<?php


namespace Pressmind\Search;


use Pressmind\ORM\Object\MediaObject;

class Result
{
    /**
     * @var string
     */
    private $_query;

    /**
     * @var array
     */
    private $_values;

    /**
     * @var array
     */
    private $_result_raw;

    /**
     * @var MediaObject[]
     */
    private $_result = [];

    /**
     * @var boolean|String
     */
    private $_isCached;

    public function __construct($isCached = false)
    {
        $this->_isCached = $isCached;
    }

    /**
     * @return MediaObject[]
     * @throws \Exception
     */
    public function getResult($loadfull = false) {
        if(empty($this->_result)) {
            foreach ($this->_result_raw as $result_item) {
                $this->_result[] = $result_item;
            }
        }
        return $this->_result;
    }

    /**
     * @param array $result_raw
     */
    public function setResultRaw($result_raw)
    {
        $this->_result_raw = $result_raw;
    }

    /**
     * @param string $query
     */
    public function setQuery($query)
    {
        $this->_query = $query;
    }

    /**
     * @param array $values
     */
    public function setValues($values)
    {
        $this->_values = $values;
    }

    /**
     * @return string
     */
    public function getQuery()
    {
        return $this->_query;
    }

    /**
     * @return array
     */
    public function getValues()
    {
        return $this->_values;
    }

    /**
     * @return bool|String
     */
    public function isCached()
    {
        return $this->_isCached;
    }
}
