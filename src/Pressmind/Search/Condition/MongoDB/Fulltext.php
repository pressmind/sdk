<?php

namespace Pressmind\Search\Condition\MongoDB;

use Pressmind\ORM\Object\FulltextSearch;

class Fulltext
{
    /**
     * @var string
     */
    private $_searchString;

    /**
     * @var string
     */
    private $_searchStringRaw;

    /**
     * @param string $searchString
     */
    public function __construct($searchString)
    {
        $this->_searchStringRaw = $searchString;
        $this->_searchString = trim(str_replace(["\\", '"'], ['', ''], FulltextSearch::replaceChars($searchString)));
    }

    /**
     * @return string
     */
    public function getSearchStringRaw()
    {
        return $this->_searchStringRaw;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return (new \ReflectionClass($this))->getShortName();
    }

    /**
     * @param $type
     * @return array[]|null
     */
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
