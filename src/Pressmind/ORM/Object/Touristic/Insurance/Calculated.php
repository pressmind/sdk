<?php
namespace Pressmind\ORM\Object\Touristic\Insurance;

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
     * @var string
     */
    public $code;
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
}
