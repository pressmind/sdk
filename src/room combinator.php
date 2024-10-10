<?php

// Tabelle mit den Zimmerdaten
$zimmer = array(
    array('ID' => 1, 'price_total' => 120, 'occupancy' => 1),
    array('ID' => 2, 'price_total' => 101, 'occupancy' => 2),
    array('ID' => 3, 'price_total' => 102, 'occupancy' => 3),
    array('ID' => 4, 'price_total' => 103, 'occupancy' => 3),
    array('ID' => 4, 'price_total' => 103, 'occupancy' => 6),
    array('ID' => 5, 'price_total' => 104, 'occupancy' => 1)
);


// ermittle aus $zimmer die günstige Kombination an Zimmern (gib die IDs der Zimmer zurück).
// Es kann vorkommen, dass eine Personanzahl gesucht wird die nicht direkt der Zimmerbelegung entspricht.
// Beispiel: 3 Personen suchen ein Zimmer. Es gibt aber nur Zimmer mit 1, 2 oder 3 Personen - in diesem Fall muss 1 Zimmer mit einer Person und ein Zimmer mit 2 Personen geliefert werden.


/**
 * @param int $persons
 * @param array $rooms
 * @param string $strategy match_by_occupancy | match_by_price
 * (match_by_occupancy = delivers the cheapest room if has a direct match on occupancy, use so few rooms as possible)
 * (match_by_price = delivers the cheapest room if has a direct match on occupancy, else use the cheapest room combination)
 * @return array
 */
function getCheapestRooms($persons, $rooms, $strategy = 'match_by_occupancy')
{
    // removed unneeded rooms
    $rooms = array_filter($rooms, function ($room) use ($persons) {
        return $room['occupancy'] <= $persons;
    });

    // remove rooms with the same occupancy - use only the cheapest
    // TOOO: this must be remove if the room amount is used for calculation
    usort($rooms, function ($a, $b) {
        return $a['price_total'] <=> $b['price_total'];
    });
    $rooms = array_filter($rooms, function ($room) {
        static $occupancies = [];
        if (in_array($room['occupancy'], $occupancies)) {
            return false;
        }
        $occupancies[] = $room['occupancy'];
        return true;
    });

    // check for direct match
    $roomCombination = [];
    foreach ($rooms as $room) {
        if ($room['occupancy'] == $persons) {
            $roomCombination[] = $room;
            return $roomCombination;
        }
    }

    // find combinations
    if ($strategy == 'match_by_occupancy') {
        usort($rooms, function ($a, $b) {
            return $b['occupancy'] <=> $a['occupancy'];
        });
        $max_runs = $persons;
        while ($persons > 0) {
            if ($max_runs-- < 0) {
                break;
            }
            foreach ($rooms as $room) {
                if ($room['occupancy'] <= $persons) {
                    $roomCombination[] = $room;
                    $persons -= $room['occupancy'];
                }
            }
        }

    } else if ($strategy == 'match_by_price') {
        usort($rooms, function ($a, $b) {
            return $a['price_total'] <=> $b['price_total'];
        });
        $max_runs = $persons;
        while ($persons > 0) {
            if ($max_runs-- < 0) {
                break;
            }
            foreach ($rooms as $room) {
                if ($room['occupancy'] <= $persons) {
                    $roomCombination[] = $room;
                    $persons -= $room['occupancy'];
                    break;
                }
            }
        }
    } else {
        throw new Exception('unknown strategy');
    }
    return $roomCombination;
}

$r = getCheapestRooms(4, $zimmer, 'match_by_price'); // match_by_price | match_by_occupancy
print_r($r);




