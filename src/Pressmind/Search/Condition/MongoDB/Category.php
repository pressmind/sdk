<?php

namespace Pressmind\Search\Condition\MongoDB;

class Category
{
    private $_categoryIds;

    private $_varName;

    private $_combineOperator;

    public function __construct($varName, $categoryIds, $combineOperator = 'OR')
    {
        $this->_varName = $varName;
        if(!is_array($categoryIds)) {
            $categoryIds = [$categoryIds];
        }
        $this->_categoryIds = $categoryIds;
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
            $query = [
                'categories.field_name' => $this->_varName,
            ];
            if (count($this->_categoryIds) > 1) {
                $ids = [];
                foreach ($this->_categoryIds as $categoryId) {
                    $ids[] = ['categories.id_item' => $categoryId];
                }
                $query['$' . strtolower($this->_combineOperator)] = $ids;
            } else {
                $query['categories.id_item'] = $this->_categoryIds[0];
            }
            return $query;
        }
        return null;
    }
}
