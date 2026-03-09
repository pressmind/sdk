<?php

/**
 * Config E: Multiple object types (e.g. 11 cabin types), high request-state share.
 */
return [
    'cache' => ['enabled' => false, 'types' => []],
    'logging' => ['enable_advanced_object_log' => false],
    'date_filter' => ['active' => true, 'orientation' => 'departure', 'offset' => 0],
    'price_mix_types' => ['date_housing'],
    'transport_types' => ['PKW', 'BUS', 'FLUG'],
];
