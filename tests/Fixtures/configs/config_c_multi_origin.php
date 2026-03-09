<?php

/**
 * Config C: Multi-origin, agency-based option and prices.
 */
return [
    'cache' => ['enabled' => false, 'types' => []],
    'logging' => ['enable_advanced_object_log' => false],
    'date_filter' => ['active' => true, 'orientation' => 'departure', 'offset' => 0],
    'origins' => [1, 2],
    'agency_based_option_and_prices' => true,
    'price_mix_types' => ['date_housing', 'date_ticket'],
    'transport_types' => ['PKW', 'BUS'],
];
