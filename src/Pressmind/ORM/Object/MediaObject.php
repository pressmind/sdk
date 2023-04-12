<?php

namespace Pressmind\ORM\Object;

use DateTime;
use Exception;
use Pressmind\Log\Writer;
use Pressmind\ORM\Object\Itinerary\Step;
use Pressmind\ORM\Object\MediaType\AbstractMediaType;
use Pressmind\ORM\Object\MediaType\Factory;
use Pressmind\DB\Adapter\Pdo;
use Pressmind\HelperFunctions;
use Pressmind\MVC\View;
use Pressmind\ORM\Object\Itinerary\Variant;
use Pressmind\ORM\Object\MediaObject\MyContent;
use Pressmind\ORM\Object\Touristic\Base;
use Pressmind\ORM\Object\Touristic\Booking\Package;
use Pressmind\ORM\Object\Touristic\Date;
use Pressmind\ORM\Object\Touristic\EarlyBirdDiscountGroup\Item;
use Pressmind\ORM\Object\Touristic\Insurance\Group;
use Pressmind\ORM\Object\Touristic\Option;
use Pressmind\ORM\Object\Touristic\Transport;
use Pressmind\Registry;
use Pressmind\Search\CheapestPrice;
use Pressmind\Search\MongoDB\Calendar;
use Pressmind\Search\MongoDB\Indexer;
use Pressmind\System\Info;
use Pressmind\ValueObject\MediaObject\Result\GetByPrettyUrl;
use Pressmind\ValueObject\MediaObject\Result\GetPrettyUrls;

/**
 * Class MediaObject
 * @property integer $id
 * @property integer $id_pool
 * @property integer $id_brand
 * @property integer $id_object_type
 * @property integer $id_client
 * @property integer $id_season
 * @property integer $id_insurance_group
 * @property string $name
 * @property string $code
 * @property string $tags
 * @property integer $visibility
 * @property integer $state
 * @property DateTime $valid_from
 * @property DateTime $valid_to
 * @property integer $hidden
 * @property boolean $is_reference
 * @property integer $reference_media_object
 * @property DateTime $different_season_from
 * @property DateTime $different_season_to
 * @property float $recommendation_rate
 * @property string $booking_type
 * @property string $booking_link
 * @property string $sales_priority
 * @property integer $sales_position
 * @property float cheapest_price_total
 * @property AbstractMediaType[] $data
 * @property MyContent[] $my_contents
 * @property Base $touristic_base
 * @property Package[] $booking_packages
 * @property Group $insurance_group
 * @property Route[] $routes
 * @property Season $season
 * @property Brand $brand
 * @property Agency[] $agencies
 */
class MediaObject extends AbstractObject
{
    /**
     * @var Transport[]
     */
    private $_all_available_transports;

    /**
     * @var Date[]
     */
    private $_all_available_dates;

    /**
     * @var bool
     */
    protected $_dont_use_autoincrement_on_primary_key = true;

    /**
     * @var bool
     */
    protected $_use_cache = true;

    /**
     * @var array
     */
    protected $_definitions = [
        'class' => [
            'name' => self::class
        ],
        'database' => [
            'table_name' => 'pmt2core_media_objects',
            'primary_key' => 'id',
        ],
        'properties' => [
            'id' => [
                'title' => 'Id',
                'name' => 'id',
                'type' => 'integer',
                'required' => true,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 22,
                    ],
                    [
                        'name' => 'unsigned',
                        'params' => null,
                    ]
                ],
                'filters' => NULL,
            ],
            'id_pool' => [
                'title' => 'Id_pool',
                'name' => 'id_pool',
                'type' => 'integer',
                'required' => true,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 22,
                    ],
                    [
                        'name' => 'unsigned',
                        'params' => null,
                    ]
                ],
                'filters' => NULL,
                'index' => [
                    'id_pool' => 'index'
                ]
            ],
            'id_object_type' => [
                'title' => 'Id_object_type',
                'name' => 'id_object_type',
                'type' => 'integer',
                'required' => true,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 22,
                    ],
                    [
                        'name' => 'unsigned',
                        'params' => null,
                    ]
                ],
                'filters' => NULL,
                'index' => [
                    'id_object_type' => 'index'
                ]
            ],
            'id_client' => [
                'title' => 'Id_client',
                'name' => 'id_client',
                'type' => 'integer',
                'required' => true,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 22,
                    ],
                    [
                        'name' => 'unsigned',
                        'params' => null,
                    ]
                ],
                'filters' => NULL,
                'index' => [
                    'id_client' => 'index'
                ]
            ],
            'id_brand' => [
                'title' => 'id_brand',
                'name' => 'id_brand',
                'type' => 'integer',
                'required' => true,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 22,
                    ],
                    [
                        'name' => 'unsigned',
                        'params' => null,
                    ]
                ],
                'filters' => NULL,
                'index' => [
                    'id_brand' => 'index'
                ]
            ],
            'id_season' => [
                'title' => 'id_season',
                'name' => 'id_season',
                'type' => 'integer',
                'required' => true,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 22,
                    ],
                    [
                        'name' => 'unsigned',
                        'params' => null,
                    ]
                ],
                'filters' => NULL,
            ],
            'id_insurance_group' => [
                'title' => 'id_insurance_group',
                'name' => 'id_insurance_group',
                'type' => 'integer',
                'required' => false,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 22,
                    ],
                    [
                        'name' => 'unsigned',
                        'params' => null,
                    ]
                ],
                'filters' => NULL,
            ],
            'name' => [
                'title' => 'Name',
                'name' => 'name',
                'type' => 'string',
                'required' => false,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 255,
                    ],
                ],
                'filters' => NULL,
            ],
            'code' => [
                'title' => 'Code',
                'name' => 'code',
                'type' => 'string',
                'required' => false,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 255,
                    ],
                ],
                'filters' => NULL,
            ],
            'tags' => [
                'title' => 'Tags',
                'name' => 'tags',
                'type' => 'string',
                'required' => false,
                'validators' => NULL,
                'filters' => NULL,
            ],
            'visibility' => [
                'title' => 'Visibility',
                'name' => 'visibility',
                'type' => 'integer',
                'required' => true,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 11,
                    ],
                    [
                        'name' => 'unsigned',
                        'params' => null,
                    ]
                ],
                'filters' => NULL,
                'index' => [
                    'visibility' => 'index'
                ]
            ],
            'state' => [
                'title' => 'State',
                'name' => 'state',
                'type' => 'integer',
                'required' => true,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 11,
                    ],
                    [
                        'name' => 'unsigned',
                        'params' => null,
                    ]
                ],
                'filters' => NULL,
                'index' => [
                    'state' => 'index'
                ]
            ],
            'valid_from' => [
                'title' => 'Valid_from',
                'name' => 'valid_from',
                'type' => 'datetime',
                'required' => false,
                'validators' => NULL,
                'filters' => NULL,
                'index' => [
                    'valid_from' => 'index'
                ]
            ],
            'valid_to' => [
                'title' => 'Valid_to',
                'name' => 'valid_to',
                'type' => 'datetime',
                'required' => false,
                'validators' => NULL,
                'filters' => NULL,
                'index' => [
                    'valid_to' => 'index'
                ]
            ],
            'hidden' => [
                'title' => 'Hidden',
                'name' => 'hidden',
                'type' => 'boolean',
                'required' => true,
                'validators' => null,
                'filters' => NULL,
            ],
            'is_reference' => [
                'title' => 'is_reference',
                'name' => 'is_reference',
                'type' => 'boolean',
                'required' => false,
                'validators' => NULL,
                'filters' => NULL,
            ],
            'reference_media_object' => [
                'title' => 'reference_media_object',
                'name' => 'reference_media_object',
                'type' => 'integer',
                'required' => false,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 22,
                    ],
                    [
                        'name' => 'unsigned',
                        'params' => null,
                    ]
                ],
                'filters' => NULL,
                'index' => [
                    'reference_media_object' => 'index'
                ]
            ],
            'different_season_from' => [
                'title' => 'different_season_from',
                'name' => 'different_season_from',
                'type' => 'datetime',
                'required' => false,
                'validators' => NULL,
                'filters' => NULL,
            ],
            'different_season_to' => [
                'title' => 'different_season_to',
                'name' => 'different_season_to',
                'type' => 'datetime',
                'required' => false,
                'validators' => NULL,
                'filters' => NULL,
            ],
            'booking_type' => [
                'title' => 'booking_type',
                'name' => 'booking_type',
                'type' => 'string',
                'required' => false,
                'validators' => NULL,
                'filters' => NULL,
            ],
            'booking_link' => [
                'title' => 'booking_link',
                'name' => 'booking_link',
                'type' => 'string',
                'required' => false,
                'validators' => NULL,
                'filters' => NULL,
            ],
            'sales_priority' => [
                'title' => 'booking_type',
                'name' => 'booking_type',
                'type' => 'string',
                'required' => false,
                'validators' => NULL,
                'filters' => NULL,
            ],
            'sales_position' => [
                'title' => 'sales_position',
                'name' => 'sales_position',
                'type' => 'integer',
                'required' => false,
                'validators' => NULL,
                'filters' => NULL,
            ],
            'recommendation_rate' => [
                'title' => 'recommendation_rate',
                'name' => 'recommendation_rate',
                'type' => 'float',
                'required' => false,
                'validators' => NULL,
                'filters' => NULL,
            ],
            'cheapest_price_total' => [
                'title' => 'cheapest_price_total',
                'name' => 'cheapest_price_total',
                'type' => 'computed',
                'required' => false,
                'validators' => NULL,
                'filters' => NULL,
            ],
            'routes' => [
                'title' => 'routes',
                'name' => 'routes',
                'type' => 'relation',
                'relation' => [
                    'type' => 'hasMany',
                    'related_id' => 'id_media_object',
                    'class' => Route::class,
                ],
                'required' => false,
                'validators' => null,
                'filters' => null
            ],
            'data' => [
                'title' => 'data',
                'name' => 'data',
                'type' => 'relation',
                'relation' => [
                    'from_factory' => true,
                    'factory_parameters' => array(
                        'id_object_type'
                    ),
                    'factory_method' => 'createById',
                    'type' => 'hasMany',
                    'related_id' => 'id_media_object',
                    'class' => Factory::class,
                    'filters' => null
                ],
                'required' => false,
                'validators' => null,
                'filters' => null
            ],
            'my_contents' => [
                'title' => 'my_contents',
                'name' => 'my_contents',
                'type' => 'relation',
                'relation' => [
                    'type' => 'hasMany',
                    'related_id' => 'id_media_object',
                    'class' => MyContent::class,
                    'filters' => null
                ],
                'required' => false,
                'validators' => null,
                'filters' => null
            ],
            'touristic_base' => [
                'title' => 'touristic_base',
                'name' => 'touristic_base',
                'type' => 'relation',
                'relation' => [
                    'type' => 'belongsTo',
                    'related_id' => 'id_media_object',
                    'class' => Base::class,
                    'filters' => null
                ],
                'required' => false,
                'validators' => null,
                'filters' => null
            ],
            'booking_packages' => [
                'title' => 'booking_packages',
                'name' => 'booking_packages',
                'type' => 'relation',
                'relation' => [
                    'type' => 'hasMany',
                    'related_id' => 'id_media_object',
                    'class' => Package::class,
                    'filters' => null,
                    'prevent_auto_delete' => true
                ],
                'required' => false,
                'validators' => null,
                'filters' => null
            ],
            'season' => [
                'title' => 'season',
                'name' => 'season',
                'type' => 'relation',
                'relation' => [
                    'type' => 'hasOne',
                    'related_id' => 'id_season',
                    'class' => Season::class,
                    'filters' => null
                ],
                'required' => false,
                'validators' => null,
                'filters' => null
            ],
            'brand' => [
                'title' => 'brand',
                'name' => 'brand',
                'type' => 'relation',
                'relation' => [
                    'type' => 'hasOne',
                    'related_id' => 'id_brand',
                    'class' => Brand::class,
                    'filters' => null
                ],
                'required' => false,
                'validators' => null,
                'filters' => null
            ],
            'insurance_group' => [
                'title' => 'insurance_group',
                'name' => 'insurance_group',
                'type' => 'relation',
                'relation' => [
                    'type' => 'hasOne',
                    'related_id' => 'id_insurance_group',
                    'class' => Group::class,
                    'filters' => null
                ],
                'required' => false,
                'validators' => null,
                'filters' => null
            ],
            'agencies' => [
                'name' => 'agencies',
                'title' => 'agencies',
                'type' => 'relation',
                'required' => false,
                'validators' => NULL,
                'filters' => NULL,
                'relation' => [
                    'type' => 'ManyToMany',
                    'class' => Agency::class,
                    'relation_table' => 'pmt2core_agency_to_media_object',
                    'relation_class' => AgencyToMediaObject::class,
                    'related_id' => 'id_media_object',
                    'target_id' => 'id_agency'
                ]
            ]
        ]
    ];

    /**
     * @param string $name
     * @return mixed|string|void|null
     * @throws Exception
     */
    public function __get($name)
    {
        parent::__get($name);
        if($name == 'season' && !empty($this->different_season_from) && !empty($this->different_season_to)) {
            if(!is_null($this->season)) {
                $this->season->season_from = $this->different_season_from;
                $this->season->season_to = $this->different_season_to;
            } else {
                $this->season = new Season();
                $this->season->id = null;
                $this->season->name = 'Different Season';
                $this->season->season_from = $this->different_season_from;
                $this->season->season_to = $this->different_season_to;
                $this->season->active = 1;
                $this->season->time_of_year = 'all';
            }
        }
        return parent::__get($name);
    }

    /**
     * @param null $language
     * @return AbstractMediaType
     */
    public function getDataForLanguage($language = null) {
        $config = Registry::getInstance()->get('config');
        if(is_null($language)) {
            $language = $config['data']['languages']['default'];
        }
        return HelperFunctions::findObjectInArray($this->data, 'language', $language);
    }

    /**
     * @param string $template
     * @param string $language
     * @param object $custom_data
     * @return false|string
     * @throws Exception
     */
    public function render($template, $language = null, $custom_data = null) {
        $config = Registry::getInstance()->get('config');
        $media_type_name = ucfirst(HelperFunctions::human_to_machine($config['data']['media_types'][$this->id_object_type]));
        $media_object = $this;
        $script_path = $config['view_scripts']['base_path'] . DIRECTORY_SEPARATOR . ucfirst($media_type_name) . '_' . ucfirst($template);
        $view = new View($script_path);
        if($config['cache']['enabled'] && in_array('RENDERER', $config['cache']['types']) && $this->_use_cache) {
            $id = $this->getId();
            $key = $this->getDbTableName() . '_' . $id . '_' . $template;
            $cache_adapter = \Pressmind\Cache\Adapter\Factory::create(Registry::getInstance()->get('config')['cache']['adapter']['name']);
            Writer::write(get_class($this) . ' _readFromCache() reading from cache. ID: ' . $key, Writer::OUTPUT_FILE, strtolower(Registry::getInstance()->get('config')['cache']['adapter']['name']), Writer::TYPE_DEBUG);
            if ($cache_adapter->exists($key)) {
               $return = $cache_adapter->get($key);
            } else {
                $return = $view->render([
                    'media_object' => $media_object,
                    'custom_data' => $custom_data,
                    'language' => $language
                ]);
                $cache_adapter->add($key, $return);
            }
        } else {
            $return = $view->render([
                'media_object' => $media_object,
                'custom_data' => $custom_data,
                'language' => $language
            ]);
        }
        return $return;
    }

    /**
     * Convenient static function to get a MediaObject by code (as defined in pressmind)
     * @param string $code
     * @return array
     * @throws Exception
     */
    public static function getByCode($code)
    {
        $object = new self();
        return $object->loadAll('code = "' . $code.'"');
    }

    /**
     * @param null|CheapestPrice $filters
     * @return CheapestPriceSpeed
     * @throws Exception
     */
    public function getCheapestPrice($filters = null)
    {
        $CheapestPrice = $this->getCheapestPrices($filters, ['price_total' => 'ASC', 'duration' => 'DESC', 'date_departure' => 'ASC'], [0,1]);
        return empty($CheapestPrice[0]) ? null : $CheapestPrice[0];
    }

    /**
     * @param null|CheapestPrice $filters
     * @return CheapestPriceSpeed[]
     * @throws Exception
     */
    public function getCheapestPrices($filters = null, $order = ['price_total' => 'ASC', 'date_departure' => 'ASC'], $limit = null)
    {
        $now = new DateTime();
        $where = "id_media_object = " . $this->getId()." AND price_total > 0";
        $occupancy_filter_is_set = false;
        if(!is_null($filters)) {
            if(!empty($filters->duration_from) && !empty($filters->duration_to)) {
                $where .= ' AND duration BETWEEN ' . $filters->duration_from . ' AND ' . $filters->duration_to;
            }
            if(!empty($filters->date_from) && !empty($filters->date_to)) {
                $where .= " AND date_departure BETWEEN '" . $filters->date_from->format('Y-m-d 00:00:00') . "' AND '" . $filters->date_to->format('Y-m-d 23:59:59') . "'";
            }elseif(!empty($filters->date_from) && empty($filters->date_to)) {
                $where .= " AND date_departure = '" . $filters->date_from->format('Y-m-d 00:00:00') . "'";
            }else{
                $where .= " AND date_departure > '" . $now->format('Y-m-d 00:00:00') . "'";
            }
            if(!empty($filters->price_to)) {
                $filters->price_from = empty($filters->price_from) ? 0 : $filters->price_from;
                $where .= ' AND price_total BETWEEN ' . $filters->price_from . ' AND ' . $filters->price_to;
            }
            if(!empty($filters->occupancies)) {
                $where .= ' AND (';
                $im = [];
                foreach ($filters->occupancies as $occupancy) {
                    $im[] = '(' . $occupancy . ' BETWEEN option_occupancy_min AND option_occupancy_max) OR option_occupancy = ' . $occupancy;
                }
                $where .= implode(') OR (', $im) .  ')';
                $occupancy_filter_is_set = true;
            }
            if(!empty($filters->id_option)) {
                $where .= ' AND id_option = "' . $filters->id_option.'"';
            }
            if(!empty($filters->id_date)) {
                $where .= ' AND id_date = "' . $filters->id_date.'"';
            }
            if(!empty($filters->id_booking_package)) {
                $where .= ' AND id_booking_package = "' . $filters->id_booking_package.'"';
            }
            if(!empty($filters->id_housing_package)) {
                $where .= ' AND id_housing_package = "' . $filters->id_housing_package.'"';
            }
            if(!empty($filters->transport_types)) {
                if(is_string($filters->transport_types)){
                    $filters->transport_types = [$filters->transport_types];
                }
                $where .= ' AND transport_type in("' . implode('","',$filters->transport_types).'")';
            }
            if(!empty($filters->transport_1_airport)) {
                if(is_string($filters->transport_1_airport)){
                    $filters->transport_1_airport = [$filters->transport_1_airport];
                }
                $where .= ' AND transport_1_airport in("' . implode('","',$filters->transport_1_airport).'")';
            }
            if(!empty($filters->id)) {
                $where .= ' AND id = ' . $filters->id;
            }
        }

        if(!$occupancy_filter_is_set && isset($filters->occupancies_disable_fallback) && $filters->occupancies_disable_fallback === false) {
            $cheapest_prices = CheapestPriceSpeed::listAll($where . ' AND option_occupancy = 2', $order, $limit);
            if (empty($cheapest_prices)) {
                $cheapest_prices = CheapestPriceSpeed::listAll($where . ' AND option_occupancy = 1', $order, $limit);
            }
            if (empty($cheapest_prices)) {
                $cheapest_prices = CheapestPriceSpeed::listAll($where, $order, $limit);
            }
        } else {
            $cheapest_prices = CheapestPriceSpeed::listAll($where, $order, $limit);
        }
        return $cheapest_prices;
    }

    /**
     * @param null|CheapestPrice $filters
     * @return CheapestPriceSpeed[]
     * @throws Exception
     */
    public function getCheapestPricesOptions() {
        /** @var Pdo $db */
        $db = Registry::getInstance()->get('db');
        $now = new DateTime();
        $query = "SELECT duration, transport_type, transport_1_airport_name, transport_1_airport, option_occupancy,
                min(date_departure) AS date_departure_from,
                max(date_departure) AS date_departure_to,
                count(*) AS count
                FROM pmt2core_cheapest_price_speed
                WHERE id_media_object = " . $this->getId() . "
                AND price_total > 0
                AND date_departure > '" . $now->format('Y-m-d 00:00:00') . "'
                GROUP BY duration, transport_type, transport_1_airport_name, transport_1_airport, option_occupancy";
        $result = $db->fetchAll($query);
        $output = new \stdClass();
        $output->count = 0;
        foreach($result as $key => $row) {
            $output->duration[$row->duration] = $row->duration;
            $output->transport_type[$row->transport_type] = $row->transport_type;
            !empty($row->transport_1_airport) ? $output->transport_1_airport[$row->transport_1_airport] = $row->transport_1_airport : '';
            !empty($row->transport_1_airport_name) ? $output->transport_1_airport_name[$row->transport_1_airport_name] = $row->transport_1_airport_name : '';
            $output->date_departure_from[$row->date_departure_from] = $row->date_departure_from;
            $output->date_departure_to[$row->date_departure_to] = $row->date_departure_to;
            $output->option_occupancy[$row->option_occupancy] = $row->option_occupancy;
            $output->count += $row->count;
        }
        $output->option_occupancy = array_values($output->option_occupancy);
        $output->duration = array_values($output->duration);
        $output->transport_type = array_map('ucfirst', array_map('strtolower', array_values($output->transport_type)));
        !empty($row->transport_1_airport) ? $output->transport_1_airport = array_values($output->transport_1_airport) : '';
        !empty($row->transport_1_airport_name) ? $output->transport_1_airport_name = array_values($output->transport_1_airport_name) : '';
        $output->date_departure_from = array_values($output->date_departure_from);
        $output->date_departure_to = array_values($output->date_departure_to);

        return $output;
    }

    /**
     * @param \Pressmind\Search\CalendarFilter $filters
     * @param int $min_columns
     * @param int $origin
     * @param string $language
     * @return \stdClass
     * @throws Exception
     */
    public function getCalendar($filters, $min_columns = 3, $origin = 0, $language = null){
        $config = Registry::getInstance()->get('config');
        $collection = (new \MongoDB\Client($config['data']['search_mongodb']['database']['uri']))->{$config['data']['search_mongodb']['database']['db']}->{'calendar_' . (!empty($language) ? $language.'_' : '') . 'origin_' . $origin};
        $stages = [];
        $query['$match']['id_media_object'] = $this->getId();
        if(!empty($filters->occupancy)){ // Entfernen
            $query['$match']['occupancy'] = $filters->occupancy;
        }
        $stages[] = $query;
        $result = $collection->aggregate($stages)->toArray();
        $filter = [
            'transport_types' => [],
            'durations' => [],
            'id_housing_packages' => [],
            'airports' => []
        ];
        $documents = json_decode(json_encode($result), false);
        $filtered_documents = [];
        foreach($documents as $document){
            if(!empty($document->transport_type) && !isset($filter['transport_types'][$document->transport_type])){
                $filter['transport_types'][$document->transport_type] = ['durations' => [], 'airports' => [], 'id_housing_packages' => []];
            }
            if(!empty($document->booking_package->duration) && !in_array($document->booking_package->duration, $filter['transport_types'][$document->transport_type]['durations'])){
                $filter['transport_types'][$document->transport_type]['durations'][] = $document->booking_package->duration;
            }
            if(!empty($document->airport) && !in_array($document->airport, $filter['transport_types'][$document->transport_type]['airports'])){
                $filter['transport_types'][$document->transport_type]['airports'][] = $document->airport;
            }
            if(!empty($document->housing_package->id) && !in_array($document->housing_package->id, $filter['transport_types'][$document->transport_type]['id_housing_packages'])){
                $filter['transport_types'][$document->transport_type]['id_housing_packages'][] = $document->housing_package->id;
            }
            if(!empty($document->booking_package->duration) && !isset($filter['durations'][$document->booking_package->duration])){
                $filter['durations'][$document->booking_package->duration] = ['transport_types' => [], 'airports' => [], 'id_housing_packages' => []];
            }
            if(!empty($document->transport_type) && !in_array($document->transport_type, $filter['durations'][$document->booking_package->duration]['transport_types'])){
                $filter['durations'][$document->booking_package->duration]['transport_types'][] = $document->transport_type;
            }
            if(!empty($document->airport) && !in_array($document->airport, $filter['durations'][$document->booking_package->duration]['airports'])){
                $filter['durations'][$document->booking_package->duration]['airports'][] = $document->airport;
            }
            if(!empty($document->housing_package->id) && !in_array($document->housing_package->id, $filter['durations'][$document->booking_package->duration]['id_housing_packages'])){
                $filter['durations'][$document->booking_package->duration]['id_housing_packages'][] = $document->housing_package->id;
            }
            if(!empty($document->housing_package->id) && !isset($filter['id_housing_packages'][$document->housing_package->id])){
                $filter['id_housing_packages'][$document->housing_package->id] = ['durations' => [], 'transport_types' => [], 'airports' => []];
            }
            if(!empty($document->transport_type) && !in_array($document->transport_type, $filter['id_housing_packages'][$document->housing_package->id]['transport_types'])){
                $filter['id_housing_packages'][$document->housing_package->id]['transport_types'][] = $document->transport_type;
            }
            if(!empty($document->airport) && !in_array($document->airport, $filter['id_housing_packages'][$document->housing_package->id]['airports'])){
                $filter['id_housing_packages'][$document->housing_package->id]['airports'][] = $document->airport;
            }
            if(!empty($document->booking_package->duration) && !in_array($document->booking_package->duration, $filter['id_housing_packages'][$document->housing_package->id]['durations'])){
                $filter['id_housing_packages'][$document->housing_package->id]['durations'][] = $document->booking_package->duration;
            }
            if(!empty($document->airport) && !isset($filter['airports'][$document->airport])){
                $filter['airports'][$document->airport] = ['durations' => [], 'transport_types' => [], 'id_housing_packages' => []];
            }
            if(!empty($document->transport_type) && !empty($document->airport) && !in_array($document->transport_type, $filter['airports'][$document->airport]['transport_types'])){
                $filter['airports'][$document->airport]['transport_types'][] = $document->transport_type;
            }
            if(!empty($document->housing_package->id) && !empty($document->airport) && !in_array($document->housing_package->id, $filter['airports'][$document->airport]['id_housing_packages'])){
                $filter['airports'][$document->airport]['id_housing_packages'][] = $document->housing_package->id;
            }
            if(!empty($document->booking_package->duration) && !empty($document->airport) && !in_array($document->booking_package->duration, $filter['airports'][$document->airport]['durations'])){
                $filter['airports'][$document->airport]['durations'][] = $document->booking_package->duration;
            }
            if(
                (empty($filters->transport_type) || $filters->transport_type == $document->transport_type) &&
                (empty($filters->duration) || $filters->duration == $document->booking_package->duration) &&
                (empty($filters->id_housing_package) || $filters->id_housing_package == $document->housing_package->id) &&
                (empty($filters->airport) || $filters->airport == $document->airport) &&
                (empty($filters->housing_package_code_ibe) || $filters->housing_package_code_ibe == $document->housing_package->code_ibe)
            ){
               $filtered_documents[] = $document;
            }
        }
        $result = new \stdClass();
        $result->filter = $filter;
        $result->calendar = null;
        if(count($filtered_documents) == 0){
            return $result;
        }
        $result->calendar = $filtered_documents[0];
        $BookingPackage = new Package();
        $result->calendar->booking_package->created = null;
        $BookingPackage->fromStdClass($result->calendar->booking_package);
        $result->calendar->booking_package = $BookingPackage;
        $HousingPackage = new \Pressmind\ORM\Object\Touristic\Housing\Package();
        $HousingPackage->fromStdClass($result->calendar->housing_package);
        $result->calendar->housing_package = $HousingPackage;
        foreach($result->calendar->month as $k => $departure){
            foreach($departure->days as $k1 => $day){
                $result->calendar->month[$k]->days[$k1]->date = new \DateTime($day->date);
                if(isset($result->calendar->month[$k]->days[$k1]->cheapest_price)){
                    $CheapestPrice = new CheapestPriceSpeed();
                    $price = $result->calendar->month[$k]->days[$k1]->cheapest_price;
                    $price->earlybird_discount_date_to = !empty((array)$price->earlybird_discount_date_to) ? new \DateTime($price->earlybird_discount_date_to) : null;
                    $price->date_arrival = !empty((array)$price->date_arrival) ? new \DateTime($price->date_arrival) : null;
                    $price->date_departure = !empty((array)$price->date_departure) ? new \DateTime($price->date_departure) : null;
                    $CheapestPrice->fromStdClass($price);
                    $result->calendar->month[$k]->days[$k1]->cheapest_price = $CheapestPrice;
                }
            }
        }
        $from = clone $result->calendar->month[0]->days[0]->date;
        $to = clone $result->calendar->month[array_key_last($result->calendar->month)]->days[0]->date;
        if(count($result->calendar->month) < $min_columns){
            $add_months = $min_columns - count($result->calendar->month) + 1;
            $from->modify('+' . count($result->calendar->month) . ' month');
            $to->modify('+' . $add_months . ' month'); // +1?
            foreach (new \DatePeriod($from, new \DateInterval('P1M'), $to) as $dt) {
                $days = range(1, $dt->format('t'));
                $departure = new \stdClass();
                $departure->year = $dt->format('Y');
                $departure->month = $dt->format('m');
                $departure->is_bookable = false;
                $departure->days = [];
                foreach($days as $day){
                    $dayObj = new \stdClass();
                    $dayObj->date = new \DateTime($dt->format('Y-m-'.$day.' 00:00:00'));
                    $departure->days[] = $dayObj;
                }
                $result->calendar->month[] = $departure;
            }
        }
        return $result;
    }


    /**
     * @return string[]
     * @throws Exception
     */
    public function buildPrettyUrls($language = null)
    {

        // @TODO umbau auf mehrsprachigkeit.
        $config = Registry::getInstance()->get('config');
        $fields = $config['data']['media_types_pretty_url'][$this->id_object_type]['fields'] ?? ['name'];
        $separator = $config['data']['media_types_pretty_url'][$this->id_object_type]['separator'] ?? '-';
        $strategy = $config['data']['media_types_pretty_url'][$this->id_object_type]['strategy'] ?? 'unique';
        $url_array = [];
        foreach ($fields as $field) {
            if(in_array($field, $this->getPropertyNames())) {
                $url_array[] = strtolower(HelperFunctions::replaceLatinSpecialChars(trim($this->$field)));
            } else {
                $mo = new MediaObject($this->getId());
                $moc = $mo->getDataForLanguage($language);
                if($moc->getPropertyDefinition($field)['type'] == 'string') {
                    if(!empty($moc->$field)) {
                        $url_array[] = strtolower(HelperFunctions::replaceLatinSpecialChars(trim(strip_tags($moc->$field))));
                    }
                }
                /*if($object->getPropertyDefinition($field)['type'] == 'relation') {
                    $linked_object_field_name = $field['linked_object_field'];
                    $linked_objects = $object->$field_name;
                    if(!empty($object->$field_name)) {
                        if(is_array($linked_objects)) {
                            if(get_class($linked_objects[0]) == Objectlink::class) {
                                $object_link = new MediaObject($linked_objects[0]->id_media_object_link);
                                $url = strtolower(HelperFunctions::replaceLatinSpecialChars(trim($object_link->data[0]->$linked_object_field_name)));
                            }
                        } else {
                            if(get_class($linked_objects) == Objectlink::class) {
                                $object_link = new MediaObject($linked_objects->id_media_object_link);
                                $url = strtolower(HelperFunctions::replaceLatinSpecialChars(trim($object_link->data[0]->$linked_object_field_name)));
                            }
                        }
                    }
                }*/
            }
        }
        $url = implode($separator, $url_array);
        $prefix = $config['data']['media_types_pretty_url'][$this->id_object_type]['prefix'] ?? '/';
        $suffix = $config['data']['media_types_pretty_url'][$this->id_object_type]['suffix'] ?? '';
        $final_url = $prefix . trim(preg_replace('/\W+/', '-', $url), '-') . $suffix;
        if($strategy == 'unique' || $strategy == 'count-up') {
            if($this->_doesRouteExist($final_url)) {
                if($strategy == 'unique') {
                    throw new Exception('Route with url ' . $final_url . ' already exists and route-building strategy is set to unique in config. Please check your configuration file.');
                }
                if($strategy == 'count-up') {
                    for ($i = 1; $i < 1000; $i++) {
                        $check_url = $prefix . trim(preg_replace('/\W+/', '-', $url), '-') . '-' . str_pad($i, 3, '0', STR_PAD_LEFT) . $suffix;
                        if ($this->_doesRouteExist($check_url) == false) {
                            $final_url = $check_url;
                            break;
                        }
                    }
                }
            }
        }
        return [$final_url];
    }

    /**
     * @param $route
     * @return bool
     * @throws Exception
     */
    private function _doesRouteExist($route) {
        $existing_routes = Route::listAll(['route' => $route]);
        if(count($existing_routes) > 0) {
            return true;
        }
        return false;
    }

    /**
     * @return string|null
     */
    public function getPrettyUrl($language = null)
    {
        if(!empty($this->routes)) {
            $route = $this->routes[0]->route;
            if(!is_null($language)){
                foreach($this->routes as $v){
                    if($v->language == $language){
                        $route = '/' . $language.$v->route;
                        break;
                    }
                }
            }
            return $route;
        }
        return null;
    }

    /**
     * @return GetPrettyUrls[]
     */
    public function getPrettyUrls()
    {
        $config = Registry::getInstance()->get('config');
        $result = [];
        foreach ($this->routes as $route) {
            $object = new GetPrettyUrls();

            $object->id = $route->id;
            $object->id_media_object = $route->id_media_object;
            $object->id_object_type = $route->id_object_type;
            $object->route = $route->route;
            $object->language = $route->language;
            $object->is_default = $route->language == $config['data']['languages']['default'];

            $result[] = $object;
        }
        return $result;
    }

    /**
     * @param string $route
     * @param integer $id_object_type
     * @param null|string $language
     * @param null|integer $visibility
     * @return GetByPrettyUrl[]
     * @throws Exception
     */
    public static function getByPrettyUrl($route, $id_object_type = null, $language = null, $visibility = null)
    {
        if(Registry::getInstance()->get('config')['cache']['enabled'] && in_array('URL', Registry::getInstance()->get('config')['cache']['types'])){
            $key = 'URL:'.implode(':', array_filter([$id_object_type, $language, $visibility, str_replace('/', ':', trim($route,'/'))]));
            $cache_adapter = \Pressmind\Cache\Adapter\Factory::create(Registry::getInstance()->get('config')['cache']['adapter']['name']);
            if($cache_adapter->exists($key)) {
                return json_decode($cache_adapter->get($key));
            }
        }

        if(is_null($language)) {
            $config = Registry::getInstance()->get('config');
            $language = $config['data']['languages']['default'];
        }
        /** @var Pdo $db */
        $db = Registry::getInstance()->get('db');
        $sql = [];
        $values = [$route, $language];
        $sql[] = "SELECT p2cmo.id, p2cmo.id_object_type, p2cmo.visibility, '" . $language ."' as language FROM pmt2core_media_objects p2cmo INNER JOIN pmt2core_routes p2cr on p2cmo.id = p2cr.id_media_object WHERE";
        $sql[] = "p2cr.route = ?";
        $sql[] = "AND p2cr.language = ?";
        if(!is_null($id_object_type)) {
            $sql[] = "AND p2cr.id_object_type = ?";
            $values[] = $id_object_type;
        }
        if(!is_null($visibility)) {
            $sql[] = "AND p2cmo.visibility = ?";
            $values[] = $visibility;
        }
        $result = $db->fetchAll(implode(' ', $sql), $values, GetByPrettyUrl::class);
        if(Registry::getInstance()->get('config')['cache']['enabled'] && in_array('URL', Registry::getInstance()->get('config')['cache']['types'])) {
            $cache_adapter->add($key, json_encode($result), $result);
        }
        return $result;
    }

    /**
     * @return array
     * @throws Exception
     */
    public function insertCheapestPrice()
    {
        $max_rows = empty(Registry::getInstance()->get('config')['data']['touristic']['max_offers_per_product']) ? 5000 : Registry::getInstance()->get('config')['data']['touristic']['max_offers_per_product'];
        $CheapestPrice = new CheapestPriceSpeed();
        $CheapestPrice->deleteByMediaObjectId($this->getId());
        $booking_packages = $this->booking_packages;
        $now = new DateTime();
        $now->setTime(0,0,0);
        $c = 0;
        foreach ($booking_packages as $booking_package) {
            foreach ($booking_package->dates as $date) {
                if($date->departure < $now){ // don't index departures in the past
                    continue;
                }
                /** @var Item[] $early_bird_discounts */
                $early_bird_discounts = is_null($date->early_bird_discount_group) ? [null] : $date->early_bird_discount_group->items;

                /** @var Transport[] $transport_pairs */
                $transport_pairs = count($date->transports) > 0 ? $date->getTransportPairs([0,2,3], [], [], null, true) : [null];

                $options = [];
                if($booking_package->price_mix == 'date_housing') {
                    $options = $date->getHousingOptions([0,1,2,3], true);
                }
                if($booking_package->price_mix == 'date_sightseeing') {
                    $options = $date->getSightseeings(true);
                }
                if($booking_package->price_mix == 'date_extra') {
                    $options = $date->getExtras(true);
                }
                if($booking_package->price_mix == 'date_ticket') {
                    $options = $date->getTickets(true);
                }
                if($booking_package->price_mix == 'date_transport') {
                    $tmpOption = new Option();
                    $tmpOption->name = '';
                    $tmpOption->price = 0;
                    $options[] = $tmpOption;
                }
                
                $cheapest_options = [];
                $check_group_validity = [];
                $option_list = $date->getAllOptionsButExcludePriceMixOptions($booking_package->price_mix, true);
                foreach($option_list as $option){
                    $key = $option->type.'-'.$option->required_group;
                    if(!empty($option->required_group) && !empty($option->required)){
                        $check_group_validity[$key]['items_count'] = isset($check_group_validity[$key]['items_count']) ? $check_group_validity[$key]['items_count'] + 1 : 0;
                        if(in_array($option->state, [1,2,3])){
                            if(empty($cheapest_options[$key]->id) || empty($option->price) || $cheapest_options[$key]->price > $option->price){
                                $cheapest_options[$key] = $option;
                            }
                        }else{
                            $check_group_validity[$key]['items_count_not_valid'] = isset($check_group_validity[$key]['items_count_not_valid']) ? $check_group_validity[$key]['items_count_not_valid'] + 1 : 0;
                        }
                    }
                }

                /**
                 * @var Option[] $cheapest_options
                 */
                $cheapest_options = array_values($cheapest_options);
                foreach($check_group_validity as $k => $v){
                    if(isset($v['items_count_not_valid']) && ($v['items_count'] - $v['items_count_not_valid'] == 0)){
                        continue(2);
                    }
                }

                foreach ($options as $option) {
                    $housing_package = $option->getHousingPackage();
                    $included_options_price = 0;
                    $included_options_earlybird_price_base = 0;
                    $included_options_lowest_state = 3;
                    $included_options_description = [];
                    $id_included_options = [];
                    $code_ibe_included_options = [];
                    foreach($cheapest_options as $cheapest_option){
                        $cheapest_option_price = $cheapest_option->calculatePrice($booking_package->duration, $housing_package->nights);
                        $included_options_price += $cheapest_option_price;
                        if($option->use_earlybird){
                            $included_options_earlybird_price_base += $cheapest_option_price;
                        }
                        if($included_options_lowest_state > $cheapest_option->state){
                            $included_options_lowest_state = $cheapest_option->state;
                        }
                        $included_options_description[] = $cheapest_option->name;
                        $id_included_options[] = $cheapest_option->getId();
                        $code_ibe_included_options[] = $cheapest_option->code_ibe;
                    }
                    foreach ($transport_pairs as $transport_pair) {
                        $is_bookable = in_array($date->state, [1,4,0]);
                        $is_request = in_array($date->state, [2]);
                        $is_bookable = $is_bookable && in_array($option->state, [3,2]);
                        $is_request = $is_request || in_array($option->state, [1]);
                        if(count($id_included_options) == 1 && $cheapest_options[0]->state == 4){
                            $is_bookable = false;
                            $is_request = false;
                        }
                        if(count($id_included_options) == 1 && $cheapest_options[0]->state == 1){
                            $is_bookable = false;
                            $is_request = true;
                        }
                        if(!empty($transport_pair['way1']) && !empty($transport_pair['way2'])){
                            $is_bookable = $is_bookable && in_array($transport_pair['way1']->state, [3,0]);
                            $is_request = $is_request || in_array($transport_pair['way1']->state, [2]);
                            $is_bookable = $is_bookable && in_array($transport_pair['way2']->state, [3,0]);
                            $is_request = $is_request || in_array($transport_pair['way2']->state, [2]);
                        }
                        $transport_earlybird_price_base = 0;
                        foreach ($early_bird_discounts as $early_bird_discount) {
                            if (!is_null($transport_pair) && isset($transport_pair['way1'])) {
                                $transport_price = $transport_pair['way1']->price + (isset($transport_pair['way2']) ? $transport_pair['way2']->price : 0);
                                if($transport_pair['way1']->use_earlybird){
                                    $transport_earlybird_price_base = $transport_pair['way1']->price;
                                }
                                if(isset($transport_pair['way2']) && $transport_pair['way2']->use_earlybird){
                                    $transport_earlybird_price_base += $transport_pair['way2']->price;
                                }
                            } else {
                                $transport_price = 0;
                            }

                            // zero prices are not allowed in primary options
                            if(($booking_package->price_mix == 'date_transport' && empty($transport_price)) ||
                                ($booking_package->price_mix != 'date_transport' && empty($option->price))
                            ){
                                continue;
                            }
                            $price = $option->price + $transport_price + $included_options_price;
                            $price_base_early_bird = ($option->use_earlybird ? $option->price : 0) + $transport_earlybird_price_base + $included_options_earlybird_price_base;
                            if($price <= 0){
                                continue;
                            }
                            $cheapestPriceSpeed = new CheapestPriceSpeed();
                            $cheapestPriceSpeed->id_media_object = $this->getId();
                            $cheapestPriceSpeed->id_booking_package = $booking_package->getId();
                            $cheapestPriceSpeed->id_housing_package = $option->id_housing_package;
                            $cheapestPriceSpeed->id_date = $date->getId();
                            $cheapestPriceSpeed->id_option = $option->getId();
                            $cheapestPriceSpeed->id_transport_1 = !is_null($transport_pair) && isset($transport_pair['way1']) ? $transport_pair['way1']->id : null;
                            $cheapestPriceSpeed->id_transport_2 = !is_null($transport_pair) && isset($transport_pair['way1']) && isset($transport_pair['way2']) ? $transport_pair['way2']->id : null;
                            $cheapestPriceSpeed->duration = $booking_package->duration;
                            $cheapestPriceSpeed->date_departure = $date->departure;
                            $cheapestPriceSpeed->date_arrival = $date->arrival;
                            $cheapestPriceSpeed->option_name = $option->name;
                            $cheapestPriceSpeed->option_code = $option->code;
                            $cheapestPriceSpeed->option_board_type = $option->board_type;
                            $cheapestPriceSpeed->option_occupancy = $option->occupancy;
                            $cheapestPriceSpeed->option_occupancy_min = empty($option->occupancy_min) ? $option->occupancy : $option->occupancy_min;
                            $cheapestPriceSpeed->option_occupancy_max = empty($option->occupancy_max) ? $option->occupancy : $option->occupancy_max;
                            $cheapestPriceSpeed->price_transport_total = $transport_price;
                            $cheapestPriceSpeed->price_transport_1 = !is_null($transport_pair) && isset($transport_pair['way1']) ? $transport_pair['way1']->price : null;
                            $cheapestPriceSpeed->price_transport_2 = !is_null($transport_pair) && isset($transport_pair['way1']) && isset($transport_pair['way2']) ? $transport_pair['way2']->price : null;
                            $cheapestPriceSpeed->price_mix = $booking_package->price_mix;
                            $cheapestPriceSpeed->price_option = $option->price;
                            $cheapestPriceSpeed->price_option_pseudo = $option->price_pseudo;
                            $cheapestPriceSpeed->option_price_due = $option->price_due;
                            $cheapestPriceSpeed->price_regular_before_discount = $price;
                            $cheapestPriceSpeed->transport_code = !is_null($transport_pair) && isset($transport_pair['way1']) ? $transport_pair['way1']->code : null;
                            $cheapestPriceSpeed->transport_type = !is_null($transport_pair) && isset($transport_pair['way1']) ? $transport_pair['way1']->type : null;
                            $cheapestPriceSpeed->transport_1_way = !is_null($transport_pair) && isset($transport_pair['way1']) ? $transport_pair['way1']->way : null;
                            $cheapestPriceSpeed->transport_2_way = !is_null($transport_pair) && isset($transport_pair['way1']) && isset($transport_pair['way2']) ? $transport_pair['way2']->way : null;
                            $cheapestPriceSpeed->transport_1_description = !is_null($transport_pair) && isset($transport_pair['way1']) ? $transport_pair['way1']->description : null;
                            $cheapestPriceSpeed->transport_2_description = !is_null($transport_pair) && isset($transport_pair['way1']) && isset($transport_pair['way2']) ? $transport_pair['way2']->description : null;
                            $cheapestPriceSpeed->transport_1_airline = !is_null($transport_pair) && isset($transport_pair['way1']) && isset($transport_pair['way2']) ? $transport_pair['way1']->airline : null;
                            $cheapestPriceSpeed->transport_2_airline = !is_null($transport_pair) && isset($transport_pair['way1']) && isset($transport_pair['way2']) ? $transport_pair['way2']->airline : null;
                            $cheapestPriceSpeed->transport_1_airport = !empty($cheapestPriceSpeed->transport_code) ? substr($cheapestPriceSpeed->transport_code, 0, 3) : null;
                            $cheapestPriceSpeed->transport_2_airport = !empty($cheapestPriceSpeed->transport_code) ? substr($cheapestPriceSpeed->transport_code, -3, 3) : null; // TODO is not often used and needs a rework
                            if(!empty($cheapestPriceSpeed->transport_1_airport)) {
                                $airport = Airport::getByIata($cheapestPriceSpeed->transport_1_airport);
                                $cheapestPriceSpeed->transport_1_airport_name = !empty($airport->name) ? $airport->name : null;
                            }
                            if(!empty($cheapestPriceSpeed->transport_2_airport)){
                                $airport = Airport::getByIata($cheapestPriceSpeed->transport_2_airport);
                                $cheapestPriceSpeed->transport_2_airport_name = !empty($airport->name) ? $airport->name : null;
                            }
                            $cheapestPriceSpeed->transport_1_flight = !is_null($transport_pair) && isset($transport_pair['way1']) && isset($transport_pair['way2']) ? $transport_pair['way1']->flight : null;
                            $cheapestPriceSpeed->transport_2_flight = !is_null($transport_pair) && isset($transport_pair['way1']) && isset($transport_pair['way2']) ? $transport_pair['way2']->flight : null;
                            $cheapestPriceSpeed->transport_1_date_from = !is_null($transport_pair) && isset($transport_pair['way1']) && isset($transport_pair['way2']) ? $transport_pair['way1']->transport_date_from : null;
                            $cheapestPriceSpeed->transport_1_date_to = !is_null($transport_pair) && isset($transport_pair['way1']) && isset($transport_pair['way2']) ? $transport_pair['way1']->transport_date_to : null;
                            $cheapestPriceSpeed->transport_2_date_from = !is_null($transport_pair) && isset($transport_pair['way1']) && isset($transport_pair['way2']) ? $transport_pair['way2']->transport_date_from : null;
                            $cheapestPriceSpeed->transport_2_date_to = !is_null($transport_pair) && isset($transport_pair['way1']) && isset($transport_pair['way2']) ? $transport_pair['way2']->transport_date_to : null;
                            if($is_bookable){
                                $cheapestPriceSpeed->state = 3;
                            }
                            if($is_request){
                                $cheapestPriceSpeed->state = 1;
                            }
                            if(!$is_bookable && !$is_request){
                                $cheapestPriceSpeed->state = 5;
                            }
                            $cheapestPriceSpeed->infotext = null;
                            $cheapestPriceSpeed->id_option_auto_book = null;
                            $cheapestPriceSpeed->id_option_required_group = null;  // @TODO deprecated
                            $cheapestPriceSpeed->included_options_price = $included_options_price;
                            $cheapestPriceSpeed->included_options_description = implode(',', $included_options_description);
                            $cheapestPriceSpeed->id_included_options = implode(',', $id_included_options);
                            $cheapestPriceSpeed->code_ibe_included_options = implode(',', $code_ibe_included_options);
                            $cheapestPriceSpeed->id_start_point_option = null;
                            $cheapestPriceSpeed->id_origin = $booking_package->id_origin;
                            $cheapestPriceSpeed->id_startingpoint = null;
                            $cheapestPriceSpeed->price_total = $cheapestPriceSpeed->price_regular_before_discount;
                            $cheapestPriceSpeed->earlybird_discount = null;
                            $cheapestPriceSpeed->earlybird_discount_date_to = null;
                            $cheapestPriceSpeed->earlybird_discount_f = null;
                            if($this->_checkEarlyBirdDiscount($early_bird_discount, $date)) {
                                $cheapestPriceSpeed->earlybird_discount = strtolower($early_bird_discount->type) == 'p' ? $early_bird_discount->discount_value : null;
                                $cheapestPriceSpeed->earlybird_discount_date_to = $early_bird_discount->booking_date_to;
                                $cheapestPriceSpeed->earlybird_discount_f = strtolower($early_bird_discount->type) == 'f' ? $early_bird_discount->discount_value : null;
                                $cheapestPriceSpeed->earlybird_name = empty($date->early_bird_discount_group->name) ? 'Frühbucher' : $date->early_bird_discount_group->name;
                                $cheapestPriceSpeed->price_total = $cheapestPriceSpeed->price_regular_before_discount + $this->_calculateEarlyBirdDiscount($early_bird_discount, $price_base_early_bird);
                            }

                            $cheapestPriceSpeed->date_code_ibe = $date->code_ibe;
                            $cheapestPriceSpeed->housing_package_code_ibe = !empty($housing_package) ? $housing_package->code_ibe : null;
                            $cheapestPriceSpeed->housing_package_name = !empty($housing_package) ? $housing_package->name : null;
                            $cheapestPriceSpeed->housing_package_code = !empty($housing_package) ? $housing_package->code : null;
                            $cheapestPriceSpeed->option_code_ibe = $option->code_ibe;
                            $cheapestPriceSpeed->option_code_ibe_board_type = $option->code_ibe_board_type;
                            $cheapestPriceSpeed->option_code_ibe_board_type_category = $option->code_ibe_board_type_category;
                            $cheapestPriceSpeed->option_code_ibe_category = $option->code_ibe_category;
                            $cheapestPriceSpeed->option_request_code = $option->request_code;
                            $cheapestPriceSpeed->transport_1_code_ibe = !is_null($transport_pair) && isset($transport_pair['way1']) ? $transport_pair['way1']->code_ibe : null;
                            $cheapestPriceSpeed->transport_2_code_ibe = !is_null($transport_pair) && isset($transport_pair['way1']) && isset($transport_pair['way2']) ? $transport_pair['way2']->code_ibe : null;
                            $cheapestPriceSpeed->startingpoint_code_ibe = null;
                            $cheapestPriceSpeed->booking_package_ibe_type = $booking_package->ibe_type;
                            $cheapestPriceSpeed->booking_package_product_type_ibe = $booking_package->product_type_ibe;
                            $cheapestPriceSpeed->booking_package_type_of_travel = $booking_package->type_of_travel;
                            $cheapestPriceSpeed->booking_package_variant_code = $booking_package->variant_code;
                            $cheapestPriceSpeed->booking_package_request_code = $booking_package->request_code;
                            $cheapestPriceSpeed->booking_package_name = $booking_package->name;
                            $cheapestPriceSpeed->is_virtual_created_price = $booking_package->is_virtual_created_price;
                            $cheapestPriceSpeed->create();
                            unset($cheapestPriceSpeed);
                            $c++;
                            if($c == $max_rows){
                                break(5);
                            }
                        }
                    }
                }

            }
        }
        $config = Registry::getInstance()->get('config');
        if(!empty($config['data']['touristic']['generate_single_room_index'])) {
            $cheapestPriceSpeed = new CheapestPriceSpeed();
            $cheapestPriceSpeed->generateSingleRoomIndex($this->getId());
        }
    }

    /**
     * @param Item $discount
     * @param Date $date
     * @return false
     */
    private function _checkEarlyBirdDiscount($discount, $date) {
        $now = new DateTime();
        $now->setTime(0,0,0);
        if(!is_null($discount) &&
            (($now >= $discount->booking_date_from || is_null($discount->booking_date_from)) && $now <= $discount->booking_date_to) &&
            ($date->departure >= $discount->travel_date_from && $date->departure <= $discount->travel_date_to)) {
            return true;
        }
        return false;
    }

    /**
     * @param Item $discount
     * @param float $total_price
     * @return float|null
     */
    private function _calculateEarlyBirdDiscount($discount, $total_price) {
        switch (strtolower($discount->type)) {
            case 'p':
                return $this->_calculatePercentageEarlyBirdDiscount($discount, $total_price);
            case 'f':
                return $this->_calculateFixedEarlyBirdDiscount($discount);
        }
        return null;
    }

    /**
     * @param Item[] $discounts
     * @param Date $date
     * @return Item|null
     */
    public function getEarlyBirdDiscount($discounts, $date){
        foreach($discounts as $discount){
            if($this->_checkEarlyBirdDiscount($discount, $date)){
                return $discount;
            }
        }
        return null;
    }

    /**
     * @param Item $discount
     * @return float
     */
    private function _calculateFixedEarlyBirdDiscount($discount)
    {
        return ($discount->discount_value) * -1;
    }

    /**
     * @param Item $discount
     * @param float $total_price
     * @return float
     */
    private function _calculatePercentageEarlyBirdDiscount($discount, $total_price)
    {
        return bcmul(bcdiv($total_price, 100, 4), $discount->discount_value, 2) * -1;
    }

    /**
     * @return Date[]
     * @throws Exception
     */
    public function getAllAvailableDates()
    {
        if(empty($this->_all_available_dates)) {
            $now = new DateTime();
            $this->_all_available_dates = Date::listAll(['id_media_object' => $this->getId(), 'departure' => ['>=', $now->format('Y-m-d H:i:s')]]);
        }
        return $this->_all_available_dates;
    }

    /**
     * @return Transport[]
     * @throws Exception
     */
    public function getAllAvailableTransports()
    {
        if(empty($this->_all_available_transports)) {
            foreach ($this->getAllAvailableDates() as $date) {
                foreach ($date->transports as $transport) {
                    if ($transport->way == 1) {
                        $this->_all_available_transports[] = $transport;
                    }
                }
            }
        }
        return $this->_all_available_transports;
    }

    /**
     * @param string $tagName
     * @param string|null $language
     * @return mixed|null
     * @throws Exception
     */
    public function getValueByTagName($tagName, $language = null) {
        /**@var ObjectdataTag[] $possible_columns**/
        $possible_columns = ObjectdataTag::listAll(['id_object_type' => $this->id_object_type, 'tag_name' => $tagName]);
        if(count($possible_columns) > 0) {
            $data = $this->getDataForLanguage($language);
            $column_name = $possible_columns[0]->objectdata_column_name;
            $v = $data->$column_name;
            if(is_array($v) && !empty($v[0]->link_type) && $v[0]->link_type == 'objectlink'){
                $tmp = new \Pressmind\ORM\Object\MediaObject($v[0]->id_media_object_link);
                $v = $tmp->getValueByTagName($tagName, $language);
            }
            return($v);
        }
        return null;
    }


    /**
     * @param boolean $deleteRelations
     * @throws Exception
     */
    /*
   public function delete($deleteRelations = false)
   {
       $config = Registry::getInstance()->get('config');
       $this->_db->delete($this->getDbTableName(), [$this->getDbPrimaryKey() . " = ?", $this->getId()]);
       if(true === $deleteRelations){
           $this->_deleteRelations();
       }
       if($config['cache']['enabled'] === true) {
           $this->removeFromCache();
       }
       if($config['data']['search_mongodb']['enabled'] === true) {
           $this->deleteMongoDBIndex();
           $this->deleteMongoDBCalendar();
       }
   }
   */

    /**
     * @throws Exception
     */
    public function deleteMongoDBIndex(){
        $config = Registry::getInstance()->get('config');
        if($config['data']['search_mongodb']['enabled'] === true) {
            $Indexer = new Indexer();
            $Indexer->deleteMediaObject($this->getId());
        }
    }

    /**
     * @throws Exception
     */
    public function createMongoDBIndex(){
        $config = Registry::getInstance()->get('config');
        if(isset($config['data']['search_mongodb']['enabled']) && $config['data']['search_mongodb']['enabled'] === true) {
            $Indexer = new Indexer();
            $Indexer->upsertMediaObject($this->getId());
        }
    }

    /**
     * @throws Exception
     */
    public function createMongoDBCalendar(){
        $config = Registry::getInstance()->get('config');
        if(isset($config['data']['search_mongodb']['enabled']) && $config['data']['search_mongodb']['enabled'] === true) {
            $Calendar = new Calendar();
            $Calendar->upsertMediaObject($this->getId());
        }
    }

    /**
     * @throws Exception
     */
    public function deleteMongoDBCalendar(){
        $config = Registry::getInstance()->get('config');
        if($config['data']['search_mongodb']['enabled'] === true) {
            $Calendar = new Calendar();
            $Calendar->deleteMediaObject($this->getId());
        }
    }

    /**
     * @return void
     */
    public function createSearchIndex()
    {
        $config = Registry::getInstance()->get('config');
        if(isset($config['data']['media_types_fulltext_index_fields'])) {
            $this->_db->delete('pmt2core_fulltext_search', ['id_media_object = ?', $this->getId()]);
            $complete_fulltext = [];
            $fulltext = [];
            foreach ($config['data']['languages']['allowed'] as $language) {
                $data = $this->getDataForLanguage($language);
                $complete_fulltext[$language] = [];
                $fulltext[] = [
                    'var_name' => 'code',
                    'language' => $language,
                    'id_media_object' => $this->getId(),
                    'fulltext_values' => $this->code
                ];
                if (in_array('code', $config['data']['media_types_fulltext_index_fields'][$this->id_object_type])) {
                    $complete_fulltext[$language][] = $this->code;
                }
                $fulltext[] = [
                    'var_name' => 'name',
                    'language' => $language,
                    'id_media_object' => $this->getId(),
                    'fulltext_values' => $this->name
                ];
                if (in_array('name', $config['data']['media_types_fulltext_index_fields'][$this->id_object_type])) {
                    $complete_fulltext[$language][] = $this->name;
                }
                $fulltext[] = [
                    'var_name' => 'tags',
                    'language' => $language,
                    'id_media_object' => $this->getId(),
                    'fulltext_values' => $this->tags
                ];
                if (in_array('tags', $config['data']['media_types_fulltext_index_fields'][$this->id_object_type])) {
                    $complete_fulltext[$language][] = $this->tags;
                }
                if(empty($data) || empty($data->getPropertyDefinitions())){
                    continue;
                }
                foreach ($data->getPropertyDefinitions() as $name => $definition) {
                    if(!empty($data->$name)) {
                        $add_to_complete_fulltext = in_array($name, $config['data']['media_types_fulltext_index_fields'][$this->id_object_type]);
                        if ($definition['type'] == 'string') {
                            $fulltext[] = [
                                'var_name' => $name,
                                'language' => $language,
                                'id_media_object' => $this->getId(),
                                'fulltext_values' => trim(preg_replace('/\s+/', ' ', strip_tags(str_replace('>', '> ', $data->$name))))
                            ];
                            if ($add_to_complete_fulltext) {
                                $complete_fulltext[$language][] = trim(preg_replace('/\s+/', ' ', strip_tags(str_replace('>', '> ', $data->$name))));
                            }
                        }
                        if ($definition['type'] == 'relation') {
                            $values = [];
                            if ($definition['relation']['class'] == '\\Pressmind\\ORM\\Object\\MediaObject\\DataType\\Categorytree') {
                                foreach ($data->$name as $tree) {
                                    $values[] = $tree->item->name;
                                }
                            }
                            if (count($values) > 0) {
                                $fulltext[] = [
                                    'var_name' => $name,
                                    'language' => $language,
                                    'id_media_object' => $this->getId(),
                                    'fulltext_values' => implode(' ', $values)
                                ];
                                if ($add_to_complete_fulltext) {
                                    $complete_fulltext[$language][] = implode(' ', $values);
                                }
                            }
                        }
                    }
                }
            }
            foreach ($complete_fulltext as $language => $values) {
                $fulltext[] = [
                    'var_name' => 'fulltext',
                    'language' => $language,
                    'id_media_object' => $this->getId(),
                    'fulltext_values' => implode(' ', $values)
                ];
            }
            foreach ($fulltext as $fulltext_data) {
                $this->_db->insert('pmt2core_fulltext_search', $fulltext_data, false);
            }
        }
    }

    /**
     * @param string|null $code
     * @param integer|null $duration
     * @param integer|null $id_booking_package
     * @param string|null $type
     * @return Variant[]
     * @throws Exception
     */
    public function getItineraryVariants($code = null, $duration = null, $id_booking_package = null, $type = null)
    {
        $filters = [
            'id_media_object' => $this->getId()
        ];
        if(!is_null($code)) {
            $filters['code'] = $code;
        }
        if(!is_null($duration)) {
            $filters['booking_package_duration'] = $duration;
        }
        if(!is_null($id_booking_package)) {
            $filters['id_booking_package'] = $id_booking_package;
        }
        if(!is_null($type)) {
            $filters['type'] = $type;
        }
        return Variant::listAll($filters);
    }

    /**
     * @return Step[]
     * @throws Exception
     */
    public function getItinerarySteps() {
        $filters = [
            'id_media_object' => $this->getId()
        ];
        return Step::listAll($filters, ['order' => 'asc']);
    }

    /**
     * @return bool
     */
    public function isAPrimaryType(){
        $config = Registry::getInstance()->get('config');
        return in_array($this->id_object_type, $config['data']['primary_media_type_ids']);
    }

    /**
     * @param $id_media_object
     * @return void
     * @throws Exception
     */
    public static function deleteTouristic($id_media_object){
        /** @var \Pressmind\DB\Adapter\Pdo $db */
        $db = Registry::getInstance()->get('db');
        $tables = [
            'pmt2core_touristic_booking_packages',
            'pmt2core_touristic_dates',
            'pmt2core_touristic_transports',
            'pmt2core_touristic_housing_packages',
            'pmt2core_touristic_housing_package_description_links',
            'pmt2core_touristic_option_descriptions',
            'pmt2core_touristic_options',
            'pmt2core_cheapest_price_speed',
        ];
        foreach ($tables as $table) {
            $db->delete($table, ['id_media_object = ?', $id_media_object]);
        }
    }
}
