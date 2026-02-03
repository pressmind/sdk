<?php

namespace Pressmind\Search\Condition\MongoDB;

class Category
{
    /**
     * @var array|mixed
     */
    private $_categoryIds;

    /**
     * @var mixed|null
     */
    private $_categoryIdsNot;

    /**
     * @var string
     */
    private $_varName;

    /**
     * @var mixed|string
     */
    private $_combineOperator;

    public function __construct($varName, $categoryIds, $combineOperator = 'OR', $categoryIdsNot = null)
    {
        $this->_varName = $varName;
        if(!is_array($categoryIds)) {
            $categoryIds = [$categoryIds];
        }
        $this->_categoryIds = $categoryIds;
        if($categoryIdsNot !== null && !is_array($categoryIdsNot)) {
            $categoryIdsNot = [$categoryIdsNot];
        }
        $this->_categoryIdsNot = $categoryIdsNot;
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
            // Exclude categories if categoryIdsNot is set
            if(!empty($this->_categoryIdsNot)) {
                $notCondition = [
                    'categories' => [
                        '$not' => [
                            '$elemMatch' => [
                                'field_name' => $this->_varName,
                                'id_item' => ['$in' => $this->_categoryIdsNot]
                            ]
                        ]
                    ]
                ];
                $query = ['$and' => [$query, $notCondition]];
            }
            return $query;
        }
        return null;
    }
}
