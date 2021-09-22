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
        $this->_searchString = $searchString;
    }

    public function getQuery($type = '$match')
    {
        if($type == '$match') {
            return [
                '$text' => [
                    '$search' => $this->_searchString
                ]
            ];
        }
        return null;
    }
}
