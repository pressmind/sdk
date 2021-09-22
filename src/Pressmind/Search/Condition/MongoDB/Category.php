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

    public function getQuery($type = '$match')
    {
        if($type == '$match') {
            $query = [
                'categories.field_name' => $this->_varName,
            ];
            if (count($this->_categoryIds) > 1) {
                $foo = [];
                foreach ($this->_categoryIds as $categoryId) {
                    $foo[] = ['categories.id_item' => $categoryId];
                }
                $query['$' . strtolower($this->_combineOperator)] = $foo;
            } else {
                $query['categories.id_item'] = $this->_categoryIds[0];
            }
            return $query;
        }
        return null;
    }
}
