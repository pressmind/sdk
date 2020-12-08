<?php


namespace Pressmind\REST\Controller;


use Exception;
use Pressmind\ORM\Object\AbstractObject;
use ReflectionClass;
use ReflectionException;

abstract class AbstractController
{
    protected $orm_class_name = null;

    /** @var AbstractObject */
    private $orm_class = null;

    /**
     * AbstractController constructor.
     * @throws ReflectionException
     */
    public function __construct()
    {
        if(is_null($this->orm_class_name)) {
            $this->orm_class_name = '\\Pressmind\\ORM\\Object\\' . (new ReflectionClass($this))->getShortName();
        }
        $this->orm_class = new $this->orm_class_name();
    }

    /**
     * @param $parameters
     * @return array|mixed|AbstractObject
     * @throws Exception
     */
    public function listAll($parameters) {
        $readRelations = false;
        $apiTemplate = null;
        $limit = null;
        $order = null;
        $properties = null;
        if(isset($parameters['readRelations'])) {
            $readRelations = boolval($parameters['readRelations']);
            unset($parameters['readRelations']);
        }
        if(isset($parameters['apiTemplate'])) {
            $apiTemplate = $parameters['apiTemplate'];
            unset($parameters['apiTemplate']);
        }
        if(isset($parameters['start']) && isset($parameters['limit'])) {
            $limit = [$parameters['start'], $parameters['limit']];
            unset($parameters['start']);
            unset($parameters['limit']);
        }
        if(isset($parameters['properties'])) {
            $properties = $parameters['properties'];
            unset($parameters['properties']);
        }
        if(count($parameters) == 1 && isset($parameters['id'])) {
            return $this->read($parameters['id'], $readRelations, $apiTemplate, $properties);
        }
        $this->orm_class->setReadRelations($readRelations);
        if(count($parameters) == 0) $parameters = null;
        if(!is_null($apiTemplate)) {
            $result = [];
            foreach($this->orm_class->loadAll($parameters, $order, $limit) as $object) {
                $result[] = $object->renderApiOutputTemplate($apiTemplate);
            }
            return $result;
        }
        if(!is_null($properties) && is_array($properties)) {
            $return = [];
            $i=0;
            foreach($this->orm_class->loadAll($parameters, $order, $limit) as $object) {
                $return[$i] = [];
                foreach ($properties as $property_name) {
                    $return[$i][$property_name] = $object->$property_name;
                }
                $i++;
            }
            return $return;
        }
        return $this->orm_class->loadAll($parameters, $order, $limit);
    }

    /**
     * @param $id
     * @param bool $readRelations
     * @param bool $apiTemplate
     * @param array $properties
     * @return mixed|AbstractObject
     * @throws Exception
     */
    public function read($id, $readRelations = false, $apiTemplate = false, $properties = null) {
        $this->orm_class->read($id);
        $this->orm_class->setReadRelations($readRelations);
        $this->orm_class->readRelations();
        if(!is_null($apiTemplate)) {
            return $this->orm_class->renderApiOutputTemplate($apiTemplate);
        }
        if(!is_null($properties) && is_array($properties)) {
            $return = [];
            foreach ($properties as $property_name) {
                $return[$property_name] = $this->orm_class->$property_name;
            }
            return $return;
        }
        return $this->orm_class;
    }
}
