<?php


namespace Pressmind\Search\Condition;


interface ConditionInterface
{
    /**
     * @return string
     */
    public function getSQL();

    /**
     * @return array
     */
    public function getValues();

    /**
     * @return integer
     */
    public function getSort();

    /**
     * @return null|string
     */
    public function getJoins();

    /**
     * @return null|string
     */
    public function getAdditionalFields();

    /**
     * @param \stdClass $config
     */
    public function setConfig($config);
}
