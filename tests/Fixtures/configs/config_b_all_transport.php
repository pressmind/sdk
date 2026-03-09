<?php

/**
 * Config B: All 5 transport types, 3 price-mix types, strong earlybird.
 * Use for tests that need full transport and earlybird behaviour.
 */
return [
    'cache' => ['enabled' => false, 'types' => []],
    'logging' => ['enable_advanced_object_log' => false],
    'date_filter' => ['active' => true, 'orientation' => 'departure', 'offset' => 0],
    'price_mix_types' => ['date_housing', 'date_ticket', 'date_transport'],
    'transport_types' => ['BUS', 'PKW', 'FLUG', 'BAH', 'SCH'],
];
