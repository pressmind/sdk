<?php


namespace Pressmind\Import;


class ManualCheapestPrice extends AbstractImport implements ImportInterface
{

    private $_data;

    public function __construct($data)
    {
        $this->_data = $data;
    }

    public function import()
    {
        foreach ($this->_data as $manual_cheapest_price) {
            $obj = new \Pressmind\ORM\Object\MediaObject\ManualCheapestPrice();
            $obj->fromImport($manual_cheapest_price);
            $obj->create();
        }
    }
}
