<?php

/**
 * Config D: Flight-dominant, high occupancy (1-10). Earlybird ~69%.
 */
return [
    'cache' => ['enabled' => false, 'types' => []],
    'logging' => ['enable_advanced_object_log' => false],
    'date_filter' => ['active' => true, 'orientation' => 'departure', 'offset' => 0],
    'price_mix_types' => ['date_housing'],
    'transport_types' => ['FLUG', 'PKW', 'BUS'],
];
