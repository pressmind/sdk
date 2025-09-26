<?php

namespace Pressmind\Tools;

use Pressmind\ORM\Object\CheapestPriceSpeed;
use Pressmind\ORM\Object\DisplayPrice;
use Pressmind\ORM\Object\Touristic\Option\Discount\Scale;
use Pressmind\Registry;

final class PriceHandler
{
    /**
     * Normalizes different offer types for display purposes
     * @param CheapestPriceSpeed $CheapestPrice
     * @param string $earlybird_name
     * @param string $pseudo_price_name
     * @return false|DisplayPrice
     */
    public static function getDiscount($CheapestPrice, string $earlybird_name = 'Frühbucher',  string $pseudo_price_name = 'Ihr Vorteil'): DisplayPrice|false
    {
        $DisplayPrice = new DisplayPrice();
        if (!empty($CheapestPrice->earlybird_discount)) {
            $DisplayPrice->price_before_discount = self::format($CheapestPrice->price_regular_before_discount);
            $DisplayPrice->price_delta = '-' . $CheapestPrice->earlybird_discount . '%';
            $DisplayPrice->valid_to = $CheapestPrice->earlybird_discount_date_to;
            $DisplayPrice->name = empty($CheapestPrice->earlybird_name) ? $earlybird_name : $CheapestPrice->earlybird_name;
            $DisplayPrice->type = 'earlybird';
            return $DisplayPrice;
        }
        if (!empty($CheapestPrice->earlybird_discount_f)) {
            $DisplayPrice->price_before_discount = self::format($CheapestPrice->price_regular_before_discount);
            $DisplayPrice->price_delta = '-' . self::format($CheapestPrice->earlybird_discount_f);
            $DisplayPrice->valid_to = $CheapestPrice->earlybird_discount_date_to;
            $DisplayPrice->name = empty($CheapestPrice->earlybird_name) ? $earlybird_name : $CheapestPrice->earlybird_name;
            $DisplayPrice->type = 'earlybird';
            return $DisplayPrice;
        }
        if (!empty($CheapestPrice->price_option_pseudo) &&
            $CheapestPrice->price_option_pseudo > $CheapestPrice->price_total && (float)$CheapestPrice->price_option_pseudo !== 0.0) {
            $percent_discount = ($CheapestPrice->price_option_pseudo ?? 0) > 0
                ? (int) round(
                    ( ( (float)$CheapestPrice->price_option_pseudo - (float)$CheapestPrice->price_total )
                        / (float)$CheapestPrice->price_option_pseudo ) * 100.0,
                    0,
                    PHP_ROUND_HALF_UP
                )
                : 0;
            $DisplayPrice->price_before_discount = self::format($CheapestPrice->price_option_pseudo);
            $DisplayPrice->price_delta = '-' . $percent_discount . '%';
            $DisplayPrice->valid_to = null;
            $DisplayPrice->name = $pseudo_price_name;
            $DisplayPrice->type = 'pseudo';
            return $DisplayPrice;
        }
        return false;
    }

    /**
     * @param float $price
     * @param string $locale
     * @return string
     */
    public static function format($price, $locale = 'de') : string
    {
        $config = Registry::getInstance()->get('config');
        $decimals = !empty($config['price_format'][$locale]['decimals']) ? $config['price_format'][$locale]['decimals'] : 2;
        $decimal_separator = !empty($config['price_format'][$locale]['decimal_separator']) ? $config['price_format'][$locale]['decimal_separator'] : ',';
        $thousands_separator = !empty($config['price_format'][$locale]['thousands_separator']) ? $config['price_format'][$locale]['thousands_separator'] : '.';
        $position = !empty($config['price_format'][$locale]['position']) ? $config['price_format'][$locale]['position'] : 'RIGHT';
        $currency = !empty($config['price_format'][$locale]['currency']) ? $config['price_format'][$locale]['currency'] : '€';
        $price = number_format($price, $decimals, $decimal_separator, $thousands_separator);
        if ($position == 'LEFT') {
            return $currency . '&nbsp;' . $price;
        }
        return $price . '&nbsp;' . $currency;
    }


    /**
     * Returns a compressed information string about the maximum discount
     * Example return value: "Kinderrabatt: 0‑2 Jahre: bis zu 100%; 2‑13 Jahre: bis zu 8%"
     *
     * @TODO
     * - this example can not handle fixed vs. percent discounts at this moment
     * @param Scale[] $OptionDiscountScales
     * @return string
     */
    public static function getCheapestOptionDiscount($OptionDiscountScales) : string
    {
        $today = new \DateTime();
        $group = [];
        foreach ($OptionDiscountScales as $Scale) {
            if ($today < $Scale->valid_from || $today > $Scale->valid_to) { // not valid
                continue;
            }
            if (empty($group[$Scale->age_from . '-' . $Scale->age_to])) { // if is not set
                $group[$Scale->age_from . '-' . $Scale->age_to] = $Scale;
            } elseif ($group[$Scale->age_from . '-' . $Scale->age_to]->value < $Scale->value) { // if is bigger discount
                $group[$Scale->age_from . '-' . $Scale->age_to] = $Scale;
            }
        }
        ksort($group);
        $age_group = [];
        foreach ($group as $Scale) {
            $str = $Scale->age_from . '&#8209;' . $Scale->age_to . '&nbsp;Jahre: bis zu ';
            if ($Scale->type == 'P') {
                $str .= str_replace('.', ',', $Scale->value) . '%';
            } elseif ($Scale->type == 'F') {
                $str .= self::format($Scale->value);
            } else { // not known, continue
                continue;
            }
            if ($Scale->age_to <= 17) {
                $age_group['childs'][] = $str;
            } else {
                $age_group['others'][] = $str;
            }
        }
        $output = '';
        if (!empty($age_group['childs'])) {
            $output .= 'Kinderrabatt: ' . implode('; ', $age_group['childs']);
        }
        if (!empty($age_group['others'])) {
            $output .= 'Weitere altersbezogene Rabatte: ' . implode('; ', $age_group['others']);
        }
        return $output;
    }
}