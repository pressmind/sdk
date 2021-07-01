<?php


namespace Pressmind\Search\Condition;


use stdClass;

class Category implements ConditionInterface
{

    /**
     * @var string
     */
    public $var_name;

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
     * @var bool
     */
    private $_linked_object_search;

    /**
     * Category constructor.
     * @param string $pVarName
     * @param array $pItemIds
     * @param string $pCombineOperator
     * @param boolean $linkedObjectSearch
     */
    public function __construct($pVarName = null, $pItemIds = null, $pCombineOperator = 'OR', $linkedObjectSearch = false)
    {
        $this->var_name = $pVarName;
        $this->item_ids = $pItemIds;
        $this->_combine_operator = $pCombineOperator;
        $this->_linked_object_search = $linkedObjectSearch;
    }

    /**
     * @return string
     */
    public function getSQL()
    {
        $item_id_strings = [];
        $term_counter = 0;
        $tablename = 'pmt2core_media_object_tree_items';
        foreach ($this->item_ids as $item_id) {
            $term_counter++;
            $item_id_strings[] = $tablename . '.id_item = :' . $this->var_name . $term_counter;
            $this->_values[':' . $this->var_name . $term_counter] = $item_id;
        }
        return $tablename . ".var_name = '" . $this->var_name . "' AND (" . implode(' ' . $this->_combine_operator . ' ', $item_id_strings) . ")";
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
        $joins = [];
        if($this->_linked_object_search) {
            $joins[] = 'INNER JOIN (SELECT pmt2core_media_object_object_links.id_media_object, pmt2core_media_object_object_links.id_media_object_link FROM pmt2core_media_object_object_links) ' . $this->var_name . '_mo ON ' . $this->var_name . '_mo.id_media_object = pmt2core_media_objects.id';
            $joins[] = 'INNER JOIN(
                SELECT pmt2core_media_object_tree_items.id_media_object FROM pmt2core_media_object_tree_items
                WHERE ###CONDITIONS###
                GROUP BY pmt2core_media_object_tree_items.id_media_object
            ) ' . $this->var_name . ' on ' . $this->var_name . '.id_media_object = ' . $this->var_name . '_mo.id_media_object_link';
        } else {
            $joins[] = 'INNER JOIN (SELECT pmt2core_media_object_tree_items.id_media_object FROM pmt2core_media_object_tree_items WHERE ###CONDITIONS### GROUP BY pmt2core_media_object_tree_items.id_media_object) ' . $this->var_name . ' ON pmt2core_media_objects.id = ' . $this->var_name . '.id_media_object';
        }
        return implode(' ', $joins);
    }

    /**
     * @return string|null
     */
    public function getJoinType()
    {
        return 'SUBSELECT';
    }

    /**
     * @return string|null
     */
    public function getSubselectJoinTable()
    {
        return $this->var_name;//'pmt2core_media_object_tree_items';
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
    public static function create($pVarName, $pItemIds, $pCombineOperator = 'OR', $linkedObjectSearch = false)
    {
       return new self($pVarName, $pItemIds, $pCombineOperator, $linkedObjectSearch);
    }

    /**
     * @param stdClass $config
     */
    public function setConfig($config) {
        $this->var_name = isset($config->var_name) ? $config->var_name : null;
        $this->item_ids = isset($config->item_ids) ? $config->item_ids : null;
        $this->_combine_operator = isset($config->combine_operator) ? $config->combine_operator : 'OR';
        $this->_linked_object_search = isset($config->linked_object_search) ? $config->linked_object_search : null;
    }
}
