<?php
namespace Pressmind\ORM\Filter;

use \Exception;
/**
 * Class Factory
 * @package Pressmind
 */
class Factory {

    /**
     * @param string $pFilterName
     * @param string $pDirection
     * @param null|array $pParams
     * @return FilterInterface
     * @throws Exception
     */
    public static function create($pFilterName, $pDirection, $pParams = null) {
        $filter_name = ucfirst($pDirection) . '\\' . ucfirst($pFilterName);
        $filter_class_name = '\\Pressmind\\ORM\\Filter\\' . $filter_name . 'Filter';
        $params = null;
        if(isset($pParams) && is_array($pParams)) {
            $params = $pParams;
        }
        return new $filter_class_name($params);
    }
}
