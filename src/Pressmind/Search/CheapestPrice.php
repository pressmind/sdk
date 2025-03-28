<?php


namespace Pressmind\Search;


class CheapestPrice
{
    const STATE_BOOKABLE = 3;
    const STATE_REQUEST = 1;
    const STATE_STOP = 5;
    public $id = null;
    public $duration_from = null;
    public $duration_to = null;
    public $occupancies = [];
    public $occupancies_disable_fallback = false;
    public $date_from = null;
    public $date_to = null;
    public $price_from = null;
    public $price_to = null;
    public $id_date = null;
    public $id_option = null;
    public $id_booking_package = null;
    public $id_housing_package = null;
    public $housing_package_code_ibe = null;
    public $transport_types = [];
    public $transport_1_airport = [];
    public $origin = null;
    public $agency = null;
    public $state = self::STATE_BOOKABLE;
    public $id_startingpoint_option = null;
    public $startingpoint_option_name = null;
    public $startingpoint_id_city = null;
    public $housing_package_id_name = null;
    /**
     * Leave empty for no fallback
     * @var int[] $state_fallback_order
     */
    public $state_fallback_order = [self::STATE_BOOKABLE, self::STATE_REQUEST, self::STATE_STOP];

    /**
     * @return void
     */
    public function initFromGet()
    {
        $params = [
            'id',
            'duration_from',
            'duration_to',
            'occupancies',
            'occupancies_disable_fallback',
            'date_from',
            'date_to',
            'price_from',
            'price_to',
            'id_date',
            'id_option',
            'id_booking_package',
            'id_housing_package',
            'housing_package_code_ibe',
            'transport_types',
            'transport_1_airport',
            'origin',
            'agency',
            'state',
            'id_startingpoint_option',
            'startingpoint_option_name',
            'startingpoint_id_city',
            'housing_package_id_name'
        ];
        foreach ($params as $param) {
            if (property_exists($this, $param) && !empty($_GET[$param])) {
                if($param == 'occupancies' || $param == 'transport_types' || $param == 'transport_1_airport'){
                    $this->$param = explode(',', $_GET[$param]);
                    continue;
                }
                if($param == 'occupancies_disable_fallback'){
                    $this->$param = (bool)$_GET[$param];
                    continue;
                }
                if($param == 'state'){
                    $this->$param = (int)$_GET[$param];
                    if($this->$param === 0){
                        $this->$param = null;
                    }
                    continue;
                }
                if($param == 'price_from' || $param == 'price_to'){
                    $this->$param = (float)$_GET[$param];
                    continue;
                }
                if($param == 'duration_from' || $param == 'duration_to'){
                    $this->$param = (int)$_GET[$param];
                    continue;
                }
                if($param == 'date_from' || $param == 'date_to'){
                    $this->$param = \DateTime::createFromFormat('Ymd', $_GET[$param]);
                    continue;
                }
                $this->$param = preg_replace('/[^a-zA-Z0-9_\-,]/', '', $_GET[$param]);
            }
        }
    }

}
