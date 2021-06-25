<?php


namespace Pressmind\Search\Condition;


use stdClass;

class Fulltext implements ConditionInterface
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
     * @var string
     */
    private $_mode = 'NATURAL LANGUAGE MODE';

    /**
     * The values used for a pdo prepared statement
     * @var array
     */
    private $_values = [];

    /**
     * The logic operator that should be used on the query (enum, can be 'AND' or 'OR', defaults to 'OR')
     * @var string
     */
    private $_logic_operator;

    /**
     * Fulltext constructor.
     * @param string $pSearchTerms
     * @param array $pProperties
     * @param string $pLogicOperator
     * @param string $pMode
     */
    public function __construct($pSearchTerms = null, $pProperties = ['fulltext'], $pLogicOperator = 'OR', $pMode = 'NATURAL LANGUAGE MODE')
    {
        $this->_splitSearchTerms($pSearchTerms);
        $this->_properties_to_be_queried = $pProperties;
        $this->_mode = $pMode;
        $this->_logic_operator = $pLogicOperator;
    }

    /**
     * @return string
     */
    public function getSQL()
    {
        $property_queries = [];
        foreach ($this->_properties_to_be_queried as $property_name) {
            $values = [];
            foreach ($this->_search_terms as $search_term) {
                if(strtolower($this->_mode) == 'boolean mode') {
                    $search_term = '+' . $search_term;
                }
                $values[] = $search_term;
            }
            $property_queries[] = "pmt2core_fulltext_search.var_name = '" . $property_name . "' AND MATCH(pmt2core_fulltext_search.fulltext_values) AGAINST (:" . $property_name . " IN " . $this->_mode . ")";
            $this->_values[':' . $property_name] = implode(' ', $values);
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
        //return 'INNER JOIN objectdata_' . $this->_object_type_id . ' ON objectdata_' . $this->_object_type_id . '.id_media_object = pmt2core_media_objects.id';
        return 'INNER JOIN pmt2core_fulltext_search ON pmt2core_fulltext_search.id_media_object = pmt2core_media_objects.id';
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
     * @param string $pSearchTerms
     * @param array $pProperties
     * @param string $pLogicOperator
     * @param string $pMode
     * @return Fulltext
     */
    public static function create($pSearchTerms, $pProperties = ['fulltext'], $pLogicOperator = 'OR', $pMode = 'NATURAL LANGUAGE MODE') {
        $object = new self( $pSearchTerms, $pProperties, $pLogicOperator, $pMode);
        return $object;
    }

    /**
     * @param stdClass $config
     */
    public function setConfig($config) {
        $this->_splitSearchTerms($config->search_terms);
        $this->_properties_to_be_queried = isset($config->properties_to_be_queried) ? $config->properties_to_be_queried : ['fulltext'];
        $this->_logic_operator = isset($config->logic_operator) ? $config->logic_operator : 'OR';
        $this->_mode = isset($config->mode) ? $config->mode : 'NATURAL LANGUAGE MODE';
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
