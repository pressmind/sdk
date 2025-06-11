<?php

namespace Pressmind\Search\Condition\MongoDB;

class Fulltext
{
    /**
     * @var string
     */
    private $_searchString;

    /**
     * @param string $searchString
     */
    public function __construct($searchString)
    {
        $this->_searchString = trim(str_replace(["\\", '"'], ['', ''], $searchString));
    }

    /**
     * @return string
     */
    public function getType()
    {
        return (new \ReflectionClass($this))->getShortName();
    }

    public function getQuery($type = 'first_match')
    {
        if ($type == 'first_match') {
            return [
                'fulltext' => [
                        '$regex' => '\\b'.$this->_searchString,
                        '$options' => 'i'
                ]
            ];
        } elseif ($type == 'project') {
            return ['$project' => [
                    'best_price_meta' => 1,
                    'categories' => 1,
                    'code' => 1,
                    'dates_per_month' => 1,
                    'departure_date_count' => 1,
                    'description' => 1,
                    'groups' => 1,
                    'id_media_object' => 1,
                    'id_object_type' => 1,
                    'last_modified_date' => 1,
                    'possible_durations' => 1,
                    'prices' => 1,
                    'url' => 1,
                    'valid_from' => 1,
                    'valid_to' => 1,
                    'visibility' => 1
                ]
            ];
        }
        return null;
    }
}
