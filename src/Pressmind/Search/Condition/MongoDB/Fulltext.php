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
    public function getType(){
        return (new \ReflectionClass($this))->getShortName();
    }

    public function getQuery($type = 'first_match')
    {
        if($type == 'first_match') {
            return [
                '$text' => [
                    '$search' => '"'.$this->_searchString.'"'
                ]
            ];
        }
        return null;
    }
}
