<?php

namespace Pressmind\Search;

class CalendarFilter
{
    public $id = null;
    public $id_booking_package = null;
    public $housing_package_code_ibe = null;
    public $occupancy = null;
    public $transport_type = null;
    public $duration = null;
    public $airport = null;
    public $startingpoint_id_city = null;
    public $housing_package_id_name = null;
    public $id_housing_package = null;
    public $agency = null;

    /**
     * @return bool
     */
    public function initFromGet()
    {
        $something_set = false;
        $params = [
            'id',
            'id_booking_package',
            'housing_package_code_ibe',
            'occupancy',
            'transport_type',
            'duration',
            'airport',
            'startingpoint_id_city',
            'housing_package_id_name',
            'id_housing_package',
            'agency'
        ];
        foreach ($params as $param) {
            if (isset($_GET[$param])) {
                $something_set = true;
                $this->$param = preg_replace('/[^a-zA-Z0-9_\-,]/', '', $_GET[$param]);
            }
        }
        return $something_set;
    }

    /**
     * Populate filter from an associative array (e.g. MCP tool arguments).
     * Same keys as initFromGet() / GET parameters.
     *
     * @param array<string, mixed> $params
     * @return bool True if at least one known parameter was set
     */
    public function initFromArray(array $params): bool
    {
        $something_set = false;
        $allowed = [
            'id',
            'id_booking_package',
            'housing_package_code_ibe',
            'occupancy',
            'transport_type',
            'duration',
            'airport',
            'startingpoint_id_city',
            'housing_package_id_name',
            'id_housing_package',
            'agency',
        ];
        foreach ($allowed as $param) {
            if (!array_key_exists($param, $params) || $params[$param] === null || $params[$param] === '') {
                continue;
            }
            $value = is_scalar($params[$param]) ? (string) $params[$param] : '';
            if ($value === '') {
                continue;
            }
            $this->$param = preg_replace('/[^a-zA-Z0-9_\-,]/', '', $value);
            $something_set = true;
        }
        return $something_set;
    }

}