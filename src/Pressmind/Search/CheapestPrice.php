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
    /**
     * Leave empty for no fallback
     * @var int[] $state_fallback_order
     */
    public $state_fallback_order = [self::STATE_BOOKABLE, self::STATE_REQUEST, self::STATE_STOP];
}
