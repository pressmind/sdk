<?php

namespace Pressmind\Import;

/**
 * Normalizes external JSON feed data before ORM hydration.
 *
 * Automatically sets `id_media_object` and structural parent foreign keys
 * (id_booking_package, id_housing_package, id_date) on nested touristic entities
 * based on the object hierarchy. This reduces the data the external integrator
 * needs to provide — only entity IDs (primary keys) and reference FKs
 * (id_starting_point, id_insurance_group, etc.) must be supplied.
 *
 * Reference FKs that point to independently existing entities are NEVER modified:
 * id_starting_point, id_insurance_group, id_early_bird_discount_group,
 * id_early_payment_discount_group, id_pickupservice, id_touristic_option_discount, id_transport
 *
 * Usage:
 *   $normalized = FeedNormalizer::normalizeBookingPackages($feedData, $idMediaObject);
 *   foreach ($normalized as $bp) {
 *       $package = new Touristic\Booking\Package();
 *       $package->fromStdClass($bp);
 *       $package->create();
 *   }
 */
class FeedNormalizer
{
    /**
     * Normalizes an array of booking package stdClass objects.
     *
     * Sets on each entity:
     * - Booking Package: id_media_object
     * - Date: id_media_object, id_booking_package
     * - Transport: id_media_object, id_booking_package, id_date
     * - Housing Package: id_media_object, id_booking_package
     * - Housing Option: id_media_object, id_booking_package, id_housing_package, type
     * - Extras/Tickets/Sightseeings: id_media_object, id_booking_package, type
     *
     * @param array $bookingPackages Array of stdClass objects representing booking packages
     * @param int $idMediaObject The media object ID to propagate
     * @return array The normalized array of stdClass objects
     */
    public static function normalizeBookingPackages(array $bookingPackages, int $idMediaObject): array
    {
        foreach ($bookingPackages as $bp) {
            $bp->id_media_object = $idMediaObject;

            if (!empty($bp->dates) && is_array($bp->dates)) {
                foreach ($bp->dates as $date) {
                    $date->id_media_object = $idMediaObject;
                    $date->id_booking_package = $bp->id;

                    if (!empty($date->transports) && is_array($date->transports)) {
                        foreach ($date->transports as $transport) {
                            $transport->id_media_object = $idMediaObject;
                            $transport->id_booking_package = $bp->id;
                            $transport->id_date = $date->id;
                        }
                    }
                }
            }

            if (!empty($bp->housing_packages) && is_array($bp->housing_packages)) {
                foreach ($bp->housing_packages as $hp) {
                    $hp->id_media_object = $idMediaObject;
                    $hp->id_booking_package = $bp->id;

                    if (!empty($hp->options) && is_array($hp->options)) {
                        foreach ($hp->options as $option) {
                            $option->id_media_object = $idMediaObject;
                            $option->id_booking_package = $bp->id;
                            $option->id_housing_package = $hp->id;
                            if (empty($option->type)) {
                                $option->type = 'housing_option';
                            }
                        }
                    }
                }
            }

            $optionTypes = [
                'extras' => 'extra',
                'tickets' => 'ticket',
                'sightseeings' => 'sightseeing',
            ];
            foreach ($optionTypes as $property => $typeName) {
                if (!empty($bp->$property) && is_array($bp->$property)) {
                    foreach ($bp->$property as $option) {
                        $option->id_media_object = $idMediaObject;
                        $option->id_booking_package = $bp->id;
                        if (empty($option->type)) {
                            $option->type = $typeName;
                        }
                    }
                }
            }
        }

        return $bookingPackages;
    }

    /**
     * Normalizes an array of starting point stdClass objects.
     *
     * Sets id_startingpoint on each nested option and id_startingpoint_option
     * on each nested zip range.
     *
     * @param array $startingPoints Array of stdClass objects representing starting points
     * @return array The normalized array
     */
    public static function normalizeStartingPoints(array $startingPoints): array
    {
        foreach ($startingPoints as $sp) {
            if (!empty($sp->options) && is_array($sp->options)) {
                foreach ($sp->options as $option) {
                    $option->id_startingpoint = $sp->id;

                    if (!empty($option->zip_ranges) && is_array($option->zip_ranges)) {
                        foreach ($option->zip_ranges as $zipRange) {
                            if (isset($option->id)) {
                                $zipRange->id_startingpoint_option = $option->id;
                            }
                        }
                    }
                }
            }
        }

        return $startingPoints;
    }

    /**
     * Normalizes an array of early bird discount group stdClass objects.
     *
     * Sets id_early_bird_discount_group on each nested item.
     *
     * @param array $groups Array of stdClass objects representing early bird discount groups
     * @return array The normalized array
     */
    public static function normalizeEarlyBirdDiscountGroups(array $groups): array
    {
        foreach ($groups as $group) {
            if (!empty($group->items) && is_array($group->items)) {
                foreach ($group->items as $item) {
                    $item->id_early_bird_discount_group = $group->id;
                }
            }
        }

        return $groups;
    }
}
