<?php

namespace Pressmind\Search\Condition\MongoDB;

class Powerfilter
{
    /**
     * @var integer
     */
    private $_id_filter;

    /**
     * @param int $idMediaObject
     */
    public function __construct($idFilter)
    {
        $this->_id_filter = (int)$idFilter;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return (new \ReflectionClass($this))->getShortName();
    }

    /**
     * @return int
     */
    public function getValue()
    {
        return $this->_id_filter;
    }

    public function getQuery($type = 'first_match')
    {
        if ($type == 'lookup') {
            return [
                '$lookup' => [
                    'from' => 'powerfilter',
                    'localField' => '_id',
                    'foreignField' => 'id_media_objects',
                    'as' => 'matchedPowerfilter'
                ]
            ];
        }
        if ($type == 'match') {
            return [
                '$match' => [
                    'matchedPowerfilter._id' => $this->_id_filter
                ]
            ];
        }
    }
}
