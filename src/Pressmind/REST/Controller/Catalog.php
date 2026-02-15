<?php

namespace Pressmind\REST\Controller;

use DateTime;
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
                if(empty($params['pm-o'])){
                    $Filter->request['pm-o'] = 'date_departure-asc';
                }
                $Filter->output = 'date_list';
            }
            $result = Query::getResult($Filter);
        }catch (Exception $e){
            return [
                'error' => true,
                'payload' => null,
                'msg' => $e->getMessage()
            ];
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
            $query = $params;
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
        if(count($month_filter->items) > 1) {
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
                        $product->priceBefore = html_entity_decode($Discount->price_before_discount);
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
            return [
                'error' => false,
                'payload' => $feed,
                'msg' => null
            ];
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
            return [
                'error' => false,
                'payload' => $feed,
                'msg' => null
            ];
        }
    }

    public function search($params){
        try {
            $Filter = new Filter();
            $Filter->request = $params;
            $Filter->getFilters = !empty($params['get_filter']);
            $Filter->returnFiltersOnly = false;
            Query::$calendar_show_departures = true;
            if (!empty($params['pm-dr'])) {
                if (empty($params['pm-o'])) {
                    $Filter->request['pm-o'] = 'date_departure-asc';
                }
                //$Filter->output = 'date_list';
            }
            $result = Query::getResult($Filter);
        } catch (Exception $e) {
            return [
                'error' => true,
                'payload' => null,
                'msg' => $e->getMessage()
            ];
        }
        return [
            'error' => false,
            'payload' => $result,
            'msg' => null
        ];
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
                'error' => false,
                'payload' => ["price_query" => null],
                'msg' => null
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
            'error' => false,
            'payload' => ["price_query" => !empty($valid_params) ? http_build_query($valid_params) : null],
            'msg' => null
        ];
    }

    /**
     * Returns a compact suggestion index for client-side autocomplete/search.
     * Entities: categories (trees), products, departures (date + product name), months.
     * Each item includes pm-search parameters as metadata.
     * Supports application-level ETag via ?etag= parameter.
     *
     * @param $params
     * @return array
     */
    public function suggestion($params)
    {
        $config = Registry::getInstance()->get('config');
        $controller_config = !empty($config['rest']['server']['controller']['catalog']) ? $config['rest']['server']['controller']['catalog'] : null;

        try {
            /**
             * Query 1: Categories (MongoDB, filter-only)
             */
            $FilterOnly = new Filter();
            $FilterOnly->page_size = 10000;
            $FilterOnly->getFilters = true;
            $FilterOnly->returnFiltersOnly = true;
            $resultFilter = Query::getResult($FilterOnly);

            /**
             * Query 2: Products (MongoDB, items-only)
             */
            $FilterItems = new Filter();
            $FilterItems->request = ['pm-l' => '1,10000'];
            $FilterItems->page_size = 10000;
            $FilterItems->getFilters = false;
            $FilterItems->returnFiltersOnly = false;
            $resultItems = Query::getResult($FilterItems);

            /**
             * Query 3: Departures (SQL, from cheapest_price_speed - only bookable dates)
             * Respects date_filter config (offset, max_date_offset)
             */
            $db = Registry::getInstance()->get('db');
            $date_offset = 0;
            $max_date_offset = 730;
            if (!empty($config['data']['touristic']['date_filter']['active'])) {
                $date_offset = empty($config['data']['touristic']['date_filter']['offset']) ? 0 : (int)$config['data']['touristic']['date_filter']['offset'];
                $max_date_offset = empty($config['data']['touristic']['date_filter']['max_date_offset']) ? 730 : (int)$config['data']['touristic']['date_filter']['max_date_offset'];
            }
            $date_from = new DateTime();
            $date_from->setTime(0, 0, 0);
            $date_from->modify($date_offset . ' days');
            $date_to = new DateTime();
            $date_to->setTime(23, 59, 59);
            $date_to->modify($max_date_offset . ' days');

            $departures_raw = $db->fetchAll(
                "SELECT DISTINCT
                    cps.id_media_object,
                    DATE_FORMAT(cps.date_departure, '%d.%m.') as dep_label,
                    DATE_FORMAT(cps.date_departure, '%Y%m%d') as dep_id,
                    mo.name as headline
                FROM pmt2core_cheapest_price_speed cps
                JOIN pmt2core_media_objects mo ON mo.id = cps.id_media_object
                WHERE cps.date_departure >= '" . $date_from->format('Y-m-d') . "'
                  AND cps.date_departure <= '" . $date_to->format('Y-m-d H:i:s') . "'
                ORDER BY cps.date_departure"
            );
        } catch (Exception $e) {
            return [
                'error' => true,
                'payload' => null,
                'msg' => $e->getMessage()
            ];
        }

        /**
         * Build categories entity
         * Label format: "Parent > Item" for nested items, just "Item" for root level
         * Only includes trees defined in controller config (if configured)
         */
        $categories = [];
        if (!empty($resultFilter['categories'])) {
            foreach ($resultFilter['categories'] as $var_name => $levels) {
                $group = $var_name;
                if (!empty($controller_config['categories'])) {
                    $found = false;
                    foreach ($controller_config['categories'] as $configured_category) {
                        if ($configured_category['var_name'] == $var_name) {
                            $group = $configured_category['title'];
                            $found = true;
                        }
                    }
                    if ($found === false) {
                        continue;
                    }
                }
                foreach ($levels as $level => $items) {
                    foreach ($items as $item) {
                        if (empty($item->count_in_system) || $item->count_in_system == 0) {
                            continue;
                        }
                        $label = $item->name;
                        if (!empty($item->path_str)) {
                            $path = (array)$item->path_str;
                            if (isset($path[1])) {
                                $label = $path[1] . ' » ' . $path[0];
                            }
                        }
                        $categories[] = [
                            'type' => 'category',
                            'id' => $item->id_item,
                            'label' => $label,
                            'group' => $group,
                            'search' => ['pm-c[' . $var_name . ']' => $item->id_item]
                        ];
                    }
                }
            }
        }

        /**
         * Build products entity
         * Compact: only id + headline
         */
        $products = [];
        if (!empty($resultItems['items'])) {
            foreach ($resultItems['items'] as $item) {
                $products[] = [
                    'type' => 'product',
                    'id' => $item['id_media_object'],
                    'label' => html_entity_decode(strip_tags($item['headline'])),
                    'search' => ['pm-id' => (string)$item['id_media_object']]
                ];
            }
        }

        /**
         * Build departures entity
         * Combined label: "dd.mm. Product Name"
         * Source: pmt2core_touristic_dates (SQL)
         */
        $departures = [];
        $seen_departures = [];
        foreach ($departures_raw as $row) {
            $key = $row->id_media_object . '_' . $row->dep_id;
            if (isset($seen_departures[$key])) {
                continue;
            }
            $seen_departures[$key] = true;
            $departures[] = [
                'type' => 'departure',
                'label' => $row->dep_label . ' ' . html_entity_decode(strip_tags($row->headline)),
                'search' => ['pm-id' => (string)$row->id_media_object, 'pm-dr' => $row->dep_id]
            ];
        }

        /**
         * Build months entity (aggregated from SQL departure data)
         */
        $months = [];
        $month_counts = [];
        foreach ($departures_raw as $row) {
            $month_key = substr($row->dep_id, 0, 6);
            if (!isset($month_counts[$month_key])) {
                $month_counts[$month_key] = 0;
            }
            $month_counts[$month_key]++;
        }
        ksort($month_counts);
        foreach ($month_counts as $month_key => $count) {
            $date = DateTime::createFromFormat('Ymd', $month_key . '01');
            if ($date === false) {
                continue;
            }
            $label = HelperFunctions::monthNumberToLocalMonthName($date->format('n'));
            if ($date->format('Y') != date('Y')) {
                $label .= ' ' . $date->format('Y');
            }
            $months[] = [
                'type' => 'month',
                'id' => $month_key,
                'label' => $label,
                'count' => $count,
                'search' => ['pm-dr' => $date->format('Ymd') . '-' . $date->format('Ymt')]
            ];
        }

        /**
         * Build payload and compute ETag hash
         */
        $payload = [
            'categories' => $categories,
            'products' => $products,
            'departures' => $departures,
            'months' => $months,
            'generated_at' => date('c')
        ];

        $etag = md5(json_encode([
            'categories' => $categories,
            'products' => $products,
            'departures' => $departures,
            'months' => $months
        ]));

        /**
         * Application-level ETag: if client sends matching etag, return minimal response
         */
        if (!empty($params['etag']) && $params['etag'] === $etag) {
            return [
                'error' => false,
                'payload' => ['changed' => false],
                'msg' => null
            ];
        }

        $payload['changed'] = true;
        $payload['etag'] = $etag;

        return [
            'error' => false,
            'payload' => $payload,
            'msg' => null
        ];
    }
}