<?php


namespace Pressmind\Import\Mapper;


use stdClass;

interface MapperInterface
{
    /**
     * @param integer $pIdMediaObject
     * @param string $pLanguage
     * @param string $pVarName
     * @param stdClass $pObject
     * @return array
     */
    public function map($pIdMediaObject, $pLanguage, $pVarName, $pObject);
}
