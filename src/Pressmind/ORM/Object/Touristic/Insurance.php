<?php

namespace Pressmind\ORM\Object\Touristic;

use DateTime;
use Pressmind\ORM\Object\AbstractObject;
use Pressmind\ORM\Object\Touristic\Insurance\Attribute;
use Pressmind\ORM\Object\Touristic\Insurance\Calculated;
use Pressmind\ORM\Object\Touristic\Insurance\InsuranceToAttribute;
use Pressmind\ORM\Object\Touristic\Insurance\InsuranceToInsurance;
use Pressmind\ORM\Object\Touristic\Insurance\InsuranceToPriceTable;
use Pressmind\ORM\Object\Touristic\Insurance\PriceTable;

/**
 * Class Insurance
 * @property string $id
 * @property boolean $active
 * @property string $name
 * @property string $description
 * @property string $description_long
 * @property integer $duration_max_days // deprecated
 * @property boolean $worldwide
 * @property boolean $is_additional_insurance
 * @property string $urlinfo
 * @property string $urlproduktinfo
 * @property string $urlagb
 * @property integer $pax_min
 * @property integer $pax_max
 * @property string $code
 * @property PriceTable[] $price_tables
 * @property Insurance[] $sub_insurances
 * @property string $own_contribution
 * @property Attribute[] $attributes
 */
class Insurance extends AbstractObject
{
    protected $_dont_use_autoincrement_on_primary_key = true;

    protected $_replace_into_on_create = true;

    protected $_definitions = array(
        'class' => [
            'name' => self::class,
        ],
        'database' =>
            array(
                'table_name' => 'pmt2core_touristic_insurances',
                'primary_key' => 'id',
            ),
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
            'active' => [
                'title' => 'Active',
                'name' => 'active',
                'type' => 'boolean',
                'required' => false,
                'validators' => NULL,
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
            'description' => [
                'title' => 'Description',
                'name' => 'description',
                'type' => 'string',
                'required' => false,
                'validators' => NULL,
                'filters' => NULL,
            ],
            'description_long' => [
                'title' => 'Description_long',
                'name' => 'description_long',
                'type' => 'string',
                'required' => false,
                'filters' => NULL,
            ],
            'duration_max_days' => [
                'title' => 'Duration_max_days',
                'name' => 'duration_max_days',
                'type' => 'integer',
                'required' => false,
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
            ],
            'worldwide' => [
                'title' => 'Worldwide',
                'name' => 'worldwide',
                'type' => 'boolean',
                'required' => false,
                'validators' => NULL,
                'filters' => NULL,
            ],
            'is_additional_insurance' => [
                'title' => 'Is_additional_insurance',
                'name' => 'is_additional_insurance',
                'type' => 'boolean',
                'required' => false,
                'validators' => NULL,
                'filters' => NULL,
            ],
            'urlinfo' => [
                'title' => 'Urlinfo',
                'name' => 'urlinfo',
                'type' => 'string',
                'required' => false,
                'validators' => NULL,
                'filters' => NULL,
            ],
            'urlproduktinfo' => [
                'title' => 'Urlproduktinfo',
                'name' => 'urlproduktinfo',
                'type' => 'string',
                'required' => false,
                'validators' => NULL,
                'filters' => NULL,
            ],
            'urlagb' => [
                'title' => 'Urlagb',
                'name' => 'urlagb',
                'type' => 'string',
                'required' => false,
                'validators' => NULL,
                'filters' => NULL,
            ],
            'pax_min' => [
                'title' => 'Pax_min',
                'name' => 'pax_min',
                'type' => 'integer',
                'required' => false,
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
            ],
            'pax_max' => [
                'title' => 'Pax_max',
                'name' => 'pax_max',
                'type' => 'integer',
                'required' => false,
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
            'price_tables' => [
                'name' => 'price_tables',
                'title' => 'price_tables',
                'type' => 'relation',
                'required' => false,
                'validators' => NULL,
                'filters' => NULL,
                'relation' => [
                    'type' => 'ManyToMany',
                    'class' => PriceTable::class,
                    'relation_table' => 'pmt2core_touristic_insurance_to_price_table',
                    'relation_class' => InsuranceToPriceTable::class,
                    'related_id' => 'id_insurance',
                    'target_id' => 'id_price_table',
                ],
            ],
            'attributes' => [
                'name' => 'attributes',
                'title' => 'attributes',
                'type' => 'relation',
                'required' => false,
                'validators' => NULL,
                'filters' => NULL,
                'relation' => [
                    'type' => 'ManyToMany',
                    'class' => PriceTable::class,
                    'relation_table' => 'pmt2core_touristic_insurance_to_attributes',
                    'relation_class' => InsuranceToAttribute::class,
                    'related_id' => 'id_insurance',
                    'target_id' => 'id_attributes',
                ],
            ],
            'sub_insurances' => [
                'name' => 'sub_insurances',
                'title' => 'sub_insurances',
                'type' => 'relation',
                'required' => false,
                'validators' => NULL,
                'filters' => NULL,
                'relation' => [
                    'type' => 'ManyToMany',
                    'class' => Insurance::class,
                    'relation_table' => 'pmt2core_touristic_insurance_to_insurance',
                    'relation_class' => InsuranceToInsurance::class,
                    'related_id' => 'id_insurance',
                    'target_id' => 'id_sub_insurance',
                ],
            ],
            'own_contribution' => [
                'title' => 'own_contribution',
                'name' => 'own_contribution',
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
        ]
    );

    /**
     * Will check if this insurance is available for the given traveldates, travelprice, travelduration and the person (by age),
     * will return lowest price (float) if available, otherwise false. If price_type of pricetable is 'percent' price
     * will be automatically calculated based on $travelPrice
     * @TODO price_type also defines type 'full_price' -> find out what that's for!!!
     * @param DateTime $dateStart
     * @param DateTime $dateEnd
     * @param float $travelPrice
     * @param integer $duration
     * @param integer $personAge
     * @param integer $total_number_of_persons
     * @return boolean|Calculated
     */
    public function isAvailableForTravelDateAndPriceAndPersonAge($dateStart, $dateEnd, $travelPrice, $duration, $personAge = 18, $total_number_of_persons = 0, $check_additional = false)
    {
        if($this->getId() == 0) { //If a default insurance (no insurance wanted) is given (id will be 0) we need to return a default object, so that the price can be displayed
            $calculated_insurance = new Calculated();
            $calculated_insurance->name = $this->name;
            $calculated_insurance->description = $this->description;
            $calculated_insurance->description_long = $this->description_long;
            $calculated_insurance->active = $this->active;
            $calculated_insurance->duration_max_days = $this->duration_max_days;
            $calculated_insurance->worldwide = $this->worldwide;
            $calculated_insurance->is_additional_insurance = $this->is_additional_insurance;
            $calculated_insurance->urlinfo = $this->urlinfo;
            $calculated_insurance->urlproduktinfo = $this->urlproduktinfo;
            $calculated_insurance->urlagb = $this->urlagb;
            $calculated_insurance->code = 'Default';
            $calculated_insurance->code_price = null;
            $calculated_insurance->code_ibe = null;
            $calculated_insurance->family_insurance = null;
            $calculated_insurance->pax_min = 0;
            $calculated_insurance->pax_max = 0;
            $calculated_insurance->price = 0.00;
            return $calculated_insurance;
        }
        $matches = array();
        try {
            if($this->is_additional_insurance == 0 || $check_additional == true) {
                $now = new DateTime();
                foreach ($this->price_tables as $pricetable) {
                    /*echo '-- ' . $this->name . '--' . "\n";
                    echo 'age_from:' . print_r(($pricetable->age_from <= $personAge || $pricetable->age_from == 0 || $personAge == null), true). "\n";
                    echo 'age_to:' . print_r(($pricetable->age_to >= $personAge || $pricetable->age_to == 0 || $personAge == null), true). "\n";
                    echo 'travel_date_from:' . print_r(($pricetable->travel_date_from <= $dateStart || is_null($pricetable->travel_date_from)), true). "\n";
                    echo 'travel_date_to:' . print_r(($pricetable->travel_date_to >= $dateEnd || is_null($pricetable->travel_date_to)), true). "\n";
                    echo 'travel_duration_from:' . print_r( ($pricetable->travel_duration_from <= $duration || $pricetable->travel_duration_from == 0), true). "\n";
                    echo 'travel_duration_to:' . print_r( ($pricetable->travel_duration_to >= $duration || $pricetable->travel_duration_to == 0), true). "\n";
                    echo 'travel_price_min:' . print_r( ($pricetable->travel_price_min <= $travelPrice || $pricetable->travel_price_min == 0), true). "\n";
                    echo 'travel_price_max:' . print_r( ($pricetable->travel_price_max >= $travelPrice || $pricetable->travel_price_max == 0), true). "\n";
                    echo 'booking_date_from:' . print_r( ($pricetable->booking_date_from <= $now || is_null($pricetable->booking_date_from)), true). "\n";
                    echo 'booking_date_to:' . print_r( ($pricetable->booking_date_to >= $now || is_null($pricetable->booking_date_to)), true). "\n";
                    echo 'pax_min:' . print_r( ($pricetable->pax_min <= $total_number_of_persons || $pricetable->pax_min == 0 || $total_number_of_persons == null), true). "\n";
                    echo 'pax_max:' . print_r( ($pricetable->pax_max >= $total_number_of_persons || $pricetable->pax_max == 0 || $total_number_of_persons == null), true). "\n";*/
                    if (
                        ($pricetable->age_from <= $personAge || $pricetable->age_from == 0 || $personAge == null)
                        && ($pricetable->age_to >= $personAge || $pricetable->age_to == 0 || $personAge == null)
                        && ($pricetable->travel_date_from <= $dateStart || is_null($pricetable->travel_date_from))
                        && ($pricetable->travel_date_to >= $dateEnd || is_null($pricetable->travel_date_to))
                        && ($pricetable->travel_duration_from <= $duration || $pricetable->travel_duration_from == 0)
                        && ($pricetable->travel_duration_to >= $duration || $pricetable->travel_duration_to == 0)
                        && ($pricetable->travel_price_min <= $travelPrice || $pricetable->travel_price_min == 0)
                        && ($pricetable->travel_price_max >= $travelPrice || $pricetable->travel_price_max == 0)
                        && ($pricetable->booking_date_from <= $now || is_null($pricetable->booking_date_from))
                        && ($pricetable->booking_date_to >= $now || is_null($pricetable->booking_date_to))
                        && ($pricetable->pax_min <= $total_number_of_persons || $pricetable->pax_min == 0 || $total_number_of_persons == null)
                        && ($pricetable->pax_max >= $total_number_of_persons || $pricetable->pax_max == 0 || $total_number_of_persons == null)
                    ) {
                        $matches[] = array($pricetable->price_per_person, $pricetable->price_type, $pricetable);
                    }
                }
                if (count($matches) > 0) {
                    usort($matches, array($this, 'cmp'));
                    $price = $matches[0][0];
                    //@TODO Percent is currently not supported by pressmind, must be ignored
                    /*if ($matches[0][1] == 'percent') {
                        $price = bcmul(bcdiv($travelPrice, 100, 4), $price, 4);
                    }*/
                    $calculated_insurance = new Calculated();
                    $calculated_insurance->id = $this->getId();
                    $calculated_insurance->name = $this->name;
                    $calculated_insurance->description = $this->description;
                    $calculated_insurance->description_long = $this->description_long;
                    $calculated_insurance->active = $this->active;
                    $calculated_insurance->duration_max_days = $this->duration_max_days;
                    $calculated_insurance->worldwide = $this->worldwide;
                    $calculated_insurance->is_additional_insurance = $this->is_additional_insurance;
                    $calculated_insurance->urlinfo = $this->urlinfo;
                    $calculated_insurance->urlproduktinfo = $this->urlproduktinfo;
                    $calculated_insurance->urlagb = $this->urlagb;
                    $calculated_insurance->code_price = $matches[0][2]->code;
                    $calculated_insurance->code = $this->code;
                    $calculated_insurance->code_ibe = $matches[0][2]->code_ibe;
                    $calculated_insurance->family_insurance = $matches[0][2]->family_insurance;
                    $calculated_insurance->pax_min = $matches[0][2]->pax_min;
                    $calculated_insurance->pax_max = $matches[0][2]->pax_max;
                    $calculated_insurance->price = floatval(bcmul($price, 1, 2));
                    $calculated_insurance->sub_insurances = [];
                    if(is_array($this->sub_insurances)) {
                        foreach ($this->sub_insurances as $sub_insurance) {
                            if($calculated_sub_insurance = $sub_insurance->isAvailableForTravelDateAndPriceAndPersonAge($dateStart, $dateEnd, $travelPrice, $duration, $personAge, $total_number_of_persons, true)) {
                                $calculated_insurance->sub_insurances[] = $calculated_sub_insurance;
                            }
                        }
                    }
                    return $calculated_insurance;
                }
            }
        } catch (\Exception $e) {
            echo $e->getMessage();
        }
        return false;
    }
}
