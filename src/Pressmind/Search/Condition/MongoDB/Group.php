<?php

namespace Pressmind\Search\Condition\MongoDB;

class Group
{
    private $_groups;

    private $_combineOperator;

    public function __construct($groups, $combineOperator = 'OR')
    {
        if(!is_array($groups)) {
            $groups = [$groups];
        }
        $this->_groups = $groups;
        $this->_combineOperator = $combineOperator;
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
            $q = [];
            foreach ($this->_groups as $group) {
                $q[] = ['groups' => $group];
            }
            $q[] = ['groups' => ['$size' => 0]];
            $query['$or'] = $q;
            return $query;
        }
        return null;
    }
}
