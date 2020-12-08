<?php


namespace Pressmind\ORM\Object;
/**
 * Class Geozip
 * @property integer id
 * @property string zip
 * @property string city
 * @property string state
 * @property string district
 * @property float geo_lon
 * @property float geo_lat
 * @property string country
 * @property float municipality_lat
 * @property float municipality_lon
 * @property string municipality_nutscode
 * @property string municipality_ags
 * @property string municipality_type
 * @property string municipality
 * @property string district_nutscode
 * @property string district_ags
 * @property string district_type
 * @property string state_nutscode
 * @property string state_ags
 * @property string street
 */
class Geozip extends AbstractObject
{
    protected $_definitions = [
        'class' => [
            'name' => 'Geozip',
        ],
        'database' => [
            'table_name' => 'pmt2core_geozip',
            'primary_key' => 'id',
        ],
        'properties' => [
            'id' => [
                'title' => 'id',
                'name' => 'id',
                'type' => 'integer',
                'required' => true,
                'filters' => null
            ],
            'zip' => [
                'title' => 'zip',
                'name' => 'zip',
                'type' => 'string',
                'required' => false,
                'filters' => null
            ],
            'city' => [
                'title' => 'city',
                'name' => 'city',
                'type' => 'string',
                'required' => false,
                'filters' => null
            ],
            'state' => [
                'title' => 'state',
                'name' => 'state',
                'type' => 'string',
                'required' => false,
                'filters' => null
            ],
            'district' => [
                'title' => 'district',
                'name' => 'district',
                'type' => 'string',
                'required' => false,
                'filters' => null
            ],
            'geo_lon' => [
                'title' => 'geo_lon',
                'name' => 'geo_lon',
                'type' => 'float',
                'required' => false,
                'filters' => null
            ],
            'geo_lat' => [
                'title' => 'geo_lat',
                'name' => 'geo_lat',
                'type' => 'float',
                'required' => false,
                'filters' => null
            ],
            'country' => [
                'title' => 'country',
                'name' => 'country',
                'type' => 'string',
                'required' => false,
                'filters' => null
            ],
            'municipality_lat' => [
                'title' => 'municipality_lat',
                'name' => 'municipality_lat',
                'type' => 'float',
                'required' => false,
                'filters' => null
            ],
            'municipality_lon' => [
                'title' => 'municipality_lon',
                'name' => 'municipality_lon',
                'type' => 'float',
                'required' => false,
                'filters' => null
            ],
            'municipality_nutscode' => [
                'title' => 'municipality_nutscode',
                'name' => 'municipality_nutscode',
                'type' => 'string',
                'required' => false,
                'filters' => null
            ],
            'municipality_ags' => [
                'title' => 'municipality_ags',
                'name' => 'municipality_ags',
                'type' => 'string',
                'required' => false,
                'filters' => null
            ],
            'municipality_type' => [
                'title' => 'municipality_type',
                'name' => 'municipality_type',
                'type' => 'string',
                'required' => false,
                'filters' => null
            ],
            'municipality' => [
                'title' => 'municipality',
                'name' => 'municipality',
                'type' => 'string',
                'required' => false,
                'filters' => null
            ],
            'district_nutscode' => [
                'title' => 'district_nutscode',
                'name' => 'district_nutscode',
                'type' => 'string',
                'required' => false,
                'filters' => null
            ],
            'district_ags' => [
                'title' => 'district_ags',
                'name' => 'district_ags',
                'type' => 'string',
                'required' => false,
                'filters' => null
            ],
            'district_type' => [
                'title' => 'district_type',
                'name' => 'district_type',
                'type' => 'string',
                'required' => false,
                'filters' => null
            ],
            'state_nutscode' => [
                'title' => 'state_nutscode',
                'name' => 'state_nutscode',
                'type' => 'string',
                'required' => false,
                'filters' => null
            ],
            'state_ags' => [
                'title' => 'state_ags',
                'name' => 'state_ags',
                'type' => 'string',
                'required' => false,
                'filters' => null
            ],
            'street' => [
                'title' => 'street',
                'name' => 'street',
                'type' => 'string',
                'required' => false,
                'filters' => null
            ],
        ]
    ];
}
