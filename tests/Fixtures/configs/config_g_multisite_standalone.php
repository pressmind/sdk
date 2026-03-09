<?php

/**
 * Config G: Multisite standalone style. Multiple ibe_client IDs (anonymized).
 */
return [
    'cache' => ['enabled' => false, 'types' => []],
    'logging' => ['enable_advanced_object_log' => false],
    'date_filter' => ['active' => true, 'orientation' => 'departure', 'offset' => 0],
    'ibe_client' => 100001,
    'price_mix_types' => ['date_housing', 'date_ticket'],
    'transport_types' => ['BUS', 'PKW', 'FLUG'],
];
