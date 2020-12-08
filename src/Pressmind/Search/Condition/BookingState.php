<?php


namespace Pressmind\Search\Condition;


class BookingState implements ConditionInterface
{
    /**
     * @var string
     */
    private $_var_name;

    /**
     * @var array
     */
    private $_state_ids = [];

    /**
     * @var array
     */
    private $_values = [];

    /**
     * @var int
     */
    private $_sort = 6;

    /**
     * Category constructor.
     * @param $pVarName
     * @param $pItemIds
     */
    public function __construct($pVarName, $pStateIds)
    {
        $this->_var_name = $pVarName;
        $this->_state_ids = $pStateIds;
    }

    public function getSQL()
    {
        $item_id_strings = [];
        $term_counter = 0;
        foreach ($this->_item_ids as $item_id) {
            $term_counter++;
            $item_id_strings[] = 'pmt2core_touristic_options.state = :booking_state' . $term_counter;
            $this->_values[':booking_state' . $term_counter] = $item_id;
        }
        $sql = "pmt2core_media_object_tree_items.var_name = '" . $this->_var_name . "' OR (" . implode(' OR ', $item_id_strings) . ")";
        return $sql;
    }

    public function getSort()
    {
        return $this->_sort;
    }

    public function getValues()
    {
        return $this->_values;
    }

    public function getJoins()
    {
        return 'INNER JOIN pmt2core_touristic_options on pmt2core_media_objects.id = pmt2core_touristic_options.id_media_object';
    }

    public function getAdditionalFields()
    {
        return null;
    }

    public function setConfig($config)
    {
        $this->_state_ids = $config->state_ids;
    }
}
