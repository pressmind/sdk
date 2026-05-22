<?php

declare(strict_types=1);

namespace Pressmind\MCP\Service;

use DateTime;
use Exception;
use Pressmind\ORM\Object\CheapestPriceSpeed;
use Pressmind\ORM\Object\MediaObject;
use Pressmind\ORM\Object\Touristic\Option;
use Pressmind\ORM\Object\Touristic\Startingpoint;
use Pressmind\ORM\Object\Touristic\Startingpoint\Option as StartingpointOption;
use Pressmind\ORM\Object\Touristic\Startingpoint\Option\ZipRange;
use Pressmind\ORM\Object\Touristic\Transport as TouristicTransport;
use Pressmind\Search\CalendarFilter;
use Pressmind\Search\CheapestPrice;

/**
 * Product detail and calendar for MCP tools.
 */
class ProductService
{
    private string $siteUrl;

    private string $ibeUrl;

    /**
     * @param  string  $siteUrl  Public base URL of the website (e.g. https://example.com)
     * @param  string  $ibeUrl   Base URL of the IBE3 booking engine (e.g. https://buchung.example.com)
     */
    public function __construct(string $siteUrl = '', string $ibeUrl = '')
    {
        $this->siteUrl = rtrim($siteUrl, '/');
        $this->ibeUrl = rtrim($ibeUrl, '/');
    }

    /**
     * Load media object with relations and build a compact JSON-safe document.
     *
     * @return array<string, mixed>
     *
     * @throws Exception
     */
    public function fetchDetails(int $idMediaObject): array
    {
        $mo = new MediaObject($idMediaObject, true);
        if (! $mo->isValid()) {
            throw new Exception('Media object not found: ' . $idMediaObject);
        }

        $bookingUrl = null;
        try {
            $priceFilter = new CheapestPrice();
            $cheapest = $mo->getCheapestPrice($priceFilter);
            if ($cheapest !== null && ! $cheapest->is_virtual_created_price) {
                $bookingUrl = MediaObject::getBookingLink($cheapest, null, null, null, true);
            }
        } catch (Exception $e) {
            $bookingUrl = $mo->booking_link ?? null;
        }

        $descriptionParts = [];
        if (is_array($mo->data)) {
            foreach ($mo->data as $block) {
                if ($block === null) {
                    continue;
                }
                $normalized = json_decode(json_encode($block), true);
                if (is_array($normalized)) {
                    $descriptionParts[] = $normalized;
                }
            }
        }

        $name = $mo->name ?? '';
        $textParts = [];
        foreach ($descriptionParts as $part) {
            if (is_array($part)) {
                foreach (['headline', 'intro', 'body', 'description', 'text'] as $k) {
                    if (! empty($part[$k]) && is_string($part[$k])) {
                        $textParts[] = strip_tags($part[$k]);
                    }
                }
            }
        }
        $fullText = implode("\n\n", array_filter($textParts));
        if ($fullText === '') {
            $fullText = $name;
        }

        $prettyUrl = $mo->getPrettyUrl() ?? '';
        if ($prettyUrl !== '' && $this->siteUrl !== '') {
            $prettyUrl = $this->siteUrl . '/' . ltrim($prettyUrl, '/');
        }

        if ($bookingUrl !== null) {
            $bookingUrl = $this->ensureAbsoluteIbeUrl($bookingUrl);
        }

        return [
            'id' => (string) $mo->id,
            'title' => $name,
            'text' => mb_substr($fullText, 0, 12000),
            'url' => $prettyUrl,
            'metadata' => [
                'code' => $mo->code ?? null,
                'id_object_type' => $mo->id_object_type ?? null,
                'booking_type' => $mo->booking_type ?? null,
                'booking_link' => $mo->booking_link ?? null,
                'booking_url' => $bookingUrl,
                'description_blocks' => $descriptionParts,
                'recommendation_rate' => $mo->recommendation_rate ?? null,
            ],
        ];
    }

    /**
     * Calendar / date prices for a product (Mongo-backed).
     *
     * @param  array<string, mixed>  $calendarArgs  Passed to CalendarFilter::initFromArray; must include enough fields for a valid filter.
     * @return array<string, mixed> Decoded calendar payload (structure from MediaObject::getCalendar)
     *
     * @throws Exception
     */
    public function getCalendar(int $idMediaObject, array $calendarArgs = [], ?string $month = null): array
    {
        $mo = new MediaObject($idMediaObject, false);
        if (! $mo->isValid()) {
            throw new Exception('Media object not found: ' . $idMediaObject);
        }

        if (! empty($mo->touristic_base->booking_on_request)) {
            throw new Exception('Product is booking_on_request; calendar not available via MCP.');
        }

        $priceFilter = new CheapestPrice();
        $cheapest = $mo->getCheapestPrice($priceFilter);
        if ($cheapest === null) {
            throw new Exception('No cheapest price available for this product.');
        }
        if ($cheapest->is_virtual_created_price) {
            throw new Exception('Cheapest price is virtual; calendar not available.');
        }

        $calFilter = new CalendarFilter();
        $merged = $calendarArgs;
        if (! isset($merged['id'])) {
            $merged['id'] = (string) $cheapest->id;
        }
        if (! $calFilter->initFromArray($merged)) {
            throw new Exception(
                'Calendar filter incomplete. Provide calendar_* arguments matching CalendarFilter fields (id, id_booking_package, id_housing_package, occupancy, transport_type, duration, airport, startingpoint_id_city, agency, …).'
            );
        }

        $calFilter->occupancy = $cheapest->option_occupancy;

        $calendar = $mo->getCalendar($calFilter, 3, 0, null, []);

        $payload = json_decode(json_encode($calendar), true);
        if (! is_array($payload)) {
            $payload = ['raw' => 'Could not normalize calendar'];
        }

        if ($month !== null && preg_match('/^(\d{4})-(\d{2})$/', $month, $m)) {
            $payload = $this->filterCalendarByMonth($payload, (int) $m[1], (int) $m[2]);
        }

        return $payload;
    }

    /**
     * Price matrix: all cheapest_price_speed rows for a product with optional filters and per-row booking URLs.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     *
     * @throws Exception
     */
    public function getCheapestPricesMatrix(int $idMediaObject, array $params = []): array
    {
        $mo = new MediaObject($idMediaObject, false);
        if (! $mo->isValid()) {
            throw new Exception('Media object not found: ' . $idMediaObject);
        }

        $filter = $this->buildCheapestPriceFilter($params);
        $order = $this->mapCheapestPriceOrder($params['order'] ?? null);
        $limit = $this->normalizeLimit($params['limit'] ?? null);

        $rows = $mo->getCheapestPrices($filter, $order, [0, $limit]);

        $prices = [];
        foreach ($rows as $cp) {
            if (! $cp instanceof CheapestPriceSpeed) {
                continue;
            }
            if (! empty($cp->is_virtual_created_price)) {
                continue;
            }
            $prices[] = $this->mapCheapestPriceSpeedToRow($cp);
        }

        $filterOptions = $this->safeGetCheapestPricesOptions($mo);

        return [
            'id_media_object' => (string) $idMediaObject,
            'total' => count($prices),
            'filter_options' => $filterOptions,
            'prices' => $prices,
        ];
    }

    /**
     * Extras, tickets, sightseeing options with prices and required-group metadata.
     *
     * @return array<string, mixed>
     *
     * @throws Exception
     */
    public function getTouristicOptions(int $idMediaObject, ?string $idBookingPackage = null, ?string $typeFilter = null): array
    {
        $mo = new MediaObject($idMediaObject, true);
        if (! $mo->isValid()) {
            throw new Exception('Media object not found: ' . $idMediaObject);
        }

        $typesWanted = $this->normalizeTouristicTypeFilter($typeFilter);
        $optionsOut = [];
        $dedupeKeys = [];

        foreach (($mo->booking_packages ?? []) as $bp) {
            if ($bp === null) {
                continue;
            }
            if ($idBookingPackage !== null && $idBookingPackage !== '' && (string) $bp->id !== $idBookingPackage) {
                continue;
            }

            $collections = [];
            if (in_array('extra', $typesWanted, true)) {
                $collections = array_merge($collections, is_array($bp->extras) ? $bp->extras : []);
            }
            if (in_array('ticket', $typesWanted, true)) {
                $collections = array_merge($collections, is_array($bp->tickets) ? $bp->tickets : []);
            }
            if (in_array('sightseeing', $typesWanted, true)) {
                $collections = array_merge($collections, is_array($bp->sightseeings) ? $bp->sightseeings : []);
            }

            foreach ($collections as $opt) {
                if (! $opt instanceof Option) {
                    continue;
                }
                $dedupeKey = md5(
                    ($opt->name ?? '') . '|' . (string) ($opt->price ?? '') . '|' . ($opt->type ?? '') . '|' . ($opt->required_group ?? '') . '|' . (string) $bp->id
                );
                if (isset($dedupeKeys[$dedupeKey])) {
                    continue;
                }
                $dedupeKeys[$dedupeKey] = true;
                $optionsOut[] = $this->mapTouristicOptionToRow($opt, (string) $bp->id);
            }
        }

        $requiredGroups = $this->aggregateRequiredGroups($optionsOut);

        return [
            'id_media_object' => (string) $idMediaObject,
            'options' => $optionsOut,
            'required_groups' => $requiredGroups,
        ];
    }

    /**
     * Outbound transports (way=1) with starting point and boarding options (incl. pickup / PLZ ranges).
     *
     * Uses future departure dates from `MediaObject::getAllAvailableDates()` (same basis as `getAllAvailableTransports()`).
     *
     * @return array<string, mixed>
     *
     * @throws Exception
     */
    public function getStartingPoints(int $idMediaObject, ?string $idBookingPackage = null): array
    {
        $mo = new MediaObject($idMediaObject, true);
        if (! $mo->isValid()) {
            throw new Exception('Media object not found: ' . $idMediaObject);
        }

        $entries = [];
        foreach ($mo->getAllAvailableDates() as $date) {
            if ($idBookingPackage !== null && $idBookingPackage !== '' && (string) $date->id_booking_package !== $idBookingPackage) {
                continue;
            }
            foreach (($date->transports ?? []) as $transport) {
                if (! $transport instanceof TouristicTransport) {
                    continue;
                }
                if ((int) $transport->way !== 1) {
                    continue;
                }

                $sp = $transport->starting_points ?? null;
                $startingPointPayload = null;
                $optionsPayload = [];
                if ($sp instanceof Startingpoint) {
                    $startingPointPayload = [
                        'id' => (string) $sp->id,
                        'code' => $sp->code ?? null,
                        'name' => $sp->name ?? null,
                        'text' => $sp->text ?? null,
                    ];
                    foreach (is_array($sp->options) ? $sp->options : [] as $opt) {
                        if ($opt instanceof StartingpointOption) {
                            $optionsPayload[] = $this->mapStartingpointOptionToRow($opt);
                        }
                    }
                }

                $entries[] = [
                    'id_date' => isset($date->id) ? (string) $date->id : null,
                    'id_booking_package' => isset($date->id_booking_package) ? (string) $date->id_booking_package : null,
                    'transport' => [
                        'id' => (string) $transport->id,
                        'type' => $transport->type ?? null,
                        'way' => $transport->way !== null ? (int) $transport->way : null,
                        'code' => $transport->code ?? null,
                        'flight' => $transport->flight ?? null,
                        'dont_use_for_offers' => ! empty($transport->dont_use_for_offers),
                        'time_departure' => $this->serializeTimeValue($transport->time_departure ?? null),
                        'time_arrival' => $this->serializeTimeValue($transport->time_arrival ?? null),
                        'id_starting_point' => $transport->id_starting_point !== null && $transport->id_starting_point !== ''
                            ? (string) $transport->id_starting_point
                            : null,
                    ],
                    'starting_point' => $startingPointPayload,
                    'starting_point_options' => $optionsPayload,
                ];
            }
        }

        return [
            'id_media_object' => (string) $idMediaObject,
            'id_booking_package_filter' => $idBookingPackage !== null && $idBookingPackage !== '' ? $idBookingPackage : null,
            'entries' => $entries,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapStartingpointOptionToRow(StartingpointOption $o): array
    {
        $zipRanges = [];
        foreach (is_array($o->zip_ranges) ? $o->zip_ranges : [] as $zr) {
            if ($zr instanceof ZipRange) {
                $zipRanges[] = [
                    'id' => (string) $zr->id,
                    'from' => $zr->from ?? null,
                    'to' => $zr->to ?? null,
                ];
            }
        }

        return [
            'id' => (string) $o->id,
            'code' => $o->code ?? null,
            'name' => $o->name ?? null,
            'text' => $o->text ?? null,
            'zip' => $o->zip ?? null,
            'city' => $o->city ?? null,
            'street' => $o->street ?? null,
            'price' => $o->price !== null ? (float) $o->price : null,
            'base_price' => $o->base_price !== null ? (float) $o->base_price : null,
            'code_ibe' => $o->code_ibe ?? null,
            'lat' => $o->lat !== null ? (float) $o->lat : null,
            'lon' => $o->lon !== null ? (float) $o->lon : null,
            'entry' => ! empty($o->entry),
            'exit' => ! empty($o->exit),
            'start_time' => $this->serializeTimeValue($o->start_time ?? null),
            'with_start_time' => ! empty($o->with_start_time),
            'exit_time' => $this->serializeTimeValue($o->exit_time ?? null),
            'with_exit_time' => ! empty($o->with_exit_time),
            'is_pickup_service' => ! empty($o->is_pickup_service),
            'zip_ranges' => $zipRanges,
            'zip_validity_area' => $o->zip_validity_area ?? null,
            'pickup_service_street' => $o->pickup_service_street ?? null,
            'pickup_service_house_number' => $o->pickup_service_house_number ?? null,
            'code_pickup_service_destination' => $o->code_pickup_service_destination ?? null,
            'price_per_day' => ! empty($o->price_per_day),
            'order' => $o->order !== null ? (int) $o->order : null,
            'rail' => $o->rail ?? null,
            'transportation' => $o->transportation ?? null,
        ];
    }

    private function serializeTimeValue(mixed $v): ?string
    {
        if ($v instanceof DateTime) {
            return $v->format('H:i:s');
        }
        if (is_string($v) && $v !== '') {
            return $v;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function buildCheapestPriceFilter(array $params): CheapestPrice
    {
        $f = new CheapestPrice();
        $f->state = null;
        $f->occupancies_disable_fallback = true;

        if (isset($params['duration_from']) && $params['duration_from'] !== '' && $params['duration_from'] !== null) {
            $f->duration_from = (int) $params['duration_from'];
        }
        if (isset($params['duration_to']) && $params['duration_to'] !== '' && $params['duration_to'] !== null) {
            $f->duration_to = (int) $params['duration_to'];
        }

        if (! empty($params['date_from'])) {
            $df = DateTime::createFromFormat('Y-m-d', (string) $params['date_from']);
            if ($df instanceof DateTime) {
                $f->date_from = $df;
            }
        }
        if (! empty($params['date_to'])) {
            $dt = DateTime::createFromFormat('Y-m-d', (string) $params['date_to']);
            if ($dt instanceof DateTime) {
                $f->date_to = $dt;
            }
        }

        if (isset($params['price_max']) && $params['price_max'] !== null && $params['price_max'] !== '') {
            $f->price_to = (float) $params['price_max'];
            $f->price_from = isset($params['price_min']) && $params['price_min'] !== '' && $params['price_min'] !== null
                ? (float) $params['price_min']
                : 0.0;
        } elseif (isset($params['price_min']) && $params['price_min'] !== '' && $params['price_min'] !== null) {
            $f->price_from = (float) $params['price_min'];
        }

        if (isset($params['occupancy']) && $params['occupancy'] !== null && $params['occupancy'] !== '') {
            $f->occupancies = [(int) $params['occupancy']];
        }

        if (! empty($params['transport_type'])) {
            $f->transport_types = [(string) $params['transport_type']];
        }

        if (! empty($params['id_booking_package'])) {
            $f->id_booking_package = (string) $params['id_booking_package'];
        }
        if (! empty($params['id_housing_package'])) {
            $f->id_housing_package = (string) $params['id_housing_package'];
        }
        if (! empty($params['startingpoint_id_city'])) {
            $f->startingpoint_id_city = (string) $params['startingpoint_id_city'];
        }
        if (! empty($params['airport'])) {
            $f->transport_1_airport = [(string) $params['airport']];
        }

        return $f;
    }

    /**
     * @return array<string, string>
     */
    private function mapCheapestPriceOrder(?string $order): array
    {
        $o = $order !== null ? strtolower(trim($order)) : '';
        if ($o === 'date-asc' || $o === 'date_departure-asc') {
            return ['date_departure' => 'ASC', 'price_total' => 'ASC'];
        }
        if ($o === 'price-desc') {
            return ['price_total' => 'DESC', 'date_departure' => 'ASC'];
        }

        return ['price_total' => 'ASC', 'date_departure' => 'ASC'];
    }

    private function normalizeLimit(?int $limit): int
    {
        if ($limit === null || $limit < 1) {
            return 50;
        }

        return min(200, $limit);
    }

    /**
     * @return array<string, mixed>
     */
    private function mapCheapestPriceSpeedToRow(CheapestPriceSpeed $cp): array
    {
        $bookingUrl = MediaObject::getBookingLink($cp, null, null, null, true);
        $bookingUrl = $this->ensureAbsoluteIbeUrl($bookingUrl);

        return [
            'id' => (string) $cp->id,
            'id_booking_package' => $cp->id_booking_package ?? null,
            'id_housing_package' => $cp->id_housing_package ?? null,
            'id_date' => $cp->id_date ?? null,
            'id_option' => $cp->id_option ?? null,
            'price_total' => $cp->price_total !== null ? (float) $cp->price_total : null,
            'price_regular_before_discount' => $cp->price_regular_before_discount !== null ? (float) $cp->price_regular_before_discount : null,
            'duration' => $cp->duration !== null ? (float) $cp->duration : null,
            'date_departure' => $cp->date_departure instanceof DateTime ? $cp->date_departure->format('Y-m-d') : null,
            'date_arrival' => $cp->date_arrival instanceof DateTime ? $cp->date_arrival->format('Y-m-d') : null,
            'option_name' => $cp->option_name ?? null,
            'option_occupancy' => $cp->option_occupancy !== null ? (int) $cp->option_occupancy : null,
            'option_board_type' => $cp->option_board_type ?? null,
            'housing_package_name' => $cp->housing_package_name ?? null,
            'transport_type' => $cp->transport_type ?? null,
            'transport_1_airport' => $cp->transport_1_airport ?? null,
            'transport_1_airport_name' => $cp->transport_1_airport_name ?? null,
            'transport_1_description' => $cp->transport_1_description ?? null,
            'startingpoint_name' => $cp->startingpoint_name ?? null,
            'startingpoint_id_city' => $cp->startingpoint_id_city ?? null,
            'state' => $cp->state !== null ? (int) $cp->state : null,
            'included_options_description' => $cp->included_options_description ?? null,
            'booking_url' => $bookingUrl,
        ];
    }

    private function ensureAbsoluteIbeUrl(?string $bookingUrl): ?string
    {
        if ($bookingUrl === null || $bookingUrl === '') {
            return $bookingUrl;
        }
        if ($this->ibeUrl !== '' && ! str_starts_with($bookingUrl, 'http')) {
            return $this->ibeUrl . '/' . ltrim($bookingUrl, '/');
        }

        return $bookingUrl;
    }

    /**
     * @return array<string, mixed>
     */
    private function safeGetCheapestPricesOptions(MediaObject $mo): array
    {
        try {
            $fo = $mo->getCheapestPricesOptions();
        } catch (\Throwable $e) {
            return ['_error' => $e->getMessage()];
        }

        if (! is_object($fo)) {
            return ['_error' => 'getCheapestPricesOptions returned unexpected type'];
        }

        return [
            'durations' => isset($fo->duration) ? array_values((array) $fo->duration) : [],
            'transport_types' => isset($fo->transport_type) ? $fo->transport_type : [],
            'option_occupancy' => isset($fo->option_occupancy) ? $fo->option_occupancy : [],
            'transport_1_airport' => isset($fo->transport_1_airport) ? array_values((array) $fo->transport_1_airport) : [],
            'transport_1_airport_name' => isset($fo->transport_1_airport_name) ? array_values((array) $fo->transport_1_airport_name) : [],
            'date_departure_from' => isset($fo->date_departure_from) ? array_values((array) $fo->date_departure_from) : [],
            'date_departure_to' => isset($fo->date_departure_to) ? array_values((array) $fo->date_departure_to) : [],
            'count' => $fo->count ?? 0,
        ];
    }

    /**
     * @return list<string>
     */
    private function normalizeTouristicTypeFilter(?string $typeFilter): array
    {
        if ($typeFilter === null || $typeFilter === '') {
            return ['extra', 'ticket', 'sightseeing'];
        }
        $t = strtolower(trim($typeFilter));
        if (in_array($t, ['extra', 'ticket', 'sightseeing'], true)) {
            return [$t];
        }

        return ['extra', 'ticket', 'sightseeing'];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapTouristicOptionToRow(Option $o, string $idBookingPackage): array
    {
        return [
            'id' => (string) $o->id,
            'type' => $o->type ?? null,
            'name' => $o->name ?? null,
            'description_long' => $o->description_long ?? null,
            'price' => $o->price !== null ? (float) $o->price : null,
            'price_pseudo' => $o->price_pseudo !== null ? (float) $o->price_pseudo : null,
            'price_due' => $o->price_due ?? null,
            'currency' => $o->currency ?? null,
            'occupancy' => $o->occupancy !== null ? (int) $o->occupancy : null,
            'occupancy_min' => $o->occupancy_min !== null ? (int) $o->occupancy_min : null,
            'occupancy_max' => $o->occupancy_max !== null ? (int) $o->occupancy_max : null,
            'min_pax' => $o->min_pax !== null ? (int) $o->min_pax : null,
            'max_pax' => $o->max_pax !== null ? (int) $o->max_pax : null,
            'age_from' => $o->age_from !== null ? (int) $o->age_from : null,
            'age_to' => $o->age_to !== null ? (int) $o->age_to : null,
            'required' => ! empty($o->required),
            'required_group' => $o->required_group !== null && $o->required_group !== '' ? (string) $o->required_group : null,
            'selection_type' => $o->selection_type ?? null,
            'auto_book' => ! empty($o->auto_book),
            'booking_type' => $o->booking_type ?? null,
            'state' => $o->state !== null ? (int) $o->state : null,
            'code_ibe' => $o->code_ibe ?? null,
            'id_booking_package' => $idBookingPackage,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $optionsOut
     * @return array<string, array<string, mixed>>
     */
    private function aggregateRequiredGroups(array $optionsOut): array
    {
        $groups = [];
        foreach ($optionsOut as $row) {
            $rg = $row['required_group'] ?? null;
            if ($rg === null || $rg === '') {
                continue;
            }
            if (! isset($groups[$rg])) {
                $groups[$rg] = [
                    'selection_type' => $row['selection_type'] ?? null,
                    'option_ids' => [],
                ];
            }
            $groups[$rg]['option_ids'][] = $row['id'];
            if ($groups[$rg]['selection_type'] === null && isset($row['selection_type'])) {
                $groups[$rg]['selection_type'] = $row['selection_type'];
            }
        }
        foreach ($groups as $k => $g) {
            $groups[$k]['option_ids'] = array_values(array_unique($g['option_ids']));
        }

        return $groups;
    }

    /**
     * Best-effort month filter on normalized calendar array.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function filterCalendarByMonth(array $payload, int $year, int $month): array
    {
        // Structure varies; try common keys
        if (! empty($payload['dates']) && is_array($payload['dates'])) {
            $payload['dates'] = array_values(array_filter($payload['dates'], function ($row) use ($year, $month) {
                if (! is_array($row)) {
                    return true;
                }
                $d = $row['date_departure'] ?? $row['departure'] ?? $row['date'] ?? null;
                if (! is_string($d)) {
                    return true;
                }

                return str_starts_with($d, sprintf('%04d-%02d', $year, $month));
            }));
        }

        return $payload;
    }
}
