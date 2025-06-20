<?php
namespace Pressmind\ORM\Object\MediaObject;
use DateTime;
use Pressmind\Log\Writer;
use Pressmind\ORM\Object\AbstractObject;
use Pressmind\ORM\Object\Touristic\Date;
use Pressmind\ORM\Object\Touristic\EarlyBirdDiscountGroup;
use Pressmind\ORM\Object\Touristic\EarlyBirdDiscountGroup\Item;

/**
 * Class ManualDiscount
 * @package Pressmind\ORM\Object\MediaObject
 * @property string $id
 * @property integer $id_media_object
 * @property DateTime $travel_date_from
 * @property DateTime $travel_date_to
 * @property DateTime $booking_date_from
 * @property DateTime $booking_date_to
 * @property string $description
 * @property float $value
 * @property string $type // fixed_price | percent
 * @property string $agency
 */
class ManualDiscount extends AbstractObject
{
    protected $_dont_use_autoincrement_on_primary_key = true;

    protected $_replace_into_on_create = true;

    protected $_definitions = [
        'class' => [
            'name' => self::class
        ],
        'database' => [
            'table_name' => 'pmt2core_media_object_manual_discounts',
            'primary_key' => 'id'
        ],
        'properties' => [
            'id' => [
                'title' => 'Id',
                'name' => 'id',
                'type' => 'string',
                'required' => true,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 32,
                    ],
                ],
                'filters' => NULL,
            ],
            'id_media_object' => [
                'title' => 'id_media_object',
                'name' => 'id_media_object',
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
                'filters' => null,
                 'index' => [
                    'type' => 'index'
                ]
            ],
            'travel_date_from' => [
                'title' => 'travel_date_from',
                'name' => 'travel_date_from',
                'type' => 'datetime',
                'required' => false,
                'validators' => null,
                'filters' => null
            ],
            'travel_date_to' => [
                'title' => 'travel_date_to',
                'name' => 'travel_date_to',
                'type' => 'datetime',
                'required' => false,
                'validators' => null,
                'filters' => null
            ],
            'booking_date_from' => [
                'title' => 'booking_date_from',
                'name' => 'booking_date_from',
                'type' => 'datetime',
                'required' => false,
                'validators' => null,
                'filters' => null
            ],
            'booking_date_to' => [
                'title' => 'booking_date_to',
                'name' => 'booking_date_to',
                'type' => 'datetime',
                'required' => false,
                'validators' => null,
                'filters' => null
            ],
            'description' => [
                'title' => 'description',
                'name' => 'description',
                'type' => 'string',
                'required' => false,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 255,
                    ]
                ],
                'filters' => null
            ],
            'value' => [
                'title' => 'value',
                'name' => 'value',
                'type' => 'float',
                'required' => true,
                'validators' => [
                    [
                        'name' => 'unsigned',
                        'params' => null,
                    ]
                ],
                'filters' => null
            ],
            'type' => [
                'title' => 'type',
                'name' => 'type',
                'type' => 'string',
                'required' => true,
                'validators' => [
                    [
                        'name' => 'inarray',
                        'params' => ['fixed_price', 'percent'],
                    ]
                ],
                'filters' => null
            ],
            'agency' => [
                'title' => 'agency',
                'name' => 'agency',
                'type' => 'string',
                'required' => false,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 22,
                    ]
                ],
                'filters' => null
            ]

        ]
    ];


    /**
     * @param $id_media_object
     * @return void
     */
    public static function convertManualDiscountsToEarlyBird($id_media_object){
        $typeMap = [
            'fixed_price' => 'F',
            'percent' => 'P'
        ];
        /**
         * @var ManualDiscount[] $manualDiscounts
         */
        try{
            $manualDiscounts = self::listAll(['id_media_object' => $id_media_object]);
            if (count($manualDiscounts) > 0) {
                $earlyBirdGroup = new EarlyBirdDiscountGroup();
                $earlyBirdGroup->id = uniqid();
                $earlyBirdGroup->name = 'undefined group name for ' . $id_media_object; // The item.name is relevant, so we have to use a placeholder here
                $earlyBirdGroup->create();
                foreach ($manualDiscounts as $manualDiscount) {
                    $item = new Item();
                    $item->id = uniqid();
                    $item->id_early_bird_discount_group = $earlyBirdGroup->id;
                    $item->travel_date_from = $manualDiscount->travel_date_from;
                    $item->travel_date_to = $manualDiscount->travel_date_to;
                    $item->booking_date_from = $manualDiscount->booking_date_from;
                    $item->booking_date_to = $manualDiscount->booking_date_to;
                    $item->discount_value = $manualDiscount->value;
                    $item->type = $typeMap[$manualDiscount->type];
                    $item->agency = $manualDiscount->agency;
                    $item->origin = 'manual_discount';
                    $item->name = $manualDiscount->description;
                    $item->create();
                }
                $Dates = Date::listAll(['id_media_object' => $id_media_object]);
                foreach ($Dates as $Date) {
                    if(!empty($Date->id_early_bird_discount_group)){
                        echo 'Date ' . $Date->id . ' already has an early bird discount group assigned, skipping.' . PHP_EOL;
                        continue;
                    }
                    $Date->id_early_bird_discount_group = $earlyBirdGroup->getId();
                    $Date->update();
                }
            }
        }catch (Exception $e) {
            Writer::write('Error converting manual discounts to early bird discounts for media object ' . $id_media_object . ': ' . $e->getMessage(), WRITER::OUTPUT_BOTH, 'touristic_data', WRITER::TYPE_INFO);
        }
    }

}
