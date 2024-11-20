<?php

namespace Pressmind\ORM\Object;
/**
 * Class Geodata
 * @property string $id
 * @property string $bundesland_name
 * @property string $bundesland_nutscode
 * @property string $regierungsbezirk_name
 * @property string $regierungsbezirk_nutscode
 * @property string $kreis_name
 * @property string $kreis_typ
 * @property string $kreis_nutscode
 * @property string $gemeinde_name
 * @property string $gemeinde_typ
 * @property string $gemeinde_ags
 * @property string $gemeinde_rs
 * @property float $gemeinde_lat
 * @property float $gemeinde_lon
 * @property string $ort_id
 * @property string $ort_name
 * @property float $ort_lat
 * @property float $ort_lon
 * @property string $postleitzahl
 * @property string $strasse_name
 *
 */
class Geodata extends AbstractObject
{
    protected $_definitions = [
        'class' => [
            'name' => self::class
        ],
        'database' => [
            'table_name' => 'pmt2core_geodata',
            'primary_key' => 'id',
        ],
        'properties' => [
            'id' => [
                'title' => 'Id',
                'name' => 'id',
                'type' => 'integer',
                'required' => true,
                'filters' => null
            ],
            'bundesland_name' => [
                'title' => 'bundesland_name',
                'name' => 'bundesland_name',
                'type' => 'varchar',
                'required' => false,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 30,
                    ]
                ],
                'filters' => null
            ],
            'bundesland_nutscode' => [
                'title' => 'bundesland_nutscode',
                'name' => 'bundesland_nutscode',
                'type' => 'varchar',
                'required' => false,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 3,
                    ]
                ],
                'filters' => null
            ],
            'regierungsbezirk_name' => [
                'title' => 'regierungsbezirk_name',
                'name' => 'regierungsbezirk_name',
                'type' => 'varchar',
                'required' => false,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 30,
                    ]
                ],
                'filters' => null
            ],
            'regierungsbezirk_nutscode' => [
                'title' => 'regierungsbezirk_nutscode',
                'name' => 'regierungsbezirk_nutscode',
                'type' => 'varchar',
                'required' => false,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 5,
                    ]
                ],
                'filters' => null
            ],
            'kreis_name' => [
                'title' => 'kreis_name',
                'name' => 'kreis_name',
                'type' => 'varchar',
                'required' => false,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 50,
                    ]
                ],
                'filters' => null
            ],
            'kreis_typ' => [
                'title' => 'kreis_typ',
                'name' => 'kreis_typ',
                'type' => 'varchar',
                'required' => false,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 40,
                    ]
                ],
                'filters' => null
            ],
            'kreis_nutscode' => [
                'title' => 'kreis_nutscode',
                'name' => 'kreis_nutscode',
                'type' => 'varchar',
                'required' => false,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 5,
                    ]
                ],
                'filters' => null
            ],
            'gemeinde_name' => [
                'title' => 'gemeinde_name',
                'name' => 'gemeinde_name',
                'type' => 'varchar',
                'required' => false,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 50,
                    ]
                ],
                'filters' => null
            ],
            'gemeinde_typ' => [
                'title' => 'gemeinde_typ',
                'name' => 'gemeinde_typ',
                'type' => 'varchar',
                'required' => false,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 40,
                    ]
                ],
                'filters' => null
            ],
            'gemeinde_ags' => [
                'title' => 'gemeinde_ags',
                'name' => 'gemeinde_ags',
                'type' => 'varchar',
                'required' => false,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 8,
                    ]
                ],
                'filters' => null
            ],
            'gemeinde_rs' => [
                'title' => 'gemeinde_rs',
                'name' => 'gemeinde_rs',
                'type' => 'varchar',
                'required' => false,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 20,
                    ]
                ],
                'filters' => null
            ],
            'gemeinde_lat' => [
                'title' => 'gemeinde_lat',
                'name' => 'gemeinde_lat',
                'type' => 'float',
                'required' => false,
                'validators' => null,
                'filters' => null
            ],
            'gemeinde_lon' => [
                'title' => 'gemeinde_lon',
                'name' => 'gemeinde_lon',
                'type' => 'float',
                'required' => false,
                'validators' => null,
                'filters' => null
            ],
            'ort_id' => [
                'title' => 'ort_id',
                'name' => 'ort_id',
                'type' => 'integer',
                'required' => false,
                'filters' => null
            ],
            'ort_name' => [
                'title' => 'ort_name',
                'name' => 'ort_name',
                'type' => 'varchar',
                'required' => false,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 80,
                    ]
                ],
                'filters' => null,
                'index' => [
                    'address' => 'fulltext'
                ]
            ],
            'ort_lat' => [
                'title' => 'ort_lat',
                'name' => 'ort_lat',
                'type' => 'float',
                'required' => false,
                'validators' => null,
                'filters' => null,
                'index' => [
                    'ort_lat' => 'index'
                ]
            ],
            'ort_lon' => [
                'title' => 'ort_lon',
                'name' => 'ort_lon',
                'type' => 'float',
                'required' => false,
                'validators' => null,
                'filters' => null,
                'index' => [
                    'ort_lon' => 'index'
                ]
            ],
            'postleitzahl' => [
                'title' => 'postleitzahl',
                'name' => 'postleitzahl',
                'type' => 'varchar',
                'required' => false,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 5,
                    ]
                ],
                'filters' => null,
                'index' => [
                    'postleitzahl' => 'index'
                ]
            ],
            'strasse_name' => [
                'title' => 'strasse_name',
                'name' => 'strasse_name',
                'type' => 'varchar',
                'required' => false,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 100,
                    ]
                ],
                'filters' => null,
                'index' => [
                    /*index name => index type */
                    'address' => 'fulltext',
                ]
            ],
        ],

    ];

    /**
     * @param $zip
     * @param int $radius
     * @return array
     * @throws \Exception
     */
    public function getZipsAroundZip($zip, $radius = 20){
        /**
         * @var Geodata[] $result
         */
        $result = $this->loadAll(['postleitzahl' => $zip], null, [0,1]);
        if(!empty($result)){
            return $this->getZipsAroundCoords($result[0]->gemeinde_lat, $result[0]->gemeinde_lon, $radius);
        }
        return [];
    }

    /**
     * @param $lat
     * @param $lon
     * @param int $radius
     * @return array
     */
    public function getZipsAroundCoords($lat, $lon, $radius = 20){
        $query = 'select postleitzahl, min(distance_in_km) as distance_in_km from (
                    SELECT postleitzahl,
                           p.distance_unit
                            * DEGREES(ACOS(COS(RADIANS(p.latpoint))
                            * COS(RADIANS(ort_lat))
                            * COS(RADIANS(p.longpoint) - RADIANS(ort_lon))
                            + SIN(RADIANS(p.latpoint))
                            * SIN(RADIANS(ort_lat)))) AS distance_in_km
                    FROM pmt2core_geodata AS a
                             JOIN (
                        SELECT '.$lat.' AS latpoint, '.$lon.' AS longpoint,'.$radius.' AS radius,  111.045 AS distance_unit
                    ) AS p ON 1=1
                    WHERE ort_lat
                        BETWEEN p.latpoint  - (p.radius / p.distance_unit)
                        AND p.latpoint  + (p.radius / p.distance_unit)
                        AND ort_lon
                        BETWEEN p.longpoint - (p.radius / (p.distance_unit * COS(RADIANS(p.latpoint))))
                        AND p.longpoint + (p.radius / (p.distance_unit * COS(RADIANS(p.latpoint))))
                    ) as n
                    group by postleitzahl
                    order by distance_in_km';
        $result = $this->_db->fetchAll($query);
        $output = [];
        foreach($result as $row){
            $output[$row->postleitzahl] = $row;
        }
        return $output;
    }

    /**
     * @param $zip
     * @return false|Geodata
     * @throws \Exception
     */
    public function getByZip($zip){
        if (!filter_var($zip, FILTER_VALIDATE_INT)) {
            return false;
        }
        $query = 'select * from pmt2core_geodata where postleitzahl = "'.$zip.'" limit 1';
        $result = $this->_db->fetchAll($query);
        if(!empty($result[0])){
            $Geodata = new Geodata();
            $Geodata->fromStdClass($result[0]);
            return $Geodata;
        }
        return false;
    }

    /**
     * Human friendly validation
     * @return array
     * @throws Exception
     */
    public static function validate($prefix = ''){
        $result = [];
        $Geodata = new Geodata();
        $r = $Geodata->getTableRowCount();
        if($r == 0){
            $result[] = $prefix . ' ❌ No geodata (zips, cities, streets) found, pls run the import script';
        }
        return $result;
    }


}
