<?php

namespace Pressmind\REST\Controller;

use Exception;
use Pressmind\HelperFunctions;
use Pressmind\Registry;
use Pressmind\Search\Query;
use Pressmind\Search\Query\Filter;
use Pressmind\Tools\PriceHandler;
use stdClass;

class Catalog
{
    public function index($params){
        $config = Registry::getInstance()->get('config');
        $controller_config = !empty($config['rest']['server']['controller']['catalog']) ? $config['rest']['server']['controller']['catalog'] : null;
        $is_initial = empty($params);
        try{
            $new_pm_c = [];
            $category_level = [];
            if(isset($params['pm-c'])){
                foreach($params['pm-c'] as $key => $id_item){
                    $level = 0;
                    preg_match('/^([A-Za-z0-9\-]+)-AL([0-9]+)$/', $id_item, $matches);
                    if(count($matches) > 0 && isset($matches[2])){
                        $level = $matches[2];
                        $id_item = $matches[1];
                    }
                    $new_pm_c[$key] = $id_item;
                    $category_level[$id_item] = $level;
                }
                $params['pm-c'] = $new_pm_c;
            }
            $Filter = new Filter();
            $Filter->request = $params;
            $Filter->page_size = 1000;
            $Filter->getFilters = true;
            $Filter->returnFiltersOnly = false;
            Query::$calendar_show_departures = true;
            if(!empty($params['pm-dr'])){
                $Filter->request['pm-o'] = 'date_departure-asc';
                $Filter->output = 'date_list';
            }
            $result = Query::getResult($Filter);
        }catch (Exception $e){
            return $e->getMessage();
        }
        $filters = [];
        foreach($result['categories'] as $category_name => $categories){
            $name = $category_name;
            if(!empty($controller_config['categories'])) {
                $found = false;
                foreach ($controller_config['categories'] as $configured_category) {
                    if ($configured_category['var_name'] == $category_name) {
                        $name = $configured_category['title'];
                        $found = true;
                    }
                }
                if ($found === false) {
                    continue;
                }
            }
            $filter = new stdClass();
            $filter->name = $name;
            $filter->items = [];
            $level = 0;
            if(!empty($params['pm-c'][$category_name])){
                $request_id_item = $params['pm-c'][$category_name];
                $level = $category_level[$request_id_item];
            }
            $categories = isset($categories[$level]) ? array_values($categories[$level]) : [];
            $product_count = 0;
            foreach($categories as $category){
                /**
                 * no items, no filter item
                 */
                if(!$is_initial && $category->count_in_search == 0){
                    continue;
                }
                $item = new stdClass();
                $item->id = $category->id_item;
                $query = $params;
                $query['pm-c'][$category_name] = $category->id_item.'-AL'.($category->level + 1);
                $item->url = urldecode(http_build_query($query));
                $item->name = $category->name;
                $item->count_in_system = $category->count_in_system;
                $item->count_in_search = $category->count_in_search;
                $product_count += $is_initial ? $category->count_in_system : $category->count_in_search;
                $filter->items[] = $item;
            }
            if($product_count > 20 && count($filter->items) > 1){
                $filters[] = $filter;
            }
        }
        /**
         * filter by month
         */
        $month_filter = new stdClass();
        $month_filter->name = "Reisezeitraum";
        $month_filter->items = [];
        $product_count = 0;
        foreach($result['months'] as $month){
            $date_range_str = $month->date->format('Ymd').'-'.$month->date->format('Ymt');
            $item = new stdClass();
            $item->id = $date_range_str;
            $query = $_GET;
            $query['pm-dr'] = $date_range_str;
            $item->url = urldecode(http_build_query($query));
            $item->name = HelperFunctions::monthNumberToLocalMonthName($month->date->format('n'));
            if($month->date->format('Y') != date('Y')){
                $item->name .= ' ' . $month->date->format('Y');
            }
            $item->count_in_system = $month->count_in_system;
            $item->count_in_search = $month->count_in_search;
            $product_count += $is_initial ? $month->count_in_system : $month->count_in_search;
            $month_filter->items[] = $item;
        }
        if($product_count > 20 && count($filter->items) > 1){
            $filters[] = $month_filter;
        }
        /**
         * products: display some products if filter items exists, if no filter items exists, display all products
         */
        $products = [];
        foreach($result['items'] as $item){
            $product = new stdClass();
            $product->id = $item['id_media_object'];
            $product->preline = !empty($item['cheapest_price']->date_departures[0]) ? $item['cheapest_price']->date_departures[0]->format('d.m.Y') : null;
            $product->name = html_entity_decode(strip_tags($item['headline']));
            $product->image = !empty($item['image']['url']) ? $item['image']['url'] : null;
            $product->priceBefore = null;
            $product->priceLabel = null;
            $product->labelColorType = 'tertiary';
            $Discount = PriceHandler::getDiscount($item['cheapest_price']);
            if($Discount !== false){
                $product->priceLabel = !empty($Discount->name) ? $Discount->name : null;
                $product->priceLabel = html_entity_decode($product->priceLabel);
                $product->priceBefore = html_entity_decode($Discount->price_before_discount);
            }
            $product->priceSuffix = null;
            $product->pricePrefix = "ab";
            $product->price = !empty($item['cheapest_price']->price_total) ? html_entity_decode(PriceHandler::format($item['cheapest_price']->price_total)) : null;
            $products[] = $product;
        }
        /**
         * initial feed (startpage), not filtered
         */
        if($is_initial){
            $feed = new stdClass();
            $feed->sections = [];
            if(count($filters) === 0){
                $section = new stdClass();
                $section->name = 'Angebote';
                $section->products = $products;
                $feed->sections[] = $section;
            }else{
                $section = new stdClass();
                $section->name = null;
                $section->filters = $filters;
                $feed->sections[] = $section;
                $Filter1 = new Filter();
                $Filter1->page_size = 5;
                $Filter1->getFilters = false;
                $Filter1->returnFiltersOnly = false;
                $Filter1->request['pm-o'] = 'rand';
                $Filter1->output = 'date_list';
                $result1= Query::getResult($Filter1);
                foreach($result1['items'] as $item){
                    $product = new stdClass();
                    $product->id = $item['id_media_object'];
                    $product->preline = !empty($item['cheapest_price']->date_departures[0]) ? $item['cheapest_price']->date_departures[0]->format('d.m.Y') : null;
                    $product->name = html_entity_decode(strip_tags($item['headline']));
                    $product->image = !empty($item['image']['url']) ? $item['image']['url'] : null;
                    $product->priceBefore = null;
                    $product->priceLabel = null;
                    $product->labelColorType = 'tertiary';
                    $Discount = PriceHandler::getDiscount($item['cheapest_price']);
                    if($Discount !== false){
                        $product->priceLabel = !empty($Discount->name) ? $Discount->name : null;
                        $product->priceLabel = html_entity_decode($product->priceLabel);
                        $product->priceBefore = html_entity_decode($Discount->name);
                    }
                    $product->priceSuffix = null;
                    $product->pricePrefix = "ab";
                    $product->price = !empty($item['cheapest_price']->price_total) ? html_entity_decode(PriceHandler::format($item['cheapest_price']->price_total)) : null;
                    $products[] = $product;
                }
                $section = new stdClass();
                $section->name = 'Aktuelle Empfehlungen';
                $section->products = $products;
                $feed->sections[] = $section;

            }
            return $feed;
        }
        if(!$is_initial){
            $feed = new stdClass();
            $feed->sections = [];
            if(count($filters) === 0){
                $section = new stdClass();
                $section->name = 'Angebote';
                $section->sort = [];
                $sort = new stdClass();
                $sort->label = 'Beliebt';
                $sort->url = 'pm-o=priority';
                $sort->active = !empty($result['order']) && $result['order'] == 'priority';
                $section->sort[] = $sort;
                $sort = new stdClass();
                $sort->label = 'Preis aufsteigend';
                $sort->url = 'pm-o=price-asc';
                $sort->active = !empty($result['order']) && $result['order'] == 'price-asc';
                $section->sort[] = $sort;
                $sort = new stdClass();
                $sort->label = 'Preis absteigend';
                $sort->url = 'pm-o=price-desc';
                $sort->active = !empty($result['order']) && $result['order'] == 'price-desc';
                $section->sort[] = $sort;
                $sort = new stdClass();
                $sort->label = 'Abreise aufsteigend';
                $sort->url = 'pm-o=date_departure-asc';
                $sort->active = !empty($result['order']) && $result['order'] == 'date_departure-asc';
                $section->sort[] = $sort;
                $sort = new stdClass();
                $sort->label = 'Abreise absteigend';
                $sort->url = 'pm-o=date_departure-desc';
                $sort->active = !empty($result['order']) && $result['order'] == 'date_departure-desc';
                $section->sort[] = $sort;
                $section->products = $products;
                $feed->sections[] = $section;
            }else{
                // filter
                $section = new stdClass();
                $section->name = null;
                $section->filters = $filters;
                $feed->sections[] = $section;
                $section = new stdClass();
                $section->name = 'Highlights';
                $section->products = array_slice($products, 0, 5);;
                $feed->sections[] = $section;
            }
            return $feed;
        }
    }

    public function search($params){
        try {
            $Filter = new Filter();
            $Filter->request = $params;
            $Filter->page_size = 1000;
            $Filter->getFilters = true;
            $Filter->returnFiltersOnly = false;
            Query::$calendar_show_departures = true;
            if (!empty($params['pm-dr'])) {
                $Filter->request['pm-o'] = 'date_departure-asc';
                $Filter->output = 'date_list';
            }
            $result = Query::getResult($Filter);
        } catch (Exception $e) {
            return $e->getMessage();
        }
       return $result;
    }

    public function listAll($params){
        return $this->index($params);
    }

    /**
     * @param $params
     * @return null[]
     */
    public function convertSearchQueryToPriceQueryString($params)
    {
        if(empty($params['search_query'])) {
            return [
                "price_query" => null,
            ];
        }
        $search_query = $params['search_query'];
        $query = [];
        parse_str($search_query, $query);
        $valid_params = [];
        if (empty($query['pm-dr']) === false) {
            $dateRange = Query::extractDaterange($query['pm-dr']);
            if ($dateRange !== false) {
                $valid_params['pm-dr'] = $query['pm-dr'];
            }
        }
        if (empty($query['pm-du']) === false) {
            $durationRange = Query::extractDurationRange($query['pm-du']);
            if ($durationRange !== false) {
                $valid_params['pm-du'] = $query['pm-du'];
            }
        }
        if (empty($query['pm-pr']) === false) {
            $priceRange = Query::extractPriceRange($query['pm-pr']);
            if ($priceRange !== false) {
                $valid_params['pm-pr'] = $query['pm-pr'];
            }
        }
        if (empty($query['pm-tr']) === false) {
            $transport_types = Query::extractTransportTypes($query['pm-tr']);
            if (!empty($transport_types)) {
                $valid_params['pm-tr'] = $query['pm-tr'];
            }
        }
        return [
            "price_query" => !empty($valid_params) ? http_build_query($valid_params) : null,
        ];
    }
}