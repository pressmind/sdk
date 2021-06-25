<?php


namespace Pressmind\Search\Condition;


use stdClass;

class CategoryGlobal implements ConditionInterface
{

    /**
     * @var array
     */
    public $item_ids = [];

    /**
     * @var array
     */
    private $_values = [];

    /**
     * @var int
     */
    private $_sort = 3;

    /**
     * @var string
     */
    private $_combine_operator;

    /**
     * Category constructor.
     * @param array $pItemIds
     * @param string $pCombineOperator
     */
    public function __construct($pItemIds = null, $pCombineOperator = 'OR')
    {
        $this->item_ids = $pItemIds;
        $this->_combine_operator = $pCombineOperator;
    }

    /**
     * @return string
     */
    public function getSQL()
    {
        $item_id_strings = [];
        $term_counter = 0;
        foreach ($this->item_ids as $item_id) {
            $term_counter++;
            $item_id_strings[] = 'id_item = :category_item' . $term_counter;
            $this->_values[':category_item' . $term_counter] = $item_id;
        }
        $sql = "(" . implode(' ' . $this->_combine_operator . ' ', $item_id_strings) . ")";
        return $sql;
    }

    /**
     * @return int
     */
    public function getSort()
    {
        return $this->_sort;
    }

    /**
     * @return array
     */
    public function getValues()
    {
        return $this->_values;
    }

    /**
     * @return string|null
     */
    public function getJoins()
    {
        return 'INNER JOIN pmt2core_media_object_tree_items ON pmt2core_media_objects.id = pmt2core_media_object_tree_items.id_media_object';
    }

    /**
     * @return string|null
     */
    public function getJoinType()
    {
        return null;
    }

    /**
     * @return string|null
     */
    public function getSubselectJoinTable()
    {
        return null;
    }

    /**
     * @return string|null
     */
    public function getAdditionalFields()
    {
        return null;
    }

    /**
     * @param string $pVarName
     * @param array $pItemIds
     * @param string $pCombineOperator
     * @return Category
     */
    public static function create($pItemIds, $pCombineOperator = 'OR')
    {
        $object = new self($pItemIds, $pCombineOperator);
        return $object;
    }

    /**
     * @param stdClass $config
     */
    public function setConfig($config) {
        $this->item_ids = $config->item_ids;
        $this->_combine_operator = isset($config->combine_operator) ? $config->combine_operator : 'OR';
    }
}
