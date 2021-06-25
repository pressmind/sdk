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
        foreach ($this->item_ids as $item_id) {
            $term_counter++;
            $item_id_strings[] = $this->var_name . '.id_item = :' . $this->var_name . $term_counter;
            $item_id_strings_linked_search[] = $this->var_name . '_mo.id_item = :' . $this->var_name . $term_counter;
            $this->_values[':' . $this->var_name . $term_counter] = $item_id;
        }
        $sql = '';
        if($this->_linked_object_search) {
            $sql = "(";
        }
        $sql .= $this->var_name . ".var_name = '" . $this->var_name . "' AND (" . implode(' ' . $this->_combine_operator . ' ', $item_id_strings) . ")";
        if($this->_linked_object_search) {
            $sql .= " OR " . $this->var_name . "_mo.var_name = '" . $this->var_name . "' AND (" . implode(' ' . $this->_combine_operator . ' ', $item_id_strings_linked_search) . "))";
        }
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
        $joins = [];
        if($this->_linked_object_search) {
            $joins[] = 'LEFT JOIN pmt2core_media_object_tree_items ' . $this->var_name . '_mo ON pmt2core_media_objects.id = ' . $this->var_name . '_mo.id_media_object';
            $joins[] = 'LEFT JOIN pmt2core_media_object_object_links ON (pmt2core_media_objects.id = pmt2core_media_object_object_links.id_media_object)';
            $joins[] = 'LEFT JOIN pmt2core_media_object_tree_items ' . $this->var_name . ' ON pmt2core_media_object_object_links.id_media_object_link = ' . $this->var_name . '.id_media_object';
        } else {
            $joins[] = 'INNER JOIN pmt2core_media_object_tree_items ' . $this->var_name . ' ON pmt2core_media_objects.id = ' . $this->var_name . '.id_media_object';
        }
        return implode(' ', $joins);
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
