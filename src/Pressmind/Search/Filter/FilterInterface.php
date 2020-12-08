<?php


namespace Pressmind\Search\Filter;


use Pressmind\Search;
use stdClass;

interface FilterInterface
{
    /**
     * @param Search $search
     * @return void
     */
    public function setSearch($search);

    /**
     * @return mixed
     */
    public function getResult();

    /**
     * @param stdClass $config
     * @return mixed
     */
    public function setConfig($config);
}
