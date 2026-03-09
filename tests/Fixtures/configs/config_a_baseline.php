<?php

/**
 * Config A: Baseline without date filter. No earlybird tables.
 * Use for tests that need minimal config (BUS + FLUG only).
 * DB/API connection must be provided via ENV in test bootstrap.
 */
return [
    'cache' => ['enabled' => false, 'types' => []],
    'logging' => ['enable_advanced_object_log' => false],
    'date_filter' => ['active' => false],
    'price_mix_types' => ['date_housing'],
    'transport_types' => ['BUS', 'FLUG'],
];
