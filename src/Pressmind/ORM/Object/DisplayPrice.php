<?php

namespace Pressmind\ORM\Object;

class DisplayPrice
{
    public ?string $price_before_discount;
    public ?string $price_delta;
    public ?\DateTime $valid_to;
    public ?string $name;
    /**
     * @var string (earlybird,pseudo)
     */
    public string $type;
}