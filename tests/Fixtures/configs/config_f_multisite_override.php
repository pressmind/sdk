<?php

/**
 * Config F: Multisite base config. Placeholder DB; subsites override via require.
 */
return [
    'cache' => ['enabled' => false, 'types' => []],
    'logging' => ['enable_advanced_object_log' => false],
    'date_filter' => ['active' => true, 'orientation' => 'departure', 'offset' => 14],
    'price_mix_types' => ['date_housing'],
    'transport_types' => ['BUS', 'FLUG'],
];
