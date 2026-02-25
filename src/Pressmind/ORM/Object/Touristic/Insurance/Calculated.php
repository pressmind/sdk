<?php
namespace Pressmind\ORM\Object\Touristic\Insurance;

use Pressmind\ORM\Object\Touristic\Insurance;

/**
 * Class Calculated
 */
class Calculated
{
    /**
     * @var integer
     */
    public $id;
    /**
     * var boolean
     */
    public $active;
    /**
    * var string
    */
    public $name;
    /**
    * var string
    */
    public $description;
    /**
    * var string
    */
    public $description_long;
    /**
    * var string
    */
    public $duration_max_days;
    /**
    * var string
    */
    public $worldwide;
    /**
    * var string
    */
    public $is_additional_insurance;
    /**
    * var string
    */
    public $urlinfo;
    /**
    * var string
    */
    public $urlproduktinfo;
    /**
    * var string
    */
    public $urlagb;
    
    /**
     * @var string
     */
    public $code;
    /**
     * @var string
     */
    public $code_price;
    /**
     * @var string
     */
    public $code_ibe;
    /**
     * @var float
     */
    public $price;

    /**
     * @var bool
     */
    public $family_insurance;

    /**
     * @var integer
     */
    public $pax_max;

    /**
     * @var integer
     */
    public $pax_min;

    /**
     * @var boolean
     */
    public $is_recommendation;

    /**
     * @var integer
     */
    public $priority;

    /**
     * @var Insurance[]
     */
    public $additional_insurances;
}
