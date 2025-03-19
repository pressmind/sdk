<?php

namespace Pressmind\Search;

use Pressmind\Search\Query\Filter;

class Query
{
    private static $_run_time_cache_full = [];
    private static $_run_time_cache_filter = [];
    private static $_run_time_cache_search = [];
    public static $group_keys = null;
    public static $language_code = null;
    public static $agency_id_price_index = null;
    public static $touristic_origin = 0;
    public static $calendar_show_departures = false;
    public static $atlas_active = false;
    public static $atlas_definition = false;
    private static $validated_search_parameters = [];
    private static $page = 1;
    private static $page_size = 10;

    /**
     * @param Filter $QueryFilter
     * <code>
     * return ['total_result' => 100,
     *            'current_page' => 1,
     *            'pages' => 10,
     *            'page_size' => 10,
     *            'cache' => [
     *              'is_cached' => false,
     *              'info' => []
     *            ],
     *            'items' => [],
     *            'mongodb' => [
     *              'aggregation_pipeline' => ''
     *            ]
     *           ];
     * </code>
     * @return array
     * @throws \Exception
     */
    public static function getResult(Filter $QueryFilter)
    {
        $cache_key = md5(serialize(func_get_args()));
        if(isset(self::$_run_time_cache_full[$cache_key])){
            return self::$_run_time_cache_full[$cache_key];
        }
        $duration_filter_ms = null;
        $duration_search_ms = null;
        if (empty($QueryFilter->request['pm-ho']) === true && !empty($QueryFilter->occupancy)) {
            $QueryFilter->request['pm-ho'] = is_array($QueryFilter->occupancy) ? implode(',', $QueryFilter->occupancy) : $QueryFilter->occupancy;
        }
        $id_object_type = empty($QueryFilter->request['pm-ot']) ? false : self::extractObjectType($QueryFilter->request['pm-ot']);
        $order = empty($QueryFilter->request['pm-o']) ? 'price-asc' : $QueryFilter->request['pm-o'];
        if($QueryFilter->getFilters){
            $FilterCondition = [];
            if(!empty($QueryFilter->request['pm-ot'])){
                if(!$id_object_type) {
                    $FilterCondition[] = new \Pressmind\Search\Condition\MongoDB\ObjectType($id_object_type);
                }
            }
            if(!empty(self::$group_keys)){
                $FilterCondition[] = new \Pressmind\Search\Condition\MongoDB\Group(self::$group_keys);
            }
            if(!empty($QueryFilter->occupancy)){
                $FilterCondition[] = new \Pressmind\Search\Condition\MongoDB\Occupancy($QueryFilter->occupancy);
            }
            $FilterCondition = array_merge($FilterCondition, $QueryFilter->custom_conditions);
            $filter = new \Pressmind\Search\MongoDB(
                $FilterCondition,
                ['price_total' => 'asc'],
                self::$language_code,
                self::$touristic_origin,
                self::$agency_id_price_index
            );
            $start_time = microtime(true);
            if(isset(self::$_run_time_cache_filter[$cache_key])){
                $result_filter = self::$_run_time_cache_filter[$cache_key];
            }else{
                $result_filter = $filter->getResult(true, true, $QueryFilter->ttl_filter, null, $QueryFilter->preview_date, $QueryFilter->allowed_visibilities);
                self::$_run_time_cache_filter[$cache_key] = $result_filter;
            }
            $end_time = microtime(true);
            $duration_filter_ms = ($end_time - $start_time)  * 1000;
        }
        $items = [];
        if(!$QueryFilter->returnFiltersOnly) {
            if(isset(self::$_run_time_cache_search[$cache_key])){
                $search = self::$_run_time_cache_search[$cache_key];
            }else {
                $search = self::fromRequest($QueryFilter->request, 'pm', true, $QueryFilter->page_size, $QueryFilter->custom_conditions);
                self::$_run_time_cache_search[$cache_key] = $search;
            }
            $start_time = microtime(true);
            $result = $search->getResult(true, false, $QueryFilter->ttl_search, $QueryFilter->output, $QueryFilter->preview_date, $QueryFilter->allowed_visibilities);
            $end_time = microtime(true);
            $duration_search_ms = ($end_time - $start_time) * 1000;
            foreach ($result->documents as $document) {
                $document = json_decode(json_encode($document), true);
                $item = (array)$document['description'];
                $item['id_media_object'] = $document['id_media_object'];
                $item['id_object_type'] = $document['id_object_type'];
                $item['url'] = $document['url'];
                $item['recommendation_rate'] = !empty($document['recommendation_rate']) ? $document['recommendation_rate'] : null;
                $item['is_running'] = empty($document['is_running'])  ? false : true;
                $item['sold_out'] = empty($document['sold_out'])  ? false : true;
                $item['dates_per_month'] = [];
                $item['fst_date_departure'] = !empty($document['fst_date_departure']) ? new \DateTime($document['fst_date_departure']) : null;
                $item['possible_durations'] = !empty($document['possible_durations']) ? $document['possible_durations'] : [];
                $item['last_modified_date'] = $document['last_modified_date'];
                $item['sales_priority'] = !empty($document['sales_priority']) ? $document['sales_priority'] : null;
                if (!empty($document['prices'])) {
                    if(!is_array($document['prices']['date_departures'])){
                        $document['prices']['date_departures'] = [$document['prices']['date_departures']];
                    }
                    $item['cheapest_price'] = new \stdClass();
                    $item['cheapest_price']->duration = $document['prices']['duration'];
                    $item['cheapest_price']->price_total = (float)$document['prices']['price_total'];
                    $item['cheapest_price']->price_regular_before_discount = $document['prices']['price_regular_before_discount'];
                    $item['cheapest_price']->earlybird_discount = $document['prices']['earlybird_discount'];
                    $item['cheapest_price']->earlybird_discount_f = $document['prices']['earlybird_discount_f'];
                    $item['cheapest_price']->earlybird_name = empty($document['prices']['earlybird_name']) ? null : $document['prices']['earlybird_name'];
                    $item['cheapest_price']->state = $document['prices']['state'];
                    $item['cheapest_price']->date_departures = [];
                    if (!empty($document['prices']['date_departures'])) {
                        foreach ($document['prices']['date_departures'] as $date_departure) {
                            $item['cheapest_price']->date_departures[] = new \DateTime($date_departure);
                        }
                    }
                    $item['cheapest_price']->guaranteed_departures = [];
                    if (!empty($document['prices']['guaranteed_departures'])) {
                        foreach ($document['prices']['date_departures'] as $guaranteed_departure) {
                            $item['cheapest_price']->guaranteed_departures[] = new \DateTime($guaranteed_departure);
                        }
                    }
                    $item['cheapest_price']->earlybird_discount_date_to = $document['prices']['earlybird_discount_date_to'] != null ? new \DateTime($document['prices']['earlybird_discount_date_to']) : null;
                    $item['cheapest_price']->option_name = $document['prices']['option_name'] ?? null;
                    $item['cheapest_price']->housing_package_name = $document['prices']['housing_package_name'] ?? null;
                    $item['cheapest_price']->option_board_type = $document['prices']['option_board_type'];
                    $item['cheapest_price']->transport_type = $document['prices']['transport_type'];
                    $item['cheapest_price']->occupancy = $document['prices']['occupancy'];
                    $item['cheapest_price']->occupancy_child = $document['prices']['occupancy_child'];
                    $item['cheapest_price']->startingpoint_id_city = !empty($document['prices']['startingpoint_option']['id']) ? $document['prices']['startingpoint_option']['id'] : null;
                    $item['cheapest_price']->startingpoint_city = !empty($document['prices']['startingpoint_option']['city']) ? $document['prices']['startingpoint_option']['city'] : null;
                    $item['cheapest_price']->startingpoint_zip = !empty($document['prices']['startingpoint_option']['zip']) ? $document['prices']['startingpoint_option']['zip'] : null;
                } else {
                    $item['cheapest_price'] = null;
                    $document['prices'] = null;
                }
                if (!empty($document['dates_per_month'])) {
                    $document['dates_per_month'] = array_filter($document['dates_per_month'], function($item) {
                        return !empty($item['five_dates_in_month']);
                    });
                    $document['dates_per_month'] = array_values($document['dates_per_month']);
                    $item['dates_per_month'] = $document['dates_per_month'];
                    foreach ($document['dates_per_month'] as $k => $month) {
                        foreach ($month['five_dates_in_month'] as $k1 => $date) {
                            $item['dates_per_month'][$k]['five_dates_in_month'][$k1]['date_departure'] = new \DateTime($date['date_departure']);
                            $item['dates_per_month'][$k]['five_dates_in_month'][$k1]['date_arrival'] = new \DateTime($date['date_arrival']);
                            $item['dates_per_month'][$k]['five_dates_in_month'][$k1]['duration'] = $date['duration'];
                            $item['dates_per_month'][$k]['five_dates_in_month'][$k1]['price_total'] = (float)$date['price_total'];
                            $item['dates_per_month'][$k]['five_dates_in_month'][$k1]['price_regular_before_discount'] = $date['price_regular_before_discount'];
                            $item['dates_per_month'][$k]['five_dates_in_month'][$k1]['durations_from_this_departure'] = $date['durations_from_this_departure'];
                            $item['dates_per_month'][$k]['five_dates_in_month'][$k1]['guaranteed'] = !empty($date['guaranteed']);
                            $item['dates_per_month'][$k]['five_dates_in_month'][$k1]['active'] = false;
                            if(!empty($document['fst_date_departure']) && $item['dates_per_month'][$k]['five_dates_in_month'][$k1]['date_departure']->format('Y-m-d') === $item['fst_date_departure']->format('Y-m-d')){
                                $item['dates_per_month'][$k]['five_dates_in_month'][$k1]['active'] = true;
                            }
                            if(!empty($item['cheapest_price']->price_total) && empty($document['fst_date_departure']) && $item['dates_per_month'][$k]['five_dates_in_month'][$k1]['price_total'] === $item['cheapest_price']->price_total){
                                $item['dates_per_month'][$k]['five_dates_in_month'][$k1]['active'] = true;
                            }
                        }
                    }
                }
                $item['departure_date_count'] = $document['departure_date_count'];
                $item['possible_durations'] = !empty($document['possible_durations']) ? $document['possible_durations'] : [];
                //$item['best_price_meta'] = $document['best_price_meta'];
                $item['meta']['findings'] = [];
                if(!empty($document['highlights']) && is_array($document['highlights'])){
                    foreach($document['highlights'] as $finding){
                        $finding_str = '';
                        foreach($finding['texts'] as $str){
                            if($str['type'] == 'hit'){
                                $finding_str .= '<b>'.$str['value'].'</b>';
                            }else{
                                $finding_str .= $str['value'];
                            }
                        }
                        $item['meta']['findings'][] = ['score' => $finding['score'], 'value' => $finding_str];
                    }
                }
                $item['meta']['score'] = !empty($document['score']) ? $document['score'] : null;
                $items[] = $item;
            }
        }
        $categories = [];
        if(!empty($result_filter->categoriesGrouped)){
            $matching_categories_map = [];
            if(!$QueryFilter->returnFiltersOnly){
                $matching_categories = json_decode(json_encode($result->categoriesGrouped));
                foreach($matching_categories as $item){
                    $matching_categories_map[$item->_id->field_name][$item->_id->id_item] = $item;
                }
            }
            foreach(json_decode(json_encode($result_filter->categoriesGrouped)) as $item){
                $item->_id->count_in_system = $item->count;
                $item->_id->count_in_search = 0;
                if(isset($matching_categories_map[$item->_id->field_name][$item->_id->id_item])){
                    $item->_id->count_in_search = $matching_categories_map[$item->_id->field_name][$item->_id->id_item]->count;
                }
                $categories[$item->_id->field_name][$item->_id->level][$item->_id->id_item] = $item->_id;
            }
        }
        $board_types = [];
        if(!empty($result_filter->boardTypesGrouped)){
            $matching_board_types_map = [];
            if(!$QueryFilter->returnFiltersOnly){
                $matching_board_types = json_decode(json_encode($result->boardTypesGrouped));
                foreach($matching_board_types as $item){
                    if(empty($item->_id)){
                        continue;
                    }
                    $matching_board_types_map[$item->_id] = $item;
                }
            }
            foreach(json_decode(json_encode($result_filter->boardTypesGrouped)) as $item){
                if(empty($item->_id)){
                    continue;
                }
                $item->count_in_system = $item->count;
                $item->count_in_search = 0;
                $item->name = $item->_id;
                if(isset($matching_board_types_map[$item->_id])){
                    $item->count_in_search = $matching_board_types_map[$item->_id]->count;
                }
                $board_types[$item->_id] = $item;
            }
        }
        $startingpoint_options = [];
        if(!empty($result_filter->startingPointsGrouped)){
           $matching_startingpoints_map = [];
           if(!$QueryFilter->returnFiltersOnly){
               $matching_startingpoints = json_decode(json_encode($result->startingPointsGrouped));
               foreach($matching_startingpoints as $item){
                   if(empty($item->_id->id_city)){
                       continue;
                   }
                   $matching_startingpoints_map[$item->_id->id_city] = $item;
               }
           }
           foreach(json_decode(json_encode($result_filter->startingPointsGrouped)) as $item){
               $newItem = new \stdClass();
               if(empty($item->_id->id_city)){
                   continue;
               }
               $newItem->count_in_system = $item->count;
               $newItem->count_in_search = 0;
               $newItem->id = $item->_id->id_city;
               $newItem->city = $item->_id->city;
               if(isset($matching_startingpoints_map[$item->_id->id_city])){
                   $newItem->count_in_search = $matching_startingpoints_map[$item->_id->id_city]->count;
               }
               $startingpoint_options[$item->_id->id_city] = $newItem;
           }
           usort($startingpoint_options, function($a, $b) {
               return $a->city <=> $b->city;
           });
        }
        $transport_types = [];
        if(!empty($result_filter->transportTypesGrouped)){
            $matching_transport_types_map = [];
            if(!$QueryFilter->returnFiltersOnly){
                $matching_transport_types = json_decode(json_encode($result->transportTypesGrouped));
                foreach($matching_transport_types as $item){
                    $matching_transport_types_map[$item->_id] = $item;
                }
            }
            foreach(json_decode(json_encode($result_filter->transportTypesGrouped)) as $item){
                if(empty($item->_id)){
                    continue;
                }
                $item->count_in_system = $item->count;
                $item->count_in_search = 0;
                $item->name = $item->_id;
                if(isset($matching_transport_types_map[$item->_id])){
                    $item->count_in_search = $matching_transport_types_map[$item->_id]->count;
                }
                $transport_types[$item->_id] = $item;
            }
        }
        $sold_out = [];
        if(!empty($result_filter->transportTypesGrouped)){
            $matching_sold_out_map = [];
            if(!$QueryFilter->returnFiltersOnly){
                $matching_sold_out = json_decode(json_encode($result->sold_out));
                foreach($matching_sold_out as $item){
                    $item->_id = $item->_id ? '1' : '0';
                    $matching_transport_types_map[$item->_id] = $item;
                }
            }
            foreach(json_decode(json_encode($result_filter->sold_out)) as $item){
                $item->_id = $item->_id ? '1' : '0';
                $item->count_in_system = $item->count;
                //$item->count_in_search = 0;
                $item->name = $item->_id;
                unset($item->count);
                //if(isset($matching_sold_out_map[$item->_id])){
                //    $item->count_in_search = $matching_sold_out_map[$item->_id]->count;
                //}
                $sold_out[$item->_id] = $item;
            }
        }
        $is_running = [];
        if(!empty($result_filter->transportTypesGrouped)){
            $matching_is_running_map = [];
            if(!$QueryFilter->returnFiltersOnly){
                $matching_is_running = json_decode(json_encode($result->is_running));
                foreach($matching_is_running as $item){
                    $item->_id = $item->_id ? '1' : '0';
                    $matching_transport_types_map[$item->_id] = $item;
                }
            }
            foreach(json_decode(json_encode($result_filter->is_running)) as $item){
                $item->_id = $item->_id ? '1' : '0';
                $item->count_in_system = $item->count;
                //$item->count_in_search = 0;
                $item->name = $item->_id;
                unset($item->count);
                //if(isset($matching_is_running_map[$item->_id])){
                //    $item->count_in_search = $matching_is_running_map[$item->_id]->count;
                //}
                $is_running[$item->_id] = $item;
            }
        }
        if(self::$calendar_show_departures === true){
            $start_time = microtime(true);
            $filter_departures = [];
            $filter_months = [];
            /**
             * be aware, the count_in_search and ids_in_search properties contains only the count of the current (limited) search result
             */
            if(!empty($result)){
                foreach ($result->documents as $document) {
                    $document = json_decode(json_encode($document), true);
                    if (!empty($document['prices']['date_departures'])) {
                        if(!is_array($document['prices']['date_departures'])){
                            $document['prices']['date_departures'] = [$document['prices']['date_departures']];
                        }
                        foreach ($document['prices']['date_departures'] as $date_departure) {
                            $date_departure = new \DateTime($date_departure);
                            if(isset($filter_departures[$date_departure->format('Y-m-d')])){
                                $filter_departures[$date_departure->format('Y-m-d')]->count_in_search++;
                                $filter_departures[$date_departure->format('Y-m-d')]->date = $date_departure->format('Y-m-d');
                            }else{
                                $filter_departures[$date_departure->format('Y-m-d')] = new \stdClass();
                                $filter_departures[$date_departure->format('Y-m-d')]->count_in_system = 0;
                                $filter_departures[$date_departure->format('Y-m-d')]->count_in_search = 1;
                                $filter_departures[$date_departure->format('Y-m-d')]->date = $date_departure->format('Y-m-d');
                            }
                            $fst_day_in_month = clone $date_departure;
                            $fst_day_in_month->modify('first day of this month');
                            if(isset($filter_months[$fst_day_in_month->format('Y-m-d')])){
                                $filter_months[$fst_day_in_month->format('Y-m-d')]->count_in_search++;
                                $filter_months[$fst_day_in_month->format('Y-m-d')]->date = $fst_day_in_month;
                                $filter_months[$fst_day_in_month->format('Y-m-d')]->ids_in_search[] = $document['id_media_object'];
                                $filter_months[$fst_day_in_month->format('Y-m-d')]->ids_in_search = array_unique($filter_months[$fst_day_in_month->format('Y-m-d')]->ids_in_search);
                            }else{
                                $filter_months[$fst_day_in_month->format('Y-m-d')] = new \stdClass();
                                $filter_months[$fst_day_in_month->format('Y-m-d')]->date = $fst_day_in_month;
                                $filter_months[$fst_day_in_month->format('Y-m-d')]->count_in_system = 0;
                                $filter_months[$fst_day_in_month->format('Y-m-d')]->count_in_search = 1;
                                $filter_months[$fst_day_in_month->format('Y-m-d')]->ids_in_search = [$document['id_media_object']];
                            }
                        }
                    }
                }
            }
            if(!empty($result_filter)) {
                foreach ($result_filter->documents as $document) {
                    $document = json_decode(json_encode($document), true);
                    if (!empty($document['prices']['date_departures'])) {
                        if (!is_array($document['prices']['date_departures'])) {
                            $document['prices']['date_departures'] = [$document['prices']['date_departures']];
                        }
                        foreach ($document['prices']['date_departures'] as $date_departure) {
                            $date_departure = new \DateTime($date_departure);
                            if (isset($filter_departures[$date_departure->format('Y-m-d')])) {
                                $filter_departures[$date_departure->format('Y-m-d')]->count_in_system++;
                                $filter_departures[$date_departure->format('Y-m-d')]->date = $date_departure->format('Y-m-d');
                            } else {
                                $filter_departures[$date_departure->format('Y-m-d')] = new \stdClass();
                                $filter_departures[$date_departure->format('Y-m-d')]->count_in_system = 1;
                                $filter_departures[$date_departure->format('Y-m-d')]->count_in_search = 0;
                                $filter_departures[$date_departure->format('Y-m-d')]->date = $date_departure->format('Y-m-d');
                            }
                            $fst_day_in_month = clone $date_departure;
                            $fst_day_in_month->modify('first day of this month');
                            if (isset($filter_months[$fst_day_in_month->format('Y-m-d')])) {
                                $filter_months[$fst_day_in_month->format('Y-m-d')]->count_in_system++;
                                $filter_months[$fst_day_in_month->format('Y-m-d')]->date = $fst_day_in_month;
                                $filter_months[$fst_day_in_month->format('Y-m-d')]->ids_in_system[] = $document['id_media_object'];
                                $filter_months[$fst_day_in_month->format('Y-m-d')]->ids_in_system = array_unique($filter_months[$fst_day_in_month->format('Y-m-d')]->ids_in_system);
                            } else {
                                $filter_months[$fst_day_in_month->format('Y-m-d')] = new \stdClass();
                                $filter_months[$fst_day_in_month->format('Y-m-d')]->date = $fst_day_in_month;
                                $filter_months[$fst_day_in_month->format('Y-m-d')]->count_in_system = 1;
                                $filter_months[$fst_day_in_month->format('Y-m-d')]->count_in_search = 0;
                                $filter_months[$fst_day_in_month->format('Y-m-d')]->ids_in_system = [$document['id_media_object']];
                            }
                        }
                    }
                }
            }
            $filter_months = array_values($filter_months);
            usort($filter_months, function($a, $b) {
                return $a->date <=> $b->date;
            });
            if(!$QueryFilter->returnFiltersOnly) {
                foreach ($result->documents as $document) {
                    $document = json_decode(json_encode($document), true);
                    if (!empty($document['prices']['date_departures'])) {
                        if (!is_array($document['prices']['date_departures'])) {
                            $document['prices']['date_departures'] = [$document['prices']['date_departures']];
                        }
                        foreach ($document['prices']['date_departures'] as $date_departure) {
                            $date_departure = new \DateTime($date_departure);
                            if (isset($filter_departures[$date_departure->format('Y-m-d')])) {
                                $filter_departures[$date_departure->format('Y-m-d')]->count_in_search++;
                            }
                            if (isset($filter_months[$date_departure->format('Y-m-01')])) {
                                $filter_months[$date_departure->format('Y-m-01')]->count_in_search++;
                            }
                        }
                    }
                }
            }
            usort($filter_departures, function($a, $b) {
                return $a->date <=> $b->date;
            });
            $end_time = microtime(true);
            $departure_filter_ms = ($end_time - $start_time)  * 1000;
        }
        $result = [
            'total_result' => !empty($result->total) ? $result->total : null,
            'current_page' => !empty($result->currentPage) ? $result->currentPage : null,
            'pages' => !empty($result->pages) ? $result->pages : null,
            'page_size' => $QueryFilter->page_size,
            'query_string' => self::getCurrentQueryString(),
            'cache' => [
                'is_cached' => false,
                'info' => []
            ],
            'id_object_type' => !$id_object_type ? [] : $id_object_type,
            'order' => $order,
            'categories' => $categories,
            'board_types' => $board_types,
            'transport_types' => $transport_types,
            'sold_out' => $sold_out,
            'is_running' => $is_running,
            'startingpoint_options' => $startingpoint_options,
            'duration_min' => !empty($result_filter->minDuration) ? $result_filter->minDuration : null,
            'duration_max' => !empty($result_filter->maxDuration) ? $result_filter->maxDuration : null,
            'departure_min' => !empty($result_filter->minDeparture) ? new \DateTime($result_filter->minDeparture) : null,
            'departure_max' => !empty($result_filter->maxDeparture) ? new \DateTime($result_filter->maxDeparture) : null,
            'price_min' => !empty($result_filter->minPrice) ? $result_filter->minPrice  : null,
            'price_max' => !empty($result_filter->maxPrice) ? $result_filter->maxPrice : null,
            'departure_dates' => !empty($filter_departures) ? $filter_departures : null,
            'months' => !empty($filter_months) ? $filter_months : null,
            'items' => $items,
            'mongodb' => [
                'duration_filter_ms' => $duration_filter_ms,
                'duration_search_ms' => $duration_search_ms,
                'aggregation_pipeline_filter' => !empty($filter) ? $filter->buildQueryAsJson($QueryFilter->getFilters, $QueryFilter->output, $QueryFilter->preview_date, $QueryFilter->allowed_visibilities) : null,
                'aggregation_pipeline_search' => !empty($search) ? $search->buildQueryAsJson($QueryFilter->getFilters, $QueryFilter->output, $QueryFilter->preview_date, $QueryFilter->allowed_visibilities) : null,
                'collection_name_filter' => !empty($filter) ? $filter->getCurrentCollectionName() : null,
                'collection_name_search' => !empty($search) ? $search->getCurrentCollectionName() : null
            ]
        ];
        self::$_run_time_cache_full[$cache_key] = $result;
        return $result;
    }

    /**
     * Build search query based on specific on GET or POST request
     * Possible Parameters
     * $request['pm-id'] media object id/s  separated by comma
     * $request['pm-ot'] object Type ID
     * $request['pm-t'] term
     * $request['pm-co'] code/s separated by comma
     * $request['pm-c'] category id's separated by comma (search with "or") or plus (search with "and")   e.g. pm-c[land_default]=xxx,yyyy = or Suche, + and Suche
     * $request['pm-pr'] price range 123-1234
     * $request['pm-dr'] date range 20200101-20200202
     * $request['pm-url'] url like /travel/5-italia/
     * $request['pm-loc'] location like 50.123,12.123,10.5 (lng,lat,radius in km)
     * $request['pm-gr'] groups
     * $request['pm-du'] duration range 3-14
     * $request['pm-tr'] transport type
     * $request['pm-bt'] board type
     * $request['pm-ho'] occupancy
     * $request['pm-hoc'] occupancy child
     * $request['pm-loc'] location
     * $request['pm-sc'] startingpoint option city ids separated by comma
     * $request['pm-so'] sold out (0 or 1)
     * $request['pm-ir'] is running (0 or 1)
     * $request['pm-sp'] sales priority (A000001)
     * $request['pm-l'] limit 0,10
     * $request['pm-o'] order
     * @param $request
     * @param string $prefix
     * @param bool $paginator
     * @param int $page_size
     * @param array $custom_conditions
     * @return \Pressmind\Search\MongoDB
     */
    public static function fromRequest($request, $prefix = 'pm', $paginator = true, $page_size = 10, $custom_conditions = [])
    {
        array_walk_recursive($request, function(&$item){
            $item = rawurldecode($item);
        });
        $validated_search_parameters = [];
        $conditions = array();
        $order = array('price_total' => 'asc');
        if (isset($request[$prefix.'-ot'])) {
            $id_object_type = self::extractObjectType($request[$prefix.'-ot']);
            if($id_object_type !== false){
                $conditions[] = new \Pressmind\Search\Condition\MongoDB\ObjectType($id_object_type);
                $validated_search_parameters[$prefix.'-ot'] = is_array($id_object_type) ? implode(',', $id_object_type) : $id_object_type;
            }
        }
        if (empty($request[$prefix.'-t']) === false || empty($request[$prefix.'-loc']) === false){
            $term = !empty($request[$prefix.'-t']) ? self::sanitizeStr($request[$prefix.'-t']) : null;
            $order = array('score' => 'desc');
            if(self::$atlas_active === true){
                $gson_property = null;
                $lat = null;
                $lon = null;
                $radius = null;
                if (isset($request[$prefix.'-loc']) === true && is_array($request[$prefix.'-loc']) === true) {
                    $search_items = $request[$prefix.'-loc'];
                    foreach($search_items as $gson_property => $location){
                        if(preg_match('/^[0-9a-zA-Z\-\_]+$/', $gson_property) > 0){
                            if(preg_match('/^([0-9\.0-9]+),([0-9\.0-9]+)(,([0-9\.0-9]+))?$/', $location, $matches) > 0){
                                $lat = (float)$matches[1];
                                if($lat >= 90 || $lat <= -90){
                                    continue;
                                }
                                $lon = (float)$matches[2];
                                if($lon >= 180 || $lon <= -180){
                                    continue;
                                }
                                $radius = isset($matches[4]) ? (float)$matches[4] : 100;
                                $validated_search_parameters[$prefix.'-loc'][$gson_property] = $location;
                            }
                        }
                        break;
                    }
                }
                $conditions[] = new \Pressmind\Search\Condition\MongoDB\AtlasLuceneFulltext($term, !empty(self::$atlas_definition) ? self::$atlas_definition : [] ,$gson_property, $lat, $lon, $radius);
            }else{
                if(empty($term) === false){
                    $conditions[] = new \Pressmind\Search\Condition\MongoDB\Fulltext($term);
                }
            }
            if(empty($term) === false){
                $validated_search_parameters[$prefix.'-t'] = $request[$prefix.'-t'];
            }
        }
        if (empty($request[$prefix.'-co']) === false && preg_match('/^([0-9\-_A-Za-z\,]+)$/', $request[$prefix.'-co']) > 0){
            $codes = explode(',', $request[$prefix.'-co']);
            $conditions[] = new \Pressmind\Search\Condition\MongoDB\Code($codes);
            $validated_search_parameters[$prefix.'-co'] = $request[$prefix.'-co'];
        }
        if (isset($request[$prefix.'-pr']) === true && preg_match('/^([0-9]+)\-([0-9]+)$/', $request[$prefix.'-pr']) > 0) {
            list($price_range_from, $price_range_to) = explode('-', $request[$prefix.'-pr']);
            $price_range_from = empty(intval($price_range_from)) ? 1 : intval($price_range_from);
            $price_range_to = empty(intval($price_range_to)) ? 99999 : intval($price_range_to);
            $conditions[] = new \Pressmind\Search\Condition\MongoDB\PriceRange($price_range_from, $price_range_to);
            $validated_search_parameters[$prefix.'-pr'] = $price_range_from.'-'.$price_range_to;
        }
        if (isset($request[$prefix.'-du']) === true && preg_match('/^([0-9]+)\-([0-9]+)$/', $request[$prefix.'-du']) > 0) {
            list($duration_range_from, $duration_range_to) = explode('-', $request[$prefix.'-du']);
            $duration_range_from = empty(intval($duration_range_from)) ? 1 : intval($duration_range_from);
            $duration_range_to = empty(intval($duration_range_to)) ? 99999 : intval($duration_range_to);
            $conditions[] = new \Pressmind\Search\Condition\MongoDB\DurationRange($duration_range_from,$duration_range_to);
            $validated_search_parameters[$prefix.'-du'] = $duration_range_from.'-'.$duration_range_to;
        }
        if (isset($request[$prefix.'-dr']) === true) {
            $dateRange = self::extractDaterange($request[$prefix.'-dr']);
            if($dateRange !== false){
                list($from, $to) = $dateRange;
                $conditions[] = new \Pressmind\Search\Condition\MongoDB\DateRange($from, $to);
                $validated_search_parameters[$prefix.'-dr'] = $from->format('Ymd').'-'.$to->format('Ymd');
            }
        }
        if (empty($request[$prefix.'-bt']) === false){
            $board_types = self::extractBoardTypes($request[$prefix.'-bt']);
            $conditions[] = new \Pressmind\Search\Condition\MongoDB\BoardType($board_types);
            $validated_search_parameters[$prefix.'-bt'] = implode(',', $board_types);
        }
        if (empty($request[$prefix.'-tr']) === false){
            $transport_types = self::extractTransportTypes($request[$prefix.'-tr']);
            $conditions[] = new \Pressmind\Search\Condition\MongoDB\TransportType($transport_types);
            $validated_search_parameters[$prefix.'-tr'] = implode(',', $transport_types);
        }
        if (empty($request[$prefix.'-sc']) === false){
            $id_cities = self::extractIdStartingPointOptionCity($request[$prefix.'-sc']);
            $conditions[] = new \Pressmind\Search\Condition\MongoDB\StartingPointOptionCity($id_cities);
            $validated_search_parameters[$prefix.'-sc'] = implode(',', $id_cities);
        }
        if (isset($request[$prefix.'-so']) === true){
            $sold_out = self::extractBoolean($request[$prefix.'-so']);
            $conditions[] = new \Pressmind\Search\Condition\MongoDB\SoldOut($sold_out);
            $validated_search_parameters[$prefix.'-so'] = $sold_out ? '1' : '0';
        }
        if (isset($request[$prefix.'-sp']) === true){
            $sales_priority = self::extractSalesPriority($request[$prefix.'-sp']);
            if(!empty($sales_priority)) {
                $conditions[] = new \Pressmind\Search\Condition\MongoDB\SalesPriority($sales_priority);
                $validated_search_parameters[$prefix . '-sp'] = $sales_priority;
            }
        }
        if (isset($request[$prefix.'-ir']) === true){
            $is_running = self::extractBoolean($request[$prefix.'-ir']);
            $conditions[] = new \Pressmind\Search\Condition\MongoDB\Running($is_running);
            $validated_search_parameters[$prefix.'-ir'] = $is_running ? '1' : '0';
        }
        if (isset($request[$prefix.'-c']) === true && is_array($request[$prefix.'-c']) === true) {
            $search_item = $request[$prefix.'-c'];
            foreach($search_item as $property_name => $item_ids){
                if(preg_match('/^[0-9a-zA-Z\-\_]+$/', $property_name) > 0){ // valid property name
                    if(preg_match('/^[0-9a-zA-Z\-\_,]+$/', $item_ids) > 0){ // search by OR, marked by ","
                        $delimiter = ',';
                        $operator = 'OR';
                    }elseif(preg_match('/^[0-9a-zA-Z\-\_+]+$/', $item_ids) > 0){ // search by AND, marked by "+"
                        $delimiter = '+'; // be aware, this sign is reserverd by php. urls must use the escaped sign %2B
                        $operator = 'AND';
                    }else{ // not valid
                        echo 'operator not valid';
                        continue;
                    }
                    $item_ids = explode($delimiter,$item_ids);
                    $conditions[] = new \Pressmind\Search\Condition\MongoDB\Category($property_name, $item_ids, $operator);
                    $validated_search_parameters[$prefix.'-c'][$property_name] = implode($delimiter, $item_ids);
                }
            }
        }
        if (empty($request[$prefix.'-ho']) === false){
            if(preg_match('/^[0-9\,]+$/', $request[$prefix.'-ho']) > 0){
                $occupancies = array_map('intval', explode(',', $request[$prefix.'-ho']));
                $validated_search_parameters[$prefix.'-ho'] = implode(',', $occupancies);
                $child_occupancies = [];
                if(empty($request[$prefix.'-hoc']) === false && preg_match('/^[0-9\,]+$/', $request[$prefix.'-hoc']) > 0){
                    $child_occupancies = array_map('intval', explode(',', $request[$prefix.'-hoc']));
                    $validated_search_parameters[$prefix.'-hoc'] = implode(',', $child_occupancies);
                }
                $conditions[] = new \Pressmind\Search\Condition\MongoDB\Occupancy($occupancies, $child_occupancies);
            }
        }
        if (empty($request[$prefix.'-id']) === false){
            if(preg_match('/^[\-0-9\,]+$/', $request[$prefix.'-id']) > 0){
                $ids = array_map('intval', explode(',', $request[$prefix.'-id']));
                $conditions[] = new \Pressmind\Search\Condition\MongoDB\MediaObject($ids);
                $validated_search_parameters[$prefix.'-id'] = implode(',', $ids);
            }
        }
        if (empty($request[$prefix.'-url']) === false){
            $url = $request[$prefix.'-url'];
            $conditions[] = new \Pressmind\Search\Condition\MongoDB\Url($url);
            $validated_search_parameters[$prefix.'-url'] = $url;
        }
        if (empty($request[$prefix.'-gr']) === false){
            $groups = self::extractGroups($request[$prefix.'-gr']);
            $conditions[] = new \Pressmind\Search\Condition\MongoDB\Group($groups);
            $validated_search_parameters[$prefix.'-gr'] = implode(',', $groups);
        }
        if(!empty(self::$group_keys)){
            $conditions[] = new \Pressmind\Search\Condition\MongoDB\Group(self::$group_keys);
        }
        $allowed_orders = array(
            'rand', 'price-desc', 'price-asc', 'date_departure-asc', 'date_departure-desc',
            'score-asc', 'score-desc', 'recommendation_rate-asc', 'recommendation_rate-desc', 'priority', 'list', 'valid_from-asc', 'valid_from-desc'
        );
        if (empty($request[$prefix.'-o']) === false && in_array($request[$prefix.'-o'], $allowed_orders) === true) {
            if($request[$prefix.'-o'] == 'rand'){
                $order = array('rand' => '');
            }elseif($request[$prefix.'-o'] == 'priority'){
                $order = array('priority' => '');
            }elseif($request[$prefix.'-o'] == 'list'){
                $order = array('list' => '');
            }else{
                list($property, $direction) =  explode('-', $request[$prefix.'-o']);
                $property = $property == 'price' ? 'price_total' : $property;
                $order = array($property => $direction);
            }
            $validated_search_parameters[$prefix.'-o'] = $request[$prefix.'-o'];
        }
        $conditions = array_merge($conditions, $custom_conditions);
        self::$validated_search_parameters = $validated_search_parameters;
        $Search = new MongoDB(
            $conditions,
            $order,
            self::$language_code,
            self::$touristic_origin,
            self::$agency_id_price_index
        );
        if($paginator){ // @TODO this needs a refactoring
            $page = 0;
            //$page_size = 10;
            if (isset($request[$prefix.'-l']) === true && preg_match('/^([0-9]+)\,([0-9]+)$/', $request[$prefix.'-l'], $m) > 0) {
                $page = intval($m[1]);
                $page_size = intval($m[2]);
            }
            $Search->setPaginator(Paginator::create($page_size, $page));
        }
        return $Search;
    }

    /**
     * rebuild the SearchConditions without a defined list of attributes
     * @param array $removeItems
     * @param string $prefix
     * @param bool $paginator
     * @param int $page_size
     * @return \Pressmind\Search
     */
    public static function rebuild($removeItems = [], $prefix = 'pm', $paginator = true, $page_size = 10){

        $p = self::$validated_search_parameters;
        foreach($removeItems as $k){
            if(preg_match('/^([a-z\-]+)\[([a-zA-Z0-9\_]+)\]$/', $k, $matches) > 0){
                unset($p[$matches[1]][$matches[2]]);
            }else{
                unset($p[$k]);
            }
        }
        return self::fromRequest(array_filter($p), $prefix, $paginator, $page_size);

    }

    public static function getCurrentQueryString($page = null, $page_size = null, $custom_params = [], $prefix = 'pm'){
        $page = empty($page) ? self::$page : $page;
        $page_size = empty($page_size) ? self::$page_size : $page_size;
        $url = array_merge(self::$validated_search_parameters, [$prefix.'-l' => $page.','.$page_size], $custom_params);
        return urldecode(http_build_query($url));
    }

    /**
     * Accepts the following formats
     * YYYYMMDD-YYYYMMDD (departure range from/to)
     * or
     * YYYYMMDD (exact departure match)
     * or
     * {relative offset from today}-{relative offset from today} eg. "+90-+120" or "90-120"
     * or
     * {relative offset from today} e.g. "+90" means today-{today+offset}
     * @param $str
     * @return \DateTime[]|bool
     * @throws Exception
     */
    public static function extractDaterange($str){
        if(preg_match('/^([0-9]{4}[0-9]{2}[0-9]{2})\-([0-9]{4}[0-9]{2}[0-9 ]{2})$/', $str, $m) > 0){
            $from = new \DateTime($m[1]);
            $from->setTime(0,0);
            $to = new \DateTime($m[2]);
            $to->setTime(0,0);
            return array($from, $to);
        }elseif(preg_match('/^([0-9]{4}[0-9]{2}[0-9]{2})$/', $str, $m) > 0){
            $from = new \DateTime($m[1]);
            $from->setTime(0,0);
            return array($from, null);
        }elseif(preg_match('/^([\+\-]?[0-9]+)$/', $str, $m) > 0) {
            $to = new \DateTime('now');
            $to->setTime(0,0);
            $to->modify($m[1].' day');
            return array(new \DateTime('now'), $to);
        }elseif(preg_match('/^([\+\-]?[0-9]+)\-([\+\-]?[0-9]+)$/', $str, $m) > 0) {
            $from = new \DateTime('now');
            $from->setTime(0,0);
            $from->modify($m[1].' day');
            $to = new \DateTime('now');
            $to->setTime(0,0);
            $to->modify($m[2].' day');
            return array($from, $to);
        }
        return false;
    }

    /**
     * @param $str
     * @param $default
     * @return int[]|bool
     */
    public static function extractDurationRange($str, $default = false, $first_key_as_int = false){
        if(preg_match('/^([0-9]+)\-([0-9]+)$/', $str, $m) > 0){
            $v = array($m[1], $m[2]);
            return $first_key_as_int ? (int)$v[0] : $v;
        }else if(preg_match('/^([0-9]+)$/', $str, $m) > 0){
            $v = array($m[1], $m[1]);
            return $first_key_as_int ? (int)$v[0] : $v;
        }
        return $default;
    }

    /**
     * @param $str
     * @param $default
     * @return int[]|bool
     */
    public static function extractPriceRange($str, $default = false){
        if(preg_match('/^([0-9]+)\-([0-9]+)$/', $str, $m) > 0){
            return array($m[1], $m[2]);
        }
        return $default;
    }

    /**
     * @param $str
     * @return int[]|bool
     */
    public static function extractObjectType($str){
        if(preg_match('/^([0-9\,]+)$/', $str, $m) > 0){
            return array_map('intval', explode(',',$str));
        }
        return false;
    }

    /**
     * @param $str
     * @param $default
     * @return []
     */
    public static function extractTransportTypes($str, $default = [], $first_key_as_string = false){
        if(preg_match('/^[a-z,A-Z\,]+$/', $str) > 0){
            $v = explode(',', $str);
            return $first_key_as_string ? $v[0] : $v;
        }
        return $default;
    }

    /**
     * @param $str
     * @param $default
     * @return mixed|void
     */
    public static function extractAirport3L($str, $default = null){
        if(preg_match('/^[a-z,A-Z]{3}$/', $str) > 0){
            return strtoupper($str);
        }
        return $default;
    }

    /**
     * @param $str
     * @param $default
     * @return mixed|null
     */
    public static function extractHousingPackageId($str, $default = null){
        if(preg_match('/^[0-9a-zA-Z\-\+]+$/', $str) > 0){
            return $str;
        }
        return null;
    }

    /**
     * @param $str
     * @return string[]
     */
    public static function extractBoardTypes($str){
        $board_types = explode(',', $str);
        foreach($board_types as $k => $board_type){
            $board_types[$k] = self::sanitizeStr($board_type);
        }
        return $board_types;
    }

    /**
     * @param $str
     * @return string[]
     */
    public static function extractGroups($groups){
        if(!is_array($groups)){
            $groups = explode(',', $groups);
        }
        foreach($groups as $k => $group){
            $groups[$k] = self::sanitizeStr($group);
        }
        return $groups;
    }

    /**
     * @param $str
     * @return string
     */
    public static function sanitizeStr($str){
        return trim(preg_replace( '/[^a-zA-Z0-9_\-\.ÁÀȦÂÄǞǍĂĀÃÅǺǼǢĆĊĈČĎḌḐḒÉÈĖÊËĚĔĒẼE̊ẸǴĠĜǦĞG̃ĢĤḤáàȧâäǟǎăāãåǻǽǣćċĉčďḍḑḓéèėêëěĕēẽe̊ẹǵġĝǧğg̃ģĥḥÍÌİÎÏǏĬĪĨỊĴĶǨĹĻĽĿḼM̂M̄ʼNŃN̂ṄN̈ŇN̄ÑŅṊÓÒȮȰÔÖȪǑŎŌÕȬŐỌǾƠíìiîïǐĭīĩịĵķǩĺļľŀḽm̂m̄ŉńn̂ṅn̈ňn̄ñņṋóòôȯȱöȫǒŏōõȭőọǿơP̄ŔŘŖŚŜṠŠȘṢŤȚṬṰÚÙÛÜǓŬŪŨŰŮỤẂẀŴẄÝỲŶŸȲỸŹŻŽẒǮp̄ŕřŗśŝṡšşṣťțṭṱúùûüǔŭūũűůụẃẁŵẅýỳŷÿȳỹźżžẓǯßœŒçÇ\s]/', '', $str));
    }

    /**
     * @param $str
     * @param $default
     * @param $first_key_as_string
     * @return array|mixed|string|string[]
     */
    public static function extractIdStartingPointOptionCity($str, $default = [], $first_key_as_string = false){
        if(preg_match('/^([a-z0-9\,]+)$/', $str) > 0){
            $v = array_filter(explode(',', $str));
            return $first_key_as_string ? $v[0] : $v;
        }
        return $default;
    }

    /**
     * @param $str
     * @return bool
     */
    public static function extractBoolean($str){
        if(preg_match('/^(0|1)$/', $str) > 0){
            return (bool)$str;
        }
        return false;
    }

    /**
     * Matches A000001 (A,B or C and 6 digits, with trailing zeros)
     * @param $str
     * @return string|null
     */
    public static function extractSalesPriority($str){
        if(preg_match('/^[A-C]{1}[0-9]{6}$/', $str) > 0){
            return $str;
        }
        return null;
    }

}