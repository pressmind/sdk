<?php

namespace Pressmind\Search;

class CalendarFilter
{
    public $id = null;
    public $id_booking_package = null;
    public $id_housing_package = null;
    public $housing_package_code_ibe = null;
    public $occupancy = null;
    public $transport_type = null;
    public $duration = null;
    public $airport = null;
    public $startingpoint_id_city = null;
    public $agency = null;

    /**
     * @return bool
     */
    public function initFromGet()
    {
        $something_set = false;
        $params = [
            'id',
            'id_booking_package',
            'id_housing_package',
            'housing_package_code_ibe',
            'occupancy',
            'transport_type',
            'duration',
            'airport',
            'startingpoint_id_city',
            'agency'
        ];
        foreach ($params as $param) {
            if (isset($_GET[$param])) {
                $something_set = true;
                $this->$param = preg_replace('/[^a-zA-Z0-9_\-,]/', '', $_GET[$param]);
            }
        }
        return $something_set;
    }

}
