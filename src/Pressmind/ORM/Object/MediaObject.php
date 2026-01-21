<?php

namespace Pressmind\ORM\Object;

use DateTime;
use Exception;
use Pressmind\Log\Writer;
use Pressmind\ORM\Object\Itinerary\Step;
use Pressmind\ORM\Object\MediaObject\ManualCheapestPrice;
use Pressmind\ORM\Object\MediaObject\ManualDiscount;
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
use Pressmind\ORM\Object\Touristic\EarlyBirdDiscountGroup;
use Pressmind\ORM\Object\Touristic\EarlyBirdDiscountGroup\Item;
use Pressmind\ORM\Object\Touristic\Insurance\Group;
use Pressmind\ORM\Object\Touristic\Option;
use Pressmind\ORM\Object\Touristic\Startingpoint;
use Pressmind\ORM\Object\Touristic\Transport;
use Pressmind\Registry;
use Pressmind\Search\CalendarFilter;
use Pressmind\Search\CheapestPrice;
use Pressmind\Search\MongoDB\Calendar;
use Pressmind\Search\MongoDB\Indexer;
use Pressmind\Search\Query;
use Pressmind\System\Info;
use Pressmind\ValueObject\MediaObject\Result\GetByPrettyUrl;
use Pressmind\ValueObject\MediaObject\Result\GetPrettyUrls;
use stdClass;

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
 * @property ManualCheapestPrice[] $manual_cheapest_prices
 * @property ManualDiscount[] $manual_discounts
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
     * @var Option[]
     */
    private $_all_available_options;

    /**
     * @var bool
     */
    protected $_dont_use_autoincrement_on_primary_key = true;

    /**
     * @var bool
     */
    protected $_use_cache = true;

    /**
     * Stores the runtime log by id_media_object
     * @var array
     */
    private static $_insert_cheapest_price_log = [];

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
            ],
            'manual_cheapest_prices' => [
                'title' => 'manual_cheapest_prices',
                'name' => 'manual_cheapest_prices',
                'type' => 'relation',
                'relation' => [
                    'type' => 'hasMany',
                    'related_id' => 'id_media_object',
                    'class' => ManualCheapestPrice::class,
                    'filters' => null
                ],
                'required' => false,
                'validators' => null,
                'filters' => null
            ],
            'manual_discounts' => [
                'title' => 'manual_discounts',
                'name' => 'manual_discounts',
                'type' => 'relation',
                'relation' => [
                    'type' => 'hasMany',
                    'related_id' => 'id_media_object',
                    'class' => ManualCheapestPrice::class,
                    'filters' => null
                ],
                'required' => false,
                'validators' => null,
                'filters' => null
            ],
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
        $state_filter_is_set = false;
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
                $where .= ' AND ((';
                $im = [];
                foreach ($filters->occupancies as $occupancy) {
                    $im[] = '(' . $occupancy . ' BETWEEN option_occupancy_min AND option_occupancy_max) OR option_occupancy = ' . $occupancy;
                }
                $where .= implode(') OR (', $im) .  '))';
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
            if(!empty($filters->housing_package_code_ibe)) {
                $where .= ' AND housing_package_code_ibe = "' . $filters->housing_package_code_ibe.'"';
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
            if(!empty($filters->origin)) {
                $where .= ' AND origin = ' . $filters->origin;
            }
            if(!empty($filters->agency)) {
                $where .= ' AND agency = ' . $filters->agency;
            }
            if(!empty($filters->state)) {
                $where .= ' AND state = '.$filters->state;
                $state_filter_is_set = true;
            }
            if(!empty($filters->id_startingpoint_option)) {
                $where .= ' AND id_startingpoint_option = "'.$filters->id_startingpoint_option.'"';
            }
            if(!empty($filters->startingpoint_option_name)) {
                $where .= ' AND startingpoint_option_name like "%'.$filters->startingpoint_option_name.'%"';
            }
            if(!empty($filters->startingpoint_id_city)) {
                $where .= ' AND startingpoint_id_city = "'.$filters->startingpoint_id_city.'"';
            }
            if(!empty($filters->housing_package_id_name)) {
                $where .= ' AND housing_package_id_name = "'.$filters->housing_package_id_name.'"';
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
        if(!empty($filters->state_fallback_order) && $state_filter_is_set === true && empty($cheapest_prices)) {
            $fallback_filter = clone $filters;
            $fallback_filter->state_fallback_order = array_slice($filters->state_fallback_order, (int)array_search($filters->state, $filters->state_fallback_order) + 1);
            if(!empty($fallback_filter->state_fallback_order[0])){
                $fallback_filter->state = $fallback_filter->state_fallback_order[0];
                $cheapest_prices = $this->getCheapestPrices($fallback_filter, $order, $limit);
            }
        }
        return $cheapest_prices;
    }

    /**
     * @param null|CheapestPrice $filters
     * @return CheapestPriceSpeed[]
     * @throws Exception
     */
    public function getCheapestPricesOptions($origin = null, $agency = null){
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
                " . (!empty($origin) ? ' AND origin = ' . $origin : '') . "
                " . (!empty($agency) ? ' AND agency = "' . $agency .'"': '') . "
                GROUP BY duration, transport_type, transport_1_airport_name, transport_1_airport, option_occupancy";
        $result = $db->fetchAll($query);
        $output = new stdClass();
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
     * @param array $custom_query
     * @return stdClass
     * @throws Exception
     */
    public function getCalendar($filters, $min_columns = 3, $origin = 0, $language = null, $custom_query = [])
    {
        $config = Registry::getInstance()->get('config');
        $collection_name = (new Calendar())->getCollectionName($origin, $language, !is_null($filters) ? $filters->agency : null);
        $collection = (new \MongoDB\Client($config['data']['search_mongodb']['database']['uri']))->{$config['data']['search_mongodb']['database']['db']}->{$collection_name};
        $stages = [];
        $query['$match']['id_media_object'] = $this->getId();
        $stages[] = $query;
        if(!empty($custom_query['transport_type'])){
            $query['$match']['transport_type'] = $custom_query['transport_type'];
            $stages[] = $query;
        }
        $result = $collection->aggregate($stages)->toArray();
        $filter = [
            'transport_types' => [],
            'durations' => [],
            'airports' => [],
            'occupancies' => [],
            'startingpoint_id_cities' => [],
            'housing_package_id_names' => [],
            'id_housing_packages' => []
        ];
        $documents = json_decode(json_encode($result), false);
        $filtered_documents = [];

        // Build a mapping from housing_package_id_name to id_housing_package using Housing\Package table
        $housingPackageIdMap = [];
        $HousingPackages = \Pressmind\ORM\Object\Touristic\Housing\Package::listAll(['id_media_object' => $this->getId()]);
        foreach ($HousingPackages as $HousingPackage) {
            $idName = md5((string)$HousingPackage->name);
            $housingPackageIdMap[$idName] = $HousingPackage->id;
        }

        foreach ($documents as $document) {
            // Fallback 1: Extract id_housing_package from cheapest_price entries if not on document level
            if (empty($document->id_housing_package) && !empty($document->month)) {
                foreach ($document->month as $month) {
                    if (!empty($month->days)) {
                        foreach ($month->days as $day) {
                            if (!empty($day->cheapest_price->id_housing_package)) {
                                $document->id_housing_package = $day->cheapest_price->id_housing_package;
                                break 2;
                            }
                        }
                    }
                }
            }

            // Fallback 2: Map housing_package_id_name to id_housing_package
            if (empty($document->id_housing_package) && !empty($document->housing_package_id_name) && isset($housingPackageIdMap[$document->housing_package_id_name])) {
                $document->id_housing_package = $housingPackageIdMap[$document->housing_package_id_name];
            }

            // Fallback 3: If only one housing package exists and id_housing_package is still empty, use it
            if (empty($document->id_housing_package) && count($HousingPackages) === 1) {
                $document->id_housing_package = $HousingPackages[0]->id;
            }

            if (!empty($document->transport_type)) {
                if (!isset($filter['transport_types'][$document->transport_type])) {
                    $filter['transport_types'][$document->transport_type] = ['durations' => [], 'airports' => [], 'occupancies' => [], 'startingpoint_id_cities' => [], 'housing_package_id_names' => [], 'id_housing_packages' => []];
                }
                if (!empty($document->booking_package->duration) && !in_array($document->booking_package->duration, $filter['transport_types'][$document->transport_type]['durations'])) {
                    $filter['transport_types'][$document->transport_type]['durations'][] = $document->booking_package->duration;
                }
                if (!empty($document->occupancy) && !in_array($document->occupancy, $filter['transport_types'][$document->transport_type]['occupancies'])) {
                    $filter['transport_types'][$document->transport_type]['occupancies'][] = $document->occupancy;
                }
                if (!empty($document->airport) && !in_array($document->airport, $filter['transport_types'][$document->transport_type]['airports'])) {
                    $filter['transport_types'][$document->transport_type]['airports'][] = $document->airport;
                }
                if (!empty($document->startingpoint_id_city) && !in_array($document->startingpoint_id_city, $filter['transport_types'][$document->transport_type]['startingpoint_id_cities'])) {
                    $filter['transport_types'][$document->transport_type]['startingpoint_id_cities'][] = $document->startingpoint_id_city;
                }
                if (!empty($document->housing_package_id_name) && !in_array($document->housing_package_id_name, $filter['transport_types'][$document->transport_type]['housing_package_id_names'])) {
                    $filter['transport_types'][$document->transport_type]['housing_package_id_names'][] = $document->housing_package_id_name;
                }
                if (!empty($document->id_housing_package) && !in_array($document->id_housing_package, $filter['transport_types'][$document->transport_type]['id_housing_packages'])) {
                    $filter['transport_types'][$document->transport_type]['id_housing_packages'][] = $document->id_housing_package;
                }
            }
            if (!empty($document->booking_package->duration) && !isset($filter['durations'][$document->booking_package->duration])) {
                $filter['durations'][$document->booking_package->duration] = ['transport_types' => [], 'airports' => [], 'occupancies' => [], 'startingpoint_id_cities' => [], 'housing_package_id_names' => [], 'id_housing_packages' => []];
            }
            if (!empty($document->occupancy) && !in_array($document->occupancy, $filter['durations'][$document->booking_package->duration]['occupancies'])) {
                $filter['durations'][$document->booking_package->duration]['occupancies'][] = $document->occupancy;
            }
            if (!empty($document->transport_type) && !in_array($document->transport_type, $filter['durations'][$document->booking_package->duration]['transport_types'])) {
                $filter['durations'][$document->booking_package->duration]['transport_types'][] = $document->transport_type;
            }
            if (!empty($document->airport) && !in_array($document->airport, $filter['durations'][$document->booking_package->duration]['airports'])) {
                $filter['durations'][$document->booking_package->duration]['airports'][] = $document->airport;
            }
            if (!empty($document->startingpoint_id_city) && !in_array($document->startingpoint_id_city, $filter['durations'][$document->booking_package->duration]['startingpoint_id_cities'])) {
                $filter['durations'][$document->booking_package->duration]['startingpoint_id_cities'][] = $document->startingpoint_id_city;
            }
            if (!empty($document->housing_package_id_name) && !in_array($document->housing_package_id_name, $filter['durations'][$document->booking_package->duration]['housing_package_id_names'])) {
                $filter['durations'][$document->booking_package->duration]['housing_package_id_names'][] = $document->housing_package_id_name;
            }
            if (!empty($document->id_housing_package) && !in_array($document->id_housing_package, $filter['durations'][$document->booking_package->duration]['id_housing_packages'])) {
                $filter['durations'][$document->booking_package->duration]['id_housing_packages'][] = $document->id_housing_package;
            }
            if (!empty($document->airport)) {
                if (!isset($filter['airports'][$document->airport])) {
                    $filter['airports'][$document->airport] = ['durations' => [], 'transport_types' => [], 'occupancies' => [], 'startingpoint_id_cities' => [], 'housing_package_id_names' => [], 'id_housing_packages' => []];
                }
                if (!empty($document->occupancy) && !in_array($document->occupancy, $filter['airports'][$document->airport]['occupancies'])) {
                    $filter['airports'][$document->airport]['occupancies'][] = $document->occupancy;
                }
                if (!empty($document->transport_type) && !in_array($document->transport_type, $filter['airports'][$document->airport]['transport_types'])) {
                    $filter['airports'][$document->airport]['transport_types'][] = $document->transport_type;
                }
                if (!empty($document->startingpoint_id_city) && !in_array($document->startingpoint_id_city, $filter['airports'][$document->airport]['startingpoint_id_cities'])) {
                    $filter['airports'][$document->airport]['startingpoint_id_cities'][] = $document->startingpoint_id_city;
                }
                if (!empty($document->booking_package->duration) && !in_array($document->booking_package->duration, $filter['airports'][$document->airport]['durations'])) {
                    $filter['airports'][$document->airport]['durations'][] = $document->booking_package->duration;
                }
                if (!empty($document->housing_package_id_name) && !in_array($document->housing_package_id_name, $filter['airports'][$document->airport]['housing_package_id_names'])) {
                    $filter['airports'][$document->airport]['housing_package_id_names'][] = $document->housing_package_id_name;
                }
                if (!empty($document->id_housing_package) && !in_array($document->id_housing_package, $filter['airports'][$document->airport]['id_housing_packages'])) {
                    $filter['airports'][$document->airport]['id_housing_packages'][] = $document->id_housing_package;
                }
            }
            if (!empty($document->startingpoint_id_city)) {
                if (!isset($filter['startingpoint_id_cities'][$document->startingpoint_id_city])) {
                    $filter['startingpoint_id_cities'][$document->startingpoint_id_city] = ['durations' => [], 'transport_types' => [], 'occupancies' => [], 'airports' => [], 'housing_package_id_names' => [], 'id_housing_packages' => []];
                }
                if (!empty($document->occupancy) && !in_array($document->occupancy, $filter['startingpoint_id_cities'][$document->startingpoint_id_city]['occupancies'])) {
                    $filter['startingpoint_id_cities'][$document->startingpoint_id_city]['occupancies'][] = $document->occupancy;
                }
                if (!empty($document->transport_type) && !in_array($document->transport_type, $filter['startingpoint_id_cities'][$document->startingpoint_id_city]['transport_types'])) {
                    $filter['startingpoint_id_cities'][$document->startingpoint_id_city]['transport_types'][] = $document->transport_type;
                }
                if (!empty($document->booking_package->duration) && !in_array($document->booking_package->duration, $filter['startingpoint_id_cities'][$document->startingpoint_id_city]['durations'])) {
                    $filter['startingpoint_id_cities'][$document->startingpoint_id_city]['durations'][] = $document->booking_package->duration;
                }
                if (!empty($document->airport) && !in_array($document->airport, $filter['startingpoint_id_cities'][$document->startingpoint_id_city]['airports'])) {
                    $filter['startingpoint_id_cities'][$document->startingpoint_id_city]['airports'][] = $document->airport;
                }
                if (!empty($document->housing_package_id_name) && !in_array($document->housing_package_id_name, $filter['startingpoint_id_cities'][$document->startingpoint_id_city]['housing_package_id_names'])) {
                    $filter['startingpoint_id_cities'][$document->startingpoint_id_city]['housing_package_id_names'][] = $document->housing_package_id_name;
                }
                if (!empty($document->id_housing_package) && !in_array($document->id_housing_package, $filter['startingpoint_id_cities'][$document->startingpoint_id_city]['id_housing_packages'])) {
                    $filter['startingpoint_id_cities'][$document->startingpoint_id_city]['id_housing_packages'][] = $document->id_housing_package;
                }
            }
            if (!empty($document->housing_package_id_name)) {
                if (!isset($filter['housing_package_id_names'][$document->housing_package_id_name])) {
                    $filter['housing_package_id_names'][$document->housing_package_id_name] = ['durations' => [], 'transport_types' => [], 'occupancies' => [], 'airports' => [], 'startingpoint_id_cities' => []];
                }
                if (!empty($document->occupancy) && !in_array($document->occupancy, $filter['housing_package_id_names'][$document->housing_package_id_name]['occupancies'])) {
                    $filter['housing_package_id_names'][$document->housing_package_id_name]['occupancies'][] = $document->occupancy;
                }
                if (!empty($document->transport_type) && !in_array($document->transport_type, $filter['housing_package_id_names'][$document->housing_package_id_name]['transport_types'])) {
                    $filter['housing_package_id_names'][$document->housing_package_id_name]['transport_types'][] = $document->transport_type;
                }
                if (!empty($document->booking_package->duration) && !in_array($document->booking_package->duration, $filter['housing_package_id_names'][$document->housing_package_id_name]['durations'])) {
                    $filter['housing_package_id_names'][$document->housing_package_id_name]['durations'][] = $document->booking_package->duration;
                }
                if (!empty($document->airport) && !in_array($document->airport, $filter['housing_package_id_names'][$document->housing_package_id_name]['airports'])) {
                    $filter['housing_package_id_names'][$document->housing_package_id_name]['airports'][] = $document->airport;
                }
            }
            // Build id_housing_packages filter (uses actual housing package IDs)
            if (!empty($document->id_housing_package)) {
                if (!isset($filter['id_housing_packages'][$document->id_housing_package])) {
                    $filter['id_housing_packages'][$document->id_housing_package] = ['durations' => [], 'transport_types' => [], 'occupancies' => [], 'airports' => [], 'startingpoint_id_cities' => []];
                }
                if (!empty($document->occupancy) && !in_array($document->occupancy, $filter['id_housing_packages'][$document->id_housing_package]['occupancies'])) {
                    $filter['id_housing_packages'][$document->id_housing_package]['occupancies'][] = $document->occupancy;
                }
                if (!empty($document->transport_type) && !in_array($document->transport_type, $filter['id_housing_packages'][$document->id_housing_package]['transport_types'])) {
                    $filter['id_housing_packages'][$document->id_housing_package]['transport_types'][] = $document->transport_type;
                }
                if (!empty($document->booking_package->duration) && !in_array($document->booking_package->duration, $filter['id_housing_packages'][$document->id_housing_package]['durations'])) {
                    $filter['id_housing_packages'][$document->id_housing_package]['durations'][] = $document->booking_package->duration;
                }
                if (!empty($document->airport) && !in_array($document->airport, $filter['id_housing_packages'][$document->id_housing_package]['airports'])) {
                    $filter['id_housing_packages'][$document->id_housing_package]['airports'][] = $document->airport;
                }
                if (!empty($document->startingpoint_id_city) && !in_array($document->startingpoint_id_city, $filter['id_housing_packages'][$document->id_housing_package]['startingpoint_id_cities'])) {
                    $filter['id_housing_packages'][$document->id_housing_package]['startingpoint_id_cities'][] = $document->startingpoint_id_city;
                }
            }
            if (!empty($document->occupancy) && !isset($filter['occupancies'][$document->occupancy])) {
                $filter['occupancies'][$document->occupancy] = ['durations' => [], 'transport_types' => [], 'airports' => [], 'startingpoint_id_cities' => [], 'housing_package_id_names' => [], 'id_housing_packages' => []];
            }
            if (!empty($document->transport_type) && !in_array($document->transport_type, $filter['occupancies'][$document->occupancy]['transport_types'])) {
                $filter['occupancies'][$document->occupancy]['transport_types'][] = $document->transport_type;
            }
            if (!empty($document->booking_package->duration) && !in_array($document->booking_package->duration, $filter['occupancies'][$document->occupancy]['durations'])) {
                $filter['occupancies'][$document->occupancy]['durations'][] = $document->booking_package->duration;
            }
            if (!empty($document->airport) && !in_array($document->airport, $filter['occupancies'][$document->occupancy]['airports'])) {
                $filter['occupancies'][$document->occupancy]['airports'][] = $document->airport;
            }
            if (!empty($document->startingpoint_id_city) && !in_array($document->startingpoint_id_city, $filter['occupancies'][$document->occupancy]['startingpoint_id_cities'])) {
                $filter['occupancies'][$document->occupancy]['startingpoint_id_cities'][] = $document->startingpoint_id_city;
            }
            if (!empty($document->housing_package_id_name) && !in_array($document->housing_package_id_name, $filter['occupancies'][$document->occupancy]['housing_package_id_names'])) {
                $filter['occupancies'][$document->occupancy]['housing_package_id_names'][] = $document->housing_package_id_name;
            }
            if (!empty($document->id_housing_package) && !in_array($document->id_housing_package, $filter['occupancies'][$document->occupancy]['id_housing_packages'])) {
                $filter['occupancies'][$document->occupancy]['id_housing_packages'][] = $document->id_housing_package;
            }
            if (
                (empty($filters->occupancy) || $filters->occupancy == $document->occupancy) &&
                (empty($filters->transport_type) || $filters->transport_type == $document->transport_type) &&
                (empty($filters->duration) || $filters->duration == $document->booking_package->duration) &&
                (empty($filters->airport) || $filters->airport == $document->airport) &&
                (empty($filters->housing_package_code_ibe) || $filters->housing_package_code_ibe == $document->housing_package->code_ibe) &&
                (empty($filters->startingpoint_id_city) || $filters->startingpoint_id_city == $document->startingpoint_id_city) &&
                (empty($filters->housing_package_id_name) || $filters->housing_package_id_name == $document->housing_package_id_name)
            ){
                $filtered_documents[] = $document;
            }
        }
        $this->_removeUnnecessaryHousingPackageIdNames($filter);
        $result = new stdClass();
        $result->filter = $filter;
        $result->calendar = null;
        if (count($filtered_documents) == 0) {
            return $result;
        }
        $merged_calendar_object = null;
        foreach($filtered_documents as $document) {
            if(empty($merged_calendar_object)) {
                $merged_calendar_object = clone $document;
                $merged_calendar_object->month = [];
            }
            foreach($document->month as $month) {
                $month_key = $month->year . '-' . $month->month;
                if(empty($merged_calendar_object->month[$month_key])) {
                    $merged_calendar_object->month[$month_key] = $month;
                } else {
                    foreach($month->days as $key => $day) {
                        $existing_day = !empty($merged_calendar_object->month[$month_key]->days[$key]) ? $merged_calendar_object->month[$month_key]->days[$key] : null;
                        if(!empty($existing_day->cheapest_price)) {
                            if(!empty($day->cheapest_price)) {
                                if($existing_day->cheapest_price->price_total > $day->cheapest_price->price_total) {
                                    $merged_calendar_object->month[$month_key]->days[$key]->cheapest_price = $day->cheapest_price;
                                }
                            }
                        } else {
                            $merged_calendar_object->month[$month_key]->days[$key] = $day;
                            if(!empty($day->cheapest_price)) {
                                $merged_calendar_object->bookable_date_count ++;
                            }
                        }
                    }
                }
            }
        }
        $merged_calendar_object->month = array_values($merged_calendar_object->month);
        $result->calendar = $merged_calendar_object;
        $BookingPackage = new Package();
        $result->calendar->booking_package->created = null;
        $BookingPackage->fromStdClass($result->calendar->booking_package);
        $result->calendar->booking_package = $BookingPackage;
        foreach ($result->calendar->month as $k => $departure) {
            foreach ($departure->days as $k1 => $day) {
                $result->calendar->month[$k]->days[$k1]->date = new \DateTime($day->date);
                if (isset($result->calendar->month[$k]->days[$k1]->cheapest_price)) {
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
        if (count($result->calendar->month) < $min_columns) {
            $add_months = $min_columns - count($result->calendar->month) + 1;
            $from->modify('+' . count($result->calendar->month) . ' month');
            $to->modify('+' . $add_months . ' month'); // +1?
            foreach (new \DatePeriod($from, new \DateInterval('P1M'), $to) as $dt) {
                $days = range(1, $dt->format('t'));
                $departure = new stdClass();
                $departure->year = $dt->format('Y');
                $departure->month = $dt->format('m');
                $departure->is_bookable = false;
                $departure->days = [];
                foreach ($days as $day) {
                    $dayObj = new stdClass();
                    $dayObj->date = new \DateTime($dt->format('Y-m-' . $day . ' 00:00:00'));
                    $departure->days[] = $dayObj;
                }
                $result->calendar->month[] = $departure;
            }
        }
        return $result;
    }

    /**
     * @param $data
     * @return void
     */
    private function _removeUnnecessaryHousingPackageIdNames(&$data, $isTopLevel = true) {
        if (!is_array($data)) {
            return;
        }
        foreach ($data as $key => &$value) {
            // Only remove single-entry housing arrays at nested levels (inside transport_types, durations, etc.)
            // Keep them at top level so templates can access all available housing packages
            if (!$isTopLevel && $key === 'housing_package_id_names' && is_array($value) && count($value) === 1) {
                $value = [];
            } elseif (!$isTopLevel && $key === 'id_housing_packages' && is_array($value) && count($value) === 1) {
                $value = [];
            } elseif (is_array($value)) {
                $this->_removeUnnecessaryHousingPackageIdNames($value, false);
            }
        }
    }

    /**
     * @return string[]
     * @throws Exception
     */
    public function buildPrettyUrls($language = null)
    {
        $config = Registry::getInstance()->get('config');
        $is_legancy = !isset($config['data']['media_types_pretty_url'][array_key_first($config['data']['media_types_pretty_url'])]['id_object_type']);
        $fields = [];
        $separator = '-';
        $strategy = 'unique';
        $prefix = '/';
        $suffix = '';
        if($is_legancy){
            $fields = $config['data']['media_types_pretty_url'][$this->id_object_type]['fields'] ?? ['name'];
            $separator = $config['data']['media_types_pretty_url'][$this->id_object_type]['separator'] ?? $separator;
            $strategy = $config['data']['media_types_pretty_url'][$this->id_object_type]['strategy'] ?? $strategy;
            $prefix = $config['data']['media_types_pretty_url'][$this->id_object_type]['prefix'] ?? $prefix;
            $suffix = $config['data']['media_types_pretty_url'][$this->id_object_type]['suffix'] ?? $suffix;
        }else{
            foreach($config['data']['media_types_pretty_url'] as $v){
                if($v['id_object_type'] == $this->id_object_type && $v['language'] == $language){
                    $fields = $v['field'] ?? ['name'];
                    $separator = $v['separator'] ?? $separator;
                    $strategy = $v['strategy'] ?? $strategy;
                    $prefix = $v['prefix'] ?? $prefix;
                    $suffix = $v['suffix'] ?? $suffix;
                    break;
                }
            }
        }
        $url_array = [];
        foreach ($fields as $field) {
            if(empty($this->$field)){
                continue;
            }
            if(in_array($field, $this->getPropertyNames())) {
                $url_array[] = strtolower(HelperFunctions::replaceLatinSpecialChars(trim($this->$field)));
            } else {
                $mo = new MediaObject($this->getId());
                $moc = $mo->getDataForLanguage($language);
                if(!empty($moc) && $moc->getPropertyDefinition($field)['type'] == 'string') {
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
        self::$_insert_cheapest_price_log[$this->id][] = 'Creating index for media_object: '.$this->id;
        $max_rows = empty(Registry::getInstance()->get('config')['data']['touristic']['max_offers_per_product']) ? 5000 : Registry::getInstance()->get('config')['data']['touristic']['max_offers_per_product'];
        $ibe_client = empty(Registry::getInstance()->get('config')['data']['touristic']['ibe_client']) ? null : Registry::getInstance()->get('config')['data']['touristic']['ibe_client'];
        $include_negative_option_in_cheapest_price = !isset(Registry::getInstance()->get('config')['data']['touristic']['include_negative_option_in_cheapest_price']) ? true : Registry::getInstance()->get('config')['data']['touristic']['include_negative_option_in_cheapest_price'];
        $agency_based_option_and_prices_enabled = !isset(Registry::getInstance()->get('config')['data']['touristic']['agency_based_option_and_prices']['enabled']) ? false : Registry::getInstance()->get('config')['data']['touristic']['agency_based_option_and_prices']['enabled'];
        $agencies = empty(Registry::getInstance()->get('config')['data']['touristic']['agency_based_option_and_prices']['allowed_agencies']) || $agency_based_option_and_prices_enabled === false ? [null] : Registry::getInstance()->get('config')['data']['touristic']['agency_based_option_and_prices']['allowed_agencies'];
        $offer_for_each_startingpoint_option = !empty(Registry::getInstance()->get('config')['data']['touristic']['generate_offer_for_each_startingpoint_option']);
        $travel_date_orientation = 'departure';
        $travel_date_offset = 0;
        $travel_date_allowed_states = [0, 1, 2, 4, 5];
        $max_date_offset = 730;
        if(!empty(Registry::getInstance()->get('config')['data']['touristic']['date_filter']['active'])) {
            $travel_date_orientation = empty(Registry::getInstance()->get('config')['data']['touristic']['date_filter']['orientation']) ? 'departure' : Registry::getInstance()->get('config')['data']['touristic']['date_filter']['orientation'];
            $travel_date_offset = empty(Registry::getInstance()->get('config')['data']['touristic']['date_filter']['offset']) ? 0 : Registry::getInstance()->get('config')['data']['touristic']['date_filter']['offset'];
            $travel_date_allowed_states = empty(Registry::getInstance()->get('config')['data']['touristic']['date_filter']['allowed_states']) ? [0, 1, 2, 3, 4, 5] : Registry::getInstance()->get('config')['data']['touristic']['date_filter']['allowed_states'];
            $max_date_offset = empty(Registry::getInstance()->get('config')['data']['touristic']['date_filter']['max_date_offset']) ? 730 : Registry::getInstance()->get('config')['data']['touristic']['date_filter']['max_date_offset'];
            if (!in_array($travel_date_orientation, ['departure', 'arrival'])) {
                throw new Exception('Error: data.touristic.date_filter.orientation must be either "departure" or "arrival" in config file.');
            }
            if (!is_array($travel_date_allowed_states) || count($travel_date_allowed_states) == 0) {
                throw new Exception('Error: data.touristic.date_filter.date_filter must be an array with min one key in config file.');
            }
        }
        $housing_option_allowed_states = [0, 1, 2, 3];
        if(!empty(Registry::getInstance()->get('config')['data']['touristic']['housing_option_filter']['active'])) {
            $housing_option_allowed_states = empty(Registry::getInstance()->get('config')['data']['touristic']['housing_option_filter']['allowed_states']) ? $housing_option_allowed_states : Registry::getInstance()->get('config')['data']['touristic']['housing_option_filter']['allowed_states'];
        }
        $transport_allowed_states = [0, 2, 3];
        if(!empty(Registry::getInstance()->get('config')['data']['touristic']['transport_filter']['active'])) {
            $transport_allowed_states = empty(Registry::getInstance()->get('config')['data']['touristic']['transport_filter']['allowed_states']) ? $transport_allowed_states : Registry::getInstance()->get('config')['data']['touristic']['transport_filter']['allowed_states'];
        }
        $CheapestPrice = new CheapestPriceSpeed();
        $CheapestPrice->deleteByMediaObjectId($this->getId());
        $booking_packages = $this->booking_packages;
        if(empty($booking_packages)){
            self::$_insert_cheapest_price_log[$this->id][] = 'No booking packages found';
        }
        $now = new DateTime();
        $now->setTime(0, 0, 0);
        $now->modify( $travel_date_offset . ' days');
        $max_date = new DateTime();
        $max_date->setTime(0, 0, 0);
        $max_date->modify( $max_date_offset . ' days');
        $c = 0;
        self::$_insert_cheapest_price_log[$this->id][] = 'Creating index for this agencies: '.implode(',',$agencies);
        foreach ($agencies as $agency) {
            self::$_insert_cheapest_price_log[$this->id][] = 'Current agency id = '.$agency;
            foreach ($booking_packages as $booking_package) {
                self::$_insert_cheapest_price_log[$this->id][] = 'current booking_package id = '.$booking_package->id.', price_mix = '.$booking_package->price_mix;
                foreach ($booking_package->dates as $date) {
                    $calculated_earlybirds = [];
                    if(($travel_date_orientation == 'departure' && $date->departure < $now) ||
                        ($travel_date_orientation == 'arrival' && $date->arrival < $now) ||
                        ($travel_date_orientation == 'arrival' && $date->arrival > $max_date) ||
                        ($travel_date_orientation == 'departure' && $date->departure > $max_date) ||
                        !in_array($date->state, $travel_date_allowed_states)) {
                        self::$_insert_cheapest_price_log[$this->id][] = 'Skipping date ' . $date->departure->format('Y-m-d') . ' because of date filter';
                        continue;
                    }
                    $date_agencies = array_filter(explode(',', (string)$date->agencies));
                    if(!empty($agency) && !empty($date_agencies) && !in_array($agency, $date_agencies)){
                        self::$_insert_cheapest_price_log[$this->id][] = 'Skipping date ' . $date->departure->format('Y-m-d') . ' because of agency filter (current agency = '.$agency.') (agencies allowed for this date = '.implode(',',$date_agencies).')';
                        continue;
                    }
                    /** @var Item[] $early_bird_discounts */
                    // Falls die early_bird_discount_group Relation nicht automatisch geladen wurde, manuell laden
                    if(!empty($date->id_early_bird_discount_group) && empty($date->early_bird_discount_group)){
                        $date->early_bird_discount_group = new EarlyBirdDiscountGroup($date->id_early_bird_discount_group, true);
                    }
                    $early_bird_discounts = $date->getEarlybirds($agency);
                    if(empty($early_bird_discounts)){
                        $early_bird_discounts = [null];
                    }
                    /** @var Transport[] $transport_pairs */
                    $transport_pairs = count($date->transports) > 0 ? $date->getTransportPairs($transport_allowed_states, [], [], null, true, $agency) : [null];
                    $options = [];
                    if ($booking_package->price_mix == 'date_housing') {
                        $options = $date->getHousingOptions($housing_option_allowed_states, true, $agency);
                    }
                    if ($booking_package->price_mix == 'date_sightseeing') {
                        $options = $date->getSightseeings(true, $agency);
                    }
                    if ($booking_package->price_mix == 'date_extra') {
                        $options = $date->getExtras(true, $agency);
                    }
                    if ($booking_package->price_mix == 'date_ticket') {
                        $options = $date->getTickets(true, $agency);
                    }
                    if ($booking_package->price_mix == 'date_transport') {
                        $tmpOption = new Option();
                        $tmpOption->name = '';
                        $tmpOption->price = 0;
                        $options[] = $tmpOption;
                    }
                    if(empty($options)){
                        self::$_insert_cheapest_price_log[$this->id][] = 'Skipping date ' . $date->departure->format('Y-m-d').' (season = '.$date->season.', agency = '.$agency.') because of no valid options found for price_mix = '.$booking_package->price_mix;
                        // Sub-validation: Log counts of different option types for debugging
                        $extras_count = count($date->getExtras(true, $agency));
                        $ticket_count = count($date->getTickets(true, $agency));
                        $sightseeing_count = count($date->getSightseeings(true, $agency));
                        self::$_insert_cheapest_price_log[$this->id][] = 'Option Info: extras count = ' . $extras_count . ', ticket count: ' . $ticket_count . ', sightseeing: ' . $sightseeing_count;
                        continue;
                    }
                    $cheapest_options = [];
                    $check_group_validity = [];
                    $option_list = $date->getAllOptionsButExcludePriceMixOptions($booking_package->price_mix, true, $agency);
                    foreach ($option_list as $option) {
                        $key = $option->type . '-' . $option->required_group;
                        if (!empty($option->required_group) && !empty($option->required)) {
                            $check_group_validity[$key]['items_count'] = isset($check_group_validity[$key]['items_count']) ? $check_group_validity[$key]['items_count'] + 1 : 0;
                            if (in_array($option->state, [1, 2, 3])) {
                                if (empty($cheapest_options[$key]->id) || empty($option->price) || $cheapest_options[$key]->price > $option->price) {
                                    $cheapest_options[$key] = $option;
                                }
                            } else {
                                $check_group_validity[$key]['items_count_not_valid'] = isset($check_group_validity[$key]['items_count_not_valid']) ? $check_group_validity[$key]['items_count_not_valid'] + 1 : 0;
                            }
                        }
                    }
                    /**
                     * @var Option[] $cheapest_options
                     */
                    $cheapest_options = array_values($cheapest_options);
                    foreach ($check_group_validity as $k => $v) {
                        if (isset($v['items_count_not_valid']) && ($v['items_count'] - $v['items_count_not_valid'] == 0)) {
                            continue(2);
                        }
                    }
                    $quotas = [];
                    foreach ($options as $option) {
                        $housing_package = $option->getHousingPackage();
                        $included_options_price = 0;
                        $included_options_earlybird_price_base = 0;
                        $included_options_lowest_state = 3;
                        $included_options_description = [];
                        $id_included_options = [];
                        $code_ibe_included_options = [];
                        $nights = empty($housing_package) ? 0 : $housing_package->nights;
                        $quotas[] = (is_null($option->quota) ? 999 : $option->quota) * $option->occupancy;
                        foreach ($cheapest_options as $cheapest_option) {
                            $cheapest_option_price = $cheapest_option->calculatePrice($booking_package->duration, $nights);
                            if ($include_negative_option_in_cheapest_price === false && $cheapest_option_price < 0) {
                                self::$_insert_cheapest_price_log[$this->id][] = 'Skipping option ' . $cheapest_option->name . ' because of negative price';
                                continue;
                            }
                            $included_options_price += $cheapest_option_price;
                            if (!empty($cheapest_option->use_earlybird)) {
                                self::$_insert_cheapest_price_log[$this->id][] = 'Add includes option ' . $cheapest_option->name . ' to earlybird base price';
                                $included_options_earlybird_price_base += $cheapest_option_price;
                            }
                            if ($included_options_lowest_state > $cheapest_option->state) {
                                $included_options_lowest_state = $cheapest_option->state;
                            }
                            $included_options_description[] = $cheapest_option->name;
                            $id_included_options[] = $cheapest_option->getId();
                            $code_ibe_included_options[] = $cheapest_option->code_ibe;
                            $quotas[] = is_null($cheapest_option->quota) ? 999 : $cheapest_option->quota;
                        }
                        foreach ($transport_pairs as $transport_pair) {
                            $is_bookable = in_array($date->state, [1, 4, 0]);
                            $is_request = in_array($date->state, [2]);
                            $is_bookable = $is_bookable && in_array($option->state, [3, 2]);
                            $is_request = $is_request || in_array($option->state, [1]);
                            if (count($id_included_options) == 1 && $cheapest_options[0]->state == 4) {
                                $is_bookable = false;
                                $is_request = false;
                            }
                            if (count($id_included_options) == 1 && $cheapest_options[0]->state == 1) {
                                $is_bookable = false;
                                $is_request = true;
                            }
                            if (!empty($transport_pair['way1']) && !empty($transport_pair['way2'])) {
                                $is_bookable = $is_bookable && in_array($transport_pair['way1']->state, [3, 0]);
                                $is_request = $is_request || in_array($transport_pair['way1']->state, [2]);
                                $is_bookable = $is_bookable && in_array($transport_pair['way2']->state, [3, 0]);
                                $is_request = $is_request || in_array($transport_pair['way2']->state, [2]);
                                $quotas[] = is_null($transport_pair['way1']->quota) ? 999 : $transport_pair['way1']->quota;
                                $quotas[] = is_null($transport_pair['way2']->quota) ? 999 : $transport_pair['way2']->quota;
                            }
                            $startingPointOptions = [];
                            if (!empty($transport_pair['way1']->id_starting_point)) {
                                if ($offer_for_each_startingpoint_option) {
                                    $startingPointOptions = Startingpoint::getOptions($transport_pair['way1']->id_starting_point, 0, 5000, $ibe_client);
                                } else {
                                    $StartingPointOption = Startingpoint::getCheapestOption($transport_pair['way1']->id_starting_point, $ibe_client);
                                    $startingPointOptions = [$StartingPointOption];
                                }
                            }
                            if(empty($startingPointOptions)){
                                $dummy = new Touristic\Startingpoint\Option();
                                $dummy->price = 0;
                                $dummy->price_per_day = false;
                                $startingPointOptions = [$dummy];
                            }
                            foreach ($startingPointOptions as $StartingPointOption) {
                                if (!empty($StartingPointOption->price_per_day) && $StartingPointOption->price_per_day) {
                                    $starting_point_price = $StartingPointOption->price * $booking_package->duration;
                                } else {
                                    $starting_point_price = empty($StartingPointOption->price) ? 0 : $StartingPointOption->price;
                                }
                                $starting_point_earlybird_price_base = !empty($StartingPointOption->use_earlybird) ? $starting_point_price : 0;
                                $transport_earlybird_price_base = 0;
                                foreach ($early_bird_discounts as $early_bird_discount) {
                                    if(!empty($early_bird_discount->booking_date_to)){
                                        $early_bird_discount->booking_date_to->setTime('23', '59', '59');
                                    }

                                    /**
                                     * Prevent ugly duplicates
                                     */
                                    /**
                                    $checksum_earlybird_stdClass = $early_bird_discount->toStdClass();
                                    unset($checksum_earlybird_stdClass->id);
                                    unset($checksum_earlybird_stdClass->id_early_bird_discount_group);
                                    $checksum_earlybird = serialize($checksum_earlybird_stdClass);
                                    if(in_array($checksum_earlybird, $calculated_earlybirds)){
                                    continue;
                                    }
                                    $calculated_earlybirds[] = $checksum_earlybird;
                                     */

                                    if (!is_null($transport_pair) && isset($transport_pair['way1'])) {
                                        $transport_price = $transport_pair['way1']->price + (isset($transport_pair['way2']) ? $transport_pair['way2']->price : 0);
                                        if ($transport_pair['way1']->use_earlybird) {
                                            $transport_earlybird_price_base = $transport_pair['way1']->price;
                                        }
                                        if (isset($transport_pair['way2']) && $transport_pair['way2']->use_earlybird) {
                                            $transport_earlybird_price_base += $transport_pair['way2']->price;
                                        }
                                    } else {
                                        $transport_price = 0;
                                    }
                                    // zero prices are not allowed in primary options
                                    if (($booking_package->price_mix == 'date_transport' && empty($transport_price)) ||
                                        ($booking_package->price_mix != 'date_transport' && empty($option->price))
                                    ) {
                                        self::$_insert_cheapest_price_log[$this->id][] = 'Skipping primary option ' . $option->name . ' because of zero price';
                                        continue;
                                    }
                                    $price = $option->price + $transport_price + $starting_point_price + $included_options_price;
                                    $price_base_early_bird = ($option->use_earlybird ? $option->price : 0) + $transport_earlybird_price_base + $starting_point_earlybird_price_base + $included_options_earlybird_price_base;
                                    if ($price <= 0) {
                                        self::$_insert_cheapest_price_log[$this->id][] = 'Skipping option ' . $option->name . ' because of zero price';
                                        continue;
                                    }
                                    $cheapestPriceSpeed = new CheapestPriceSpeed();
                                    $cheapestPriceSpeed->id_media_object = $this->getId();
                                    $cheapestPriceSpeed->id_booking_package = $booking_package->getId();
                                    $cheapestPriceSpeed->id_housing_package = $option->id_housing_package;
                                    $cheapestPriceSpeed->housing_package_id_name = empty($housing_package->name) ? null : md5($housing_package->name);
                                    $cheapestPriceSpeed->id_date = $date->getId();
                                    $cheapestPriceSpeed->id_option = $option->getId();
                                    $cheapestPriceSpeed->id_transport_1 = !is_null($transport_pair) && isset($transport_pair['way1']) ? $transport_pair['way1']->id : null;
                                    $cheapestPriceSpeed->id_transport_2 = !is_null($transport_pair) && isset($transport_pair['way1']) && isset($transport_pair['way2']) ? $transport_pair['way2']->id : null;
                                    $cheapestPriceSpeed->duration = $booking_package->duration;
                                    $cheapestPriceSpeed->date_departure = $date->departure;
                                    $cheapestPriceSpeed->date_arrival = $date->arrival;
                                    $cheapestPriceSpeed->option_name = $option->name;
                                    $cheapestPriceSpeed->option_description_long = $option->description_long;
                                    $cheapestPriceSpeed->option_code = $option->code;
                                    $cheapestPriceSpeed->option_board_type = $option->board_type;
                                    $cheapestPriceSpeed->option_board_code = $option->board_code;
                                    $cheapestPriceSpeed->option_occupancy = empty($option->occupancy) ? 1 : $option->occupancy;
                                    $cheapestPriceSpeed->option_occupancy_min = empty($option->occupancy_min) ? $option->occupancy : $option->occupancy_min;
                                    $cheapestPriceSpeed->option_occupancy_max = empty($option->occupancy_max) ? $option->occupancy : $option->occupancy_max;
                                    $cheapestPriceSpeed->option_occupancy_child = empty($option->occupancy_child) ? null: $option->occupancy_child;
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
                                    if (!empty($cheapestPriceSpeed->transport_1_airport)) {
                                        $airport = Airport::getByIata($cheapestPriceSpeed->transport_1_airport);
                                        $cheapestPriceSpeed->transport_1_airport_name = !empty($airport->name) ? $airport->name : null;
                                    }
                                    if (!empty($cheapestPriceSpeed->transport_2_airport)) {
                                        $airport = Airport::getByIata($cheapestPriceSpeed->transport_2_airport);
                                        $cheapestPriceSpeed->transport_2_airport_name = !empty($airport->name) ? $airport->name : null;
                                    }
                                    $cheapestPriceSpeed->transport_1_flight = !is_null($transport_pair) && isset($transport_pair['way1']) && isset($transport_pair['way2']) ? $transport_pair['way1']->flight : null;
                                    $cheapestPriceSpeed->transport_2_flight = !is_null($transport_pair) && isset($transport_pair['way1']) && isset($transport_pair['way2']) ? $transport_pair['way2']->flight : null;
                                    $cheapestPriceSpeed->transport_1_date_from = !is_null($transport_pair) && isset($transport_pair['way1']) && isset($transport_pair['way2']) ? $transport_pair['way1']->transport_date_from : null;
                                    $cheapestPriceSpeed->transport_1_date_to = !is_null($transport_pair) && isset($transport_pair['way1']) && isset($transport_pair['way2']) ? $transport_pair['way1']->transport_date_to : null;
                                    $cheapestPriceSpeed->transport_2_date_from = !is_null($transport_pair) && isset($transport_pair['way1']) && isset($transport_pair['way2']) ? $transport_pair['way2']->transport_date_from : null;
                                    $cheapestPriceSpeed->transport_2_date_to = !is_null($transport_pair) && isset($transport_pair['way1']) && isset($transport_pair['way2']) ? $transport_pair['way2']->transport_date_to : null;
                                    if ($is_bookable) {
                                        $cheapestPriceSpeed->state = 3;
                                    }
                                    if ($is_request) {
                                        $cheapestPriceSpeed->state = 1;
                                    }
                                    if (!$is_bookable && !$is_request) {
                                        $cheapestPriceSpeed->state = 5;
                                    }
                                    $cheapestPriceSpeed->infotext = $date->text;
                                    $cheapestPriceSpeed->id_option_auto_book = null;
                                    $cheapestPriceSpeed->id_option_required_group = null;  // @TODO deprecated
                                    $cheapestPriceSpeed->included_options_price = $included_options_price;
                                    $cheapestPriceSpeed->included_options_description = implode(',', $included_options_description);
                                    $cheapestPriceSpeed->id_included_options = implode(',', $id_included_options);
                                    $cheapestPriceSpeed->code_ibe_included_options = implode(',', $code_ibe_included_options);
                                    $cheapestPriceSpeed->id_origin = $booking_package->id_origin;
                                    $cheapestPriceSpeed->id_startingpoint = empty($StartingPointOption) ? null : $StartingPointOption->id_startingpoint;
                                    $cheapestPriceSpeed->id_startingpoint_option = empty($StartingPointOption) ? null : $StartingPointOption->id;
                                    $cheapestPriceSpeed->startingpoint_name = empty($StartingPointOption) ? null : $StartingPointOption->name;
                                    $cheapestPriceSpeed->startingpoint_city = empty($StartingPointOption) ? null : $StartingPointOption->city;
                                    $cheapestPriceSpeed->startingpoint_id_city = empty($StartingPointOption->city) ? null : md5($StartingPointOption->city);
                                    $cheapestPriceSpeed->startingpoint_code_ibe = empty($StartingPointOption) ? null : $StartingPointOption->code_ibe;
                                    $cheapestPriceSpeed->startingpoint_zip = empty($StartingPointOption) ? null : $StartingPointOption->zip;
                                    $cheapestPriceSpeed->price_total = $cheapestPriceSpeed->price_regular_before_discount;
                                    $cheapestPriceSpeed->earlybird_discount = null;
                                    $cheapestPriceSpeed->earlybird_discount_date_to = null;
                                    $cheapestPriceSpeed->earlybird_discount_f = null;
                                    if ($price_base_early_bird > 0 && $this->_checkEarlyBirdDiscount($early_bird_discount, $date, $option->code_ibe)) {
                                        $discount = $this->_calculateEarlyBirdDiscount($early_bird_discount, $price_base_early_bird);
                                        if ($discount > 0 || $discount < 0) {
                                            $cheapestPriceSpeed->earlybird_discount = strtolower($early_bird_discount->type) == 'p' ? $early_bird_discount->discount_value : null;
                                            $cheapestPriceSpeed->earlybird_discount_date_to = $this->_getEffectiveBookingDateTo($early_bird_discount, $date);
                                            $cheapestPriceSpeed->earlybird_discount_f = strtolower($early_bird_discount->type) == 'f' ? $early_bird_discount->discount_value : null;
                                            $cheapestPriceSpeed->earlybird_name = empty($date->early_bird_discount_group->name) ? 'Frühbucher' : $date->early_bird_discount_group->name;
                                            if(!empty($early_bird_discount->name)) {
                                                $cheapestPriceSpeed->earlybird_name = $early_bird_discount->name;
                                            }
                                            $cheapestPriceSpeed->price_total = $cheapestPriceSpeed->price_regular_before_discount + $discount;
                                        }
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
                                    $cheapestPriceSpeed->booking_package_ibe_type = $booking_package->ibe_type;
                                    $cheapestPriceSpeed->booking_package_product_type_ibe = $booking_package->product_type_ibe;
                                    $cheapestPriceSpeed->booking_package_type_of_travel = $booking_package->type_of_travel;
                                    $cheapestPriceSpeed->booking_package_variant_code = $booking_package->variant_code;
                                    $cheapestPriceSpeed->booking_package_request_code = $booking_package->request_code;
                                    $cheapestPriceSpeed->booking_package_name = $booking_package->name;
                                    $cheapestPriceSpeed->booking_package_code = $booking_package->code;
                                    $cheapestPriceSpeed->booking_package_price_group = $booking_package->price_group;
                                    $cheapestPriceSpeed->booking_package_product_group = $booking_package->product_group;
                                    $cheapestPriceSpeed->is_virtual_created_price = $booking_package->is_virtual_created_price;
                                    $cheapestPriceSpeed->guaranteed = $date->guaranteed;
                                    $cheapestPriceSpeed->saved = $date->saved;
                                    $cheapestPriceSpeed->agency = $agency;
                                    $cheapestPriceSpeed->fingerprint = $cheapestPriceSpeed->createFingerprint();
                                    $cheapestPriceSpeed->quota_pax = min($quotas);
                                    $cheapestPriceSpeed->create();
                                    unset($cheapestPriceSpeed);
                                    $c++;
                                    if ($c == $max_rows) {
                                        self::$_insert_cheapest_price_log[$this->id][] = 'Reached maximum number of rows (' . $max_rows . ')';
                                        break(5);
                                    }
                                }
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
     * @return DateTime|null
     */
    private function _getEffectiveBookingDateFrom($discount, $date) {
        if (is_null($discount->booking_date_from) && is_null($discount->booking_date_to) && !empty($discount->booking_days_before_departure)) {
            $booking_date_from = clone $date->departure;
            $booking_date_from->sub(new \DateInterval('P' . $discount->booking_days_before_departure . 'D'));
            return $booking_date_from;
        }
        return $discount->booking_date_from;
    }

    /**
     * @param Item $discount
     * @param Date $date
     * @return DateTime|null
     */
    private function _getEffectiveBookingDateTo($discount, $date) {
        if (is_null($discount->booking_date_from) && is_null($discount->booking_date_to) && !empty($discount->booking_days_before_departure)) {
            return clone $date->departure;
        }
        return $discount->booking_date_to;
    }

    /**
     * @param Item $discount
     * @param Date $date
     * @param string|null $housing_code_ibe
     * @return false
     */
    private function _checkEarlyBirdDiscount($discount, $date, $housing_code_ibe = null) {
        if(is_null($discount)){
            return false;
        }
        if(!empty($discount->room_condition_code_ibe) && $discount->room_condition_code_ibe !== $housing_code_ibe){
            return false;
        }
        $now = new DateTime();
        $now->setTime(0,0,0);
        $booking_date_from = $this->_getEffectiveBookingDateFrom($discount, $date);
        $booking_date_to = $this->_getEffectiveBookingDateTo($discount, $date);
        if(
            ($now >= $booking_date_from || is_null($booking_date_from)) &&
            ($now <= $booking_date_to || is_null($booking_date_to)) &&
            ($date->departure >= $discount->travel_date_from || is_null($discount->travel_date_from)) &&
            ($date->departure <= $discount->travel_date_to || is_null($discount->travel_date_to))
        ) {
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
     * @param string|null $housing_code_ibe
     * @return Item|null
     */
    public function getEarlyBirdDiscount($discounts, $date, $housing_code_ibe = null){
        foreach($discounts as $discount){
            if($this->_checkEarlyBirdDiscount($discount, $date, $housing_code_ibe)){
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
     * @param Item $discount
     * @param Date $date
     * @return bool
     */
    public function checkEarlyBirdDiscountDateOnly($discount, $date) {
        if(is_null($discount)){
            return false;
        }
        $now = new DateTime();
        $now->setTime(0,0,0);
        $booking_date_from = $this->_getEffectiveBookingDateFrom($discount, $date);
        $booking_date_to = $this->_getEffectiveBookingDateTo($discount, $date);
        if(
            ($now >= $booking_date_from || is_null($booking_date_from)) &&
            ($now <= $booking_date_to || is_null($booking_date_to)) &&
            ($date->departure >= $discount->travel_date_from || is_null($discount->travel_date_from)) &&
            ($date->departure <= $discount->travel_date_to || is_null($discount->travel_date_to))
        ) {
            return true;
        }
        return false;
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
     * @return Option[]
     * @throws Exception
     */
    public function getAllAvailableOptions()
    {
        if(empty($this->_all_available_options)) {
            $this->_all_available_options = Option::listAll(['id_media_object' => $this->getId()]);
        }
        return $this->_all_available_options;
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
            return !empty($v) ? $v : null;
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
        if(!empty($config['data']['search_mongodb']['enabled'])) {
            $Indexer = new Indexer();
            $Indexer->upsertMediaObject($this->getId());
        }
    }

    /**
     * @throws Exception
     */
    public function createOpenSearchIndex(){
        $config = Registry::getInstance()->get('config');
        if(!empty($config['data']['search_opensearch']['search_opensearch']['enabled'])) {
            $OpenSearchIndexer = new \Pressmind\Search\OpenSearch\Indexer();
            $OpenSearchIndexer->upsertMediaObject($this->getId());
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
                                    if(empty($tree->item->name)){
                                        continue;
                                    }
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
                $fulltext_data = FulltextSearch::replaceChars($fulltext_data);
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
     * @param $date_code_ibe
     * @return MediaObject[]
     * @throws Exception
     */
    public static function getByIbeCodeDate($date_code_ibe){
        $Dates = Date::listAll(['code_ibe' => $date_code_ibe]);
        $output = [];
        foreach($Dates as $Date){
            $MediaObject = new MediaObject($Date->id_media_object);
            if(empty($MediaObject->getId())){
                continue;
            }
            $output[] = $MediaObject;
        }
        return $output;
    }

    /**
     * @param $housing_package_code_ibe
     * @return MediaObject[]
     * @throws Exception
     */
    public static function getByIbeCodeHousingPackage($housing_package_code_ibe){
        $HousingPackages = Touristic\Housing\Package::listAll(['code_ibe' => $housing_package_code_ibe]);
        $output = [];
        foreach($HousingPackages as $HousingPackage){
            $MediaObject = new MediaObject($HousingPackage->id_media_object);
            if(empty($MediaObject->getId())){
                continue;
            }
            $output[] = $MediaObject;
        }
        return $output;
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
            'pmt2core_touristic_date_attributes',
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

    /**
     * @param string $id_item
     * @param integer $foreign_id_object_type 123
     * @param string$foreign_field_name 'zielgebiet_default'
     * @return MediaObject[]
     * @throws Exception
     */
    public static function getJoinedMediaObjectsByCategory($id_item, $foreign_id_object_type, $foreign_field_name = null){
        $query = ['id_item' => $id_item, 'id_object_type' => $foreign_id_object_type, 'is_tail' => 1];
        if(!empty($foreign_field_name)){
            $query['var_name'] = $foreign_field_name;
        }
        $Objects = \Pressmind\ORM\Object\MediaObject\DataType\Categorytree::listAll($query);
        $output = [];
        foreach($Objects as $Object){
            $output[] = new MediaObject($Object->id_media_object);
        }
        return $output;
    }

    /**
     * only for internal stats!
     * @return stdClass[]
     * @throws Exception
     */
    public static function getMediaObjectsWithPickupServices(){
        /** @var Pdo $db */
        $db = Registry::getInstance()->get('db');
        $result = $db->fetchAll('select m.id,
                                           m.name,
                                           group_concat(distinct o.id) as id_starting_point_options
                                    from `wp-pm-web-core`.pmt2core_touristic_transports t
                                             left join pmt2core_touristic_startingpoints s on (t.id_starting_point = s.id)
                                             left join pmt2core_touristic_startingpoint_options o on (s.id = o.id_startingpoint)
                                             left join pmt2core_media_objects m on (t.id_media_object = m.id)
                                    where is_pickup_service = 1
                                    group by  m.id');
        $output = [];
        foreach($result as $row) {
            $tmp = new stdClass();
            $tmp->id = $row->id;
            $tmp->name = $row->name;
            $tmp->id_starting_point_options = explode($row->id_starting_point_options);
            $output[] = $tmp;
        }
        return $output;
    }


    /**
     * only for internal stats!
     * @return stdClass[]
     * @throws Exception
     */
    public static function getMediaObjectsWithPickupServicesAndZeroZipRanges(){
        /** @var Pdo $db */
        $db = Registry::getInstance()->get('db');
        $result = $db->fetchAll('select *
                                            from (select m.id,
                                                         m.name,
                                                         o.id as id_option,
                                                         o.name as option_name,
                                                         o.code_ibe as option_code_ibe,
                                                         (select count(*)
                                                          from pmt2core_touristic_startingpoint_options_zip_ranges z
                                                          where z.id_option = o.id) as zip_count
                                                  from `wp-pm-web-core`.pmt2core_touristic_transports t
                                                           left join pmt2core_touristic_startingpoints s on (t.id_starting_point = s.id)
                                                           left join pmt2core_touristic_startingpoint_options o on (s.id = o.id_startingpoint)
                                                           left join pmt2core_media_objects m on (t.id_media_object = m.id)
                                                  where is_pickup_service = 1) a
                                            where zip_count = 0
                                            group by id, id_option');
        $output = [];
        foreach($result as $row) {
            $tmp = new stdClass();
            $tmp->id = $row->id;
            $tmp->name = $row->name;
            $tmp->id_option = $row->id_option;
            $tmp->code_ibe = $row->code_ibe;
            $output[] = $tmp;
        }
        return $output;
    }

    /**
     * only for internal stats!
     * @return stdClass[]
     * @throws Exception
     */
    public static function getMediaObjectByCRSID($crs_id, $property = 'code'){
        /** @var Pdo $db */
        $db = Registry::getInstance()->get('db');
        $config = Registry::getInstance()->get('config');
        if($property === 'code'){
            $query = 'select id_media_object from pmt2core_media_objects where code = "'.$crs_id.'"';
        }else{
            $queries = [];
            foreach ($config['data']['media_types'] as $media_type_id => $media_type_name) {
                $DataType = \Pressmind\ORM\Object\MediaType\Factory::createById($media_type_id);
                if(!$DataType->hasProperty($property)){
                    continue;
                }
                $queries = 'select id_media_object from '.$DataType->getDbTableName().' where '.$property.' = "'.$crs_id.'"';
            }
            if(empty($queries)){
                return [];
            }
            $query = implode(' UNION ', $queries);
        }
        $output = [];
        $result = $db->fetchAll($query);
        foreach($result as $row) {
            $tmp = new stdClass();
            $tmp->id = $row->id;
            $output[] = $tmp;
        }
        return $output;
    }

    /**
     * only for internal stats!
     * @return stdClass[]
     * @throws Exception
     */
    public static function getCheapestPriceCount($id_media_object){
        if(!is_array($id_media_object)){
            $id_media_object = [$id_media_object];
        }
        /** @var Pdo $db */
        $db = Registry::getInstance()->get('db');
        $result = $db->fetchAll('select  id_media_object, 
                                                count(id) as count from pmt2core_cheapest_price_speed
                                        where id_media_object in ('.implode(',', $id_media_object).')
                                            and price_total > 0
                                            and date_departure > NOW()
                                        group by id_media_object');
        $output = [];
        foreach($result as $row) {
            $tmp = new stdClass();
            $tmp->id = $row->id;
            $tmp->count = $row->count;
            $output[] = $tmp;
        }
        return $output;
    }

    /**
     * Returns a list of all extras
     * @param string $agency
     * @param bool $filter_auto_book_zero_price
     * @param bool $filter_auto_book_and_required
     * @return array
     * @throws Exception
     */
    public function getExtraOptions($agency = null, $filter_auto_book_zero_price = false, $filter_auto_book_and_required = true){
        $options = [];
        foreach ($this->booking_packages as $booking_package) {
            foreach ($booking_package->dates as $date) {
                $option_list = $date->getAllOptionsButExcludePriceMixOptions($booking_package->price_mix, true, $agency);
                foreach ($option_list as $option) {
                    if($filter_auto_book_zero_price && !empty($option->auto_book) && $option->price == 0){
                        continue;
                    }
                    if($filter_auto_book_and_required && !empty($option->auto_book) && !empty($option->required)){
                        continue;
                    }
                    $hash = md5(serialize([$option->name]));
                    $options[$hash] = [
                        'name' => $option->name,
                        'description_long' => $option->description_long,
                        'order' => $option->order,
                    ];

                    if(!isset($options[$hash]['prices'])){
                        $options[$hash]['prices'] = [];
                    }
                    $options[$hash]['prices'][] = [
                        'price' =>  $option->price,
                        'departure' => $date->departure,
                        'duration' => $booking_package->duration,
                    ];

                    usort($options[$hash]['prices'], function($a, $b) {
                        return $a['price'] <=> $b['price'];
                    });
                    $options[$hash]['price_range'] = [
                        'min' => $options[$hash]['prices'][array_key_first($options[$hash]['prices'])]['price'],
                        'max' => $options[$hash]['prices'][array_key_last($options[$hash]['prices'])]['price']
                    ];
                }
            }
        }
        usort($options, function($a, $b) {
            return $a['order'] <=> $b['order'];
        });
        return $options;
    }

    /**
     * @return bool
     * @throws Exception
     */
    public function hasOffers(){
        $CheapestPriceSpeed = new CheapestPriceSpeed();
        $r = $CheapestPriceSpeed->loadAll(['id_media_object' => $this->getId()]);
        if(count($r) > 0){
            return true;
        }
        return false;
    }

    /**
     * @return bool
     */
    public function isAPrimaryObject(){
        $config = Registry::getInstance()->get('config');
        return in_array($this->id_object_type, $config['data']['primary_media_type_ids']);
    }

    /**
     * Human friendly validation
     * @param string $prefix
     * @return array
     */
    public function validate(){
        $config = Registry::getInstance()->get('config');
        $result = [];
        $result[] = 'Validation of MediaObject: '.$this->getId().' ('.$this->name.')';
        if($this->isAPrimaryObject()){
            $result[] = '    ✅  Primary Object';
        }else{
            $result[] = '    ✅  Not a primary Object (no extended tests required)';
            return $result;
        }
        if(in_array($this->visibility, $config['data']['media_types_allowed_visibilities'][$this->id_object_type])){
            $result[] = '    ✅  allowed visibility';
        }else{
            $result[] = '    ❌  visibility not allowed ('.$this->visibility.'), allowed id: ('.implode(',', $config['data']['media_types_allowed_visibilities'][$this->id_object_type]).')';
        }
        if(!empty($this->valid_from) && $this->valid_from > (new DateTime())){
            $result[] = '    ❌  not visible yet (valid_from: '.$this->valid_from->format('Y-m-d H:i:s').')';
        }
        if(!empty($this->valid_to) && $this->valid_to < (new DateTime())){
            $result[] = '    ❌  not visible outdated by valid_to '.$this->valid_to->format('Y-m-d H:i:s').')';
        }
        if(count($this->manual_cheapest_prices) > 0){
            $result[] = '     ✅  Manual Cheapest Prices are defined (count: '.count($this->manual_cheapest_prices).')';
        }
        $result[] = '    Validation: CheapestPriceSpeed (Offers)';
        $CheapestPriceSpeed = new CheapestPriceSpeed();
        $r = $CheapestPriceSpeed->loadAll(['id_media_object' => $this->getId()]);
        $count = count($r);
        $result[] = '     '.($count > 0 ? '✅' : '❌') . '  Offers (count: '.$count.')';
        if(((defined('PM_SDK_DEBUG') && PM_SDK_DEBUG) || $count === 0) && !empty(MediaObject::$_insert_cheapest_price_log[$this->id])){
            foreach(MediaObject::$_insert_cheapest_price_log[$this->id] as $log){
                $result[] = '          > '.$log;
            }
        }
        $agency_based_option_and_prices_enabled = !isset($config['data']['touristic']['agency_based_option_and_prices']['enabled']) ? false : $config['data']['touristic']['agency_based_option_and_prices']['enabled'];
        if($agency_based_option_and_prices_enabled){
            $agencies = empty($config['data']['touristic']['agency_based_option_and_prices']['allowed_agencies']) ? null : $config['data']['touristic']['agency_based_option_and_prices']['allowed_agencies'];
            if(empty($agencies)) {
                $result[] = '     ❌  No agencies defined in config.touristic.agency_based_option_and_prices.allowed_agencies but config.touristic.agency_based_option_and_prices.enabled is true';
            }else{
                $agencies_with_listings = [];
                foreach($agencies as $agency){
                    $QueryFilter = new Query\Filter();
                    $QueryFilter->request = ['pm-id' => $this->getId()];
                    $QueryFilter->occupancy = null;
                    Query::$agency_id_price_index = $agency;
                    $r = Query::getResult($QueryFilter);
                    if($r['total_result'] > 0){
                        $agencies_with_listings[] = $agency;
                    }
                }
                Query::$agency_id_price_index = null;
                $result[] = '     '.($agencies_with_listings > 0 ? '✅' : '❌') . '  MongoIndex Results (agencies with listings: '.implode(',', $agencies_with_listings).')';
                $agencies_with_calendar = [];
                foreach($agencies as $agency) {
                    $Filter = new CalendarFilter();
                    $Filter->agency = $agency;
                    $Calendar = $this->getCalendar($Filter);
                    if(!empty($Calendar->calendar)){
                        $agencies_with_calendar[] = $agency;
                    }
                }
                $result[] = '     ' . (count($agencies_with_calendar) > 0 ? '✅' : '❌') . '  Mongo Calendar';
            }
        }else{
            $QueryFilter = new Query\Filter();
            $QueryFilter->request = ['pm-id' => $this->getId()];
            $QueryFilter->occupancy = null;
            $r = Query::getResult($QueryFilter);
            $result[] = '     '.($r['total_result'] > 0 ? '✅' : '❌') . ' MongoIndex Results (count: '.$r['total_result'].')';
            if($r['total_result'] == 0 && !empty($r['mongodb']['aggregation_pipeline_search'])){
                $result[] = '    Mongo Aggregation: '.$r['mongodb']['aggregation_pipeline_search'];
            }
            $Filter = new CalendarFilter();
            $Calendar = $this->getCalendar($Filter);
            $result[] = '     '.(!empty($Calendar->calendar) ? '✅' : '❌') . '  Mongo Calendar';
        }
        $result = array_merge(
            $result,
            $this->validateBookingPackages('    ')
        );
        if(!empty($this->touristic_base)){
            $result = array_merge(
                $result,
                $this->touristic_base->validate('    ')
            );
        }
        $result = array_merge($result, Airline::validate(''));
        $result = array_merge($result, Airport::validate(''));
        $result = array_merge($result, Geodata::validate(''));
        return $result;
    }
    /**
     * Human friendly validation
     * @return array
     * @throws Exception
     */
    public function validateBookingPackages($prefix = ''){
        $result = [];
        $BookingPackage = new Package();
        /**
         * @var Package[] $Packages
         */
        $Packages = $BookingPackage->loadAll(['id_media_object' => $this->getId()]);
        if(count($Packages) == 0) {
            $result[] = $prefix . ' ❌  No Booking Packages found';
        }
        foreach($Packages as $Package){
            $r = $Package->validate($prefix);
            $result[] = $prefix.'Booking Package `'.$Package->name. '` '. $Package->duration. '-days (id: '.$Package->id.') ' ;
            $result = array_merge($result,  $r);
        }
        return $result;
    }

    /**
     * Build the booking linked based on several parameters
     * @param CheapestPriceSpeed $CheapestPriceSpeed
     * @param string $url back reference url to the detail page
     * @param string $dc discount code
     * @param string $booking_type enum(request,option,fix) or null ('fix' is the ib3 default value)
     * @param bool $dont_hide_options prevent hiding the housing option dialog - legacy
     * @return string
     */
    public static function getBookingLink($CheapestPriceSpeed, $url = null, $dc = null, $booking_type = null, $dont_hide_options = false): string
    {
        $p = [];
        $p[] = 'imo='.$CheapestPriceSpeed->id_media_object;
        $p[] = 'idbp='.$CheapestPriceSpeed->id_booking_package;
        $p[] = 'idd='.$CheapestPriceSpeed->id_date;
        if(!empty($CheapestPriceSpeed->id_option)) {
            $p[] = 'iho[' . $CheapestPriceSpeed->id_option . ']=1';
        }
        if($dont_hide_options === true){ // legacy
            $p[] = 'hodh=1';
        }
        if(!empty($CheapestPriceSpeed->transport_type)){
            $p[] = 'idt1='.$CheapestPriceSpeed->id_transport_1;
            $p[] = 'idt2='.$CheapestPriceSpeed->id_transport_2;
            $p[] = 'tt='.$CheapestPriceSpeed->transport_type;
        }
        if(!is_null($dc)){
            $p[] = 'dc='.$dc;
        }
        if(!is_null($booking_type)){
            $p[] = 't='.$booking_type;
        }
        if(!is_null($url)){
            $p[] = 'url='.base64_encode($url);
        }
        $config = Registry::getInstance()->get('config');
        $base_url = !empty($config['ib3']['endpoint']) ? trim($config['ib3']['endpoint'],'/') : '';
        return $base_url.'/?'.implode('&', $p);
    }
}