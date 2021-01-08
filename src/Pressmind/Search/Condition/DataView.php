<?php


namespace Pressmind\Search\Condition;


use Exception;
use stdClass;

class DataView implements ConditionInterface
{

    public $name;

    public $id;

    /**
     * @var \Pressmind\ORM\Object\DataView
     */
    private $dataView;

    private $_sql = [];

    private $_values = [];

    private $_joins = [];

    /**
     * DataView constructor.
     * @param string $name
     * @param integer $id
     */
    public function __construct($name = null, $id = null)
    {
        $config = new stdClass();
        $config->name = $name;
        $config->id = $id;
        //$this->setConfig($config);
    }

    public function getSQL()
    {
        return implode(' AND ', $this->_sql);
    }

    public function getValues()
    {
        return $this->_values;
    }

    public function getSort()
    {
        return 1;
    }

    public function getJoins()
    {
        return implode(' ', $this->_joins);
    }

    public function getAdditionalFields()
    {
        return null;
    }

    public function setConfig($config)
    {
        $this->name = isset($config->name) ? $config->name : null;
        $this->id = isset($config->id) ? $config->id : null;
        if(!is_null($this->name)) {
            if ($dataViews = \Pressmind\ORM\Object\DataView::listAll(['name' => $this->name])) {
                $this->dataView = $dataViews[0];
            } else {
                throw new Exception('DataView with name ' . $this->name . ' does not exist');
            }
        } else if (!is_null($this->id)) {
            $this->dataView = new \Pressmind\ORM\Object\DataView(intval($this->id), true);
        } else {
            throw new Exception('Missing parameters! "name" or "id" has to be given!');
        }
        foreach ($this->dataView->search_conditions as $search_condition) {
            $condition_class_name = '\Pressmind\Search\Condition\\' . $search_condition->class_name;
            $condition = new $condition_class_name();
            $condition->setConfig(json_decode($search_condition->values));
            $this->_sql[] = $condition->getSql();
            if (!empty($condition->getJoins())) {
                $this->_joins[] = $condition->getJoins();
            }
            foreach ($condition->getValues() as $key => $value) {
                $this->_values[$key] = $value;
            }
        }
    }

    public function getConfig()
    {
        return [
            'name' => $this->name
        ];
    }

    /**
     * @param null $name
     * @return DataView
     */
    public static function create($name = null, $id = null)
    {
        $object = new self($name, $id);
        return $object;
    }
}