<?php
namespace Pressmind\ORM\Filter;

interface FilterInterface {
    public function filterValue($pValue);
    public function getErrors();
}
