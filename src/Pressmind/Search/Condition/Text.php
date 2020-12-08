<?php


namespace Pressmind\Search\Condition;


use stdClass;

class Text implements ConditionInterface
{
    /**
     * @var integer
     */
    private $_sort = 1;

    /**
     * The terms to be searched
     * @var array
     */
    private $_search_terms;

    /**
     * MediaObject properties to be queried, associative array in the form 'property' => 'comparison operator'
     * @example
     * ['headline' => 'LIKE', 'tags' => 'IN', 'someproperty' => '!=', 'another_property' => '=']
     * @var array
     */
    private $_properties_to_be_queried;

    /**
     * The logic operator that should be used on the query (enum, can be 'AND' or 'OR', defaults to 'OR')
     * @var string
     */
    private $_logic_operator = 'AND';

    /**
     * The values used for a pdo prepared statement
     * @var array
     */
    private $_values = [];

    /**
     * @var null
     */
    private $_object_type_id;

    /**
     * Fulltext constructor.
     * @param null $pObjectTypeId
     * @param null $pSearchTerms
     * @param null $pProperties
     * @param string $pLogicOperator
     */
    public function __construct($pObjectTypeId = null, $pSearchTerms = null, $pProperties = null, $pLogicOperator = 'AND')
    {
        $this->_splitSearchTerms($pSearchTerms);
        $this->_properties_to_be_queried = $pProperties;
        $this->_logic_operator = $pLogicOperator;
        $this->_object_type_id = $pObjectTypeId;
    }

    /**
     * @return string
     */
    public function getSQL()
    {
        $property_queries = [];
        foreach ($this->_properties_to_be_queried as $property_name => $comparison_operator) {
            $term_counter = 0;
            $sql_strings = [];
            foreach ($this->_search_terms as $search_term) {
                $term_counter++;
                if($comparison_operator == 'LIKE') {
                    $search_term = '%' . $search_term . '%';
                }
                $this->_values[':' . $property_name . $term_counter] = $search_term;
                $sql_strings[] = $property_name . ' ' . $comparison_operator . ' ' . ':' . $property_name . $term_counter;
            }
            $property_queries[] = implode(' OR ', $sql_strings);
        }
        $sql = '(' . implode(') ' . $this->_logic_operator . ' (', $property_queries) . ')';
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
        return 'INNER JOIN objectdata_' . $this->_object_type_id . ' ON objectdata_' . $this->_object_type_id . '.id_media_object = pmt2core_media_objects.id';
    }

    public function getAdditionalFields()
    {
        return null;
    }

    /**
     * @param string $pSearchTerms
     */
    private function _splitSearchTerms($pSearchTerms)
    {
        $this->_search_terms = explode(' ', $pSearchTerms);
    }

    /**
     * @param null $pObjectTypeId
     * @param null $pSearchTerms
     * @param null $pProperties
     * @param string $pLogicOperator
     * @return Text
     */
    public static function create($pObjectTypeId = null, $pSearchTerms = null, $pProperties = null, $pLogicOperator = 'AND')
    {
        $object = new self($pObjectTypeId, $pSearchTerms, $pProperties, $pLogicOperator);
        return $object;
    }

    /**
     * @param stdClass $config
     */
    public function setConfig($config) {
        $this->_object_type_id = $config->object_type_id;
        $this->_splitSearchTerms($config->search_terms);
        $this->_properties_to_be_queried = $config->properties_to_be_queried;
        $this->_logic_operator = isset($config->logic_operator) ? $config->logic_operator : 'AND';
    }

    /**
     * @return array
     */
    public function getConfig() {
        return [
            'object_type_id' => $this->_object_type_id,
            'search_terms' => implode(' ', $this->_search_terms),
            'logic_operator' => $this->_logic_operator,
            'properties_to_be_queried' => $this->_properties_to_be_queried
        ];
    }

    /**
     * @return array
     * @throws \ReflectionException
     */
    public function toJson() {
        $data = [
            'type' => (new \ReflectionClass($this))->getShortName(),
            'config' => $this->getConfig()
        ];
        return $data;
    }
}
