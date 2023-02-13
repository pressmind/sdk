<?php

namespace Pressmind\Search\Condition\MongoDB;

class BoardType
{
    private $_boardType;

    public function __construct($boardType)
    {
        $this->_boardType = !is_array($boardType) ? [$boardType] : $boardType;
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
            return ['prices' => ['$elemMatch' => ['option_board_type' => ['$in' => $this->_boardType]]]];
        }
        if($type == 'prices_filter') {
            return ['$in' => ['$$this.option_board_type', $this->_boardType]];
        }
        return null;
    }
}
