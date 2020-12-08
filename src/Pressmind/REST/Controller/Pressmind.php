<?php


namespace Pressmind\REST\Controller;


use Exception;
use Pressmind\AbstractController;
use Pressmind\Custom\MediaType\Reise;
use Pressmind\HelperFunctions;
use Pressmind\ORM\Object\AbstractObject;
use Pressmind\ORM\Object\CategoryTree;
use Pressmind\ORM\Object\MediaObject;
use Pressmind\Search;
use Pressmind\Search\Condition\Fulltext;
use stdClass;

class Pressmind extends AbstractController
{
    /**
     * @return AbstractObject[]|stdClass
     * @throws Exception
     */
    public function mediaObject() {
        if(isset($this->parameters['id'])) {
            $mediaObject = new MediaObject($this->parameters['id'], true);
            return $mediaObject->toStdClass();
        } else {
            return MediaObject::listAll();
        }
    }

    /**
     * @return AbstractObject[]|stdClass
     * @throws Exception
     */
    public function categoryTree() {
        if(isset($this->parameters['id'])) {
            $categoryTree = new CategoryTree($this->parameters['id'], true);
            return $categoryTree->toStdClass();
        } else {
            return CategoryTree::listAll();
        }
    }

    /**
     * @return array
     * @throws Exception
     */
    public function search() {
        $searchparameters = $this->parameters['postdata'];
        //return ($this->parameters);
        $search_conditions = $searchparameters->conditions;
        $search_sort = isset($searchparameters->sort) ? $searchparameters->sort : null;
        $search_limit = isset($searchparameters->limit) ? $searchparameters->limit : null;
        $conditions = [];
        $cheapest_price_filter = new Search\CheapestPrice();
        foreach($search_conditions as $search_condition) {
            $condition_name = 'Pressmind\\Search\\Condition\\' . ucfirst($search_condition->type);
            $condition = new $condition_name();
            $condition->setConfig($search_condition->config);
            $conditions[] = $condition;
            if(strtolower($search_condition->type) == 'durationrange') {
                $cheapest_price_filter->duration_from = $condition->durationFrom;
                $cheapest_price_filter->duration_to = $condition->durationTo;
            }
            if(strtolower($search_condition->type) == 'daterange') {
                $cheapest_price_filter->date_from = $condition->dateFrom;
                $cheapest_price_filter->date_to = $condition->dateTo;
            }
        }
        $sort = !is_null($search_sort) ? [$search_sort->property => $search_sort->direction] : null;
        $limit = !is_null($search_limit) ? array('start' => $search_limit->start, 'length' => $search_limit->length) : null;
        $search = new Search($conditions, $limit, $sort);
        $trips = $search->getResults();
        /*print_r($searchparameters);
        echo $search->getQuery() . "\n";
        print_r($search->getValues());
        echo "\n";*/
        $result = [
            'trips' => []
        ];
        $prices = [];
        $departure_dates = [];
        $available_touristic_transports = [];
        $available_transports = [];
        $available_destinations = [];
        foreach ($trips as $trip) {
            $dates = $trip->getAllAvailableDates();
            $transports = $trip->getAllAvailableTransports();
            $trip_dates = [];
            foreach ($transports as $transport) {
                if(!isset($available_touristic_transports[$transport->type])) {
                    $available_touristic_transports[$transport->type] = $transport->mapTypeToString();
                }
            }
            foreach ($dates as $date) {
                $departure_dates[] = $date->departure->format('Y-m-d');
            }

            $cheapest_price = $trip->getCheapestPrice($cheapest_price_filter);
            $all_cheapest_prices = $trip->getCheapestPrices($cheapest_price_filter);
            foreach ($all_cheapest_prices as $single_cheapest_price) {
                if(!in_array($single_cheapest_price->date_departure->format('Y-m-d'), $trip_dates)) {
                    $trip_dates[] = $single_cheapest_price->date_departure->format('Y-m-d');
                }
            }
            $prices[] = $cheapest_price->price_total;
            $data = HelperFunctions::findObjectInArray($trip->data, 'language', 'de');
            foreach ($data->zielgebiet_default as $zielgebiet) {
                if(!isset($available_destinations[$zielgebiet->item->id]) && is_null($zielgebiet->item->id_parent)) {
                    $available_destinations[$zielgebiet->item->id] = $zielgebiet->item->name;
                }
            }
            foreach ($data->befoerderung_default as $befoerderung) {
                if(!isset($available_transports[$befoerderung->item->id])) {
                    $available_transports[$befoerderung->item->id] = $befoerderung->item->name;
                }
            }
            $image = $data->bilder_default[0];
            $item = new stdClass();
            $item->teaser_image = $image->getUri('teaser');
            $item->teaser_image_alt = $image->alt;
            $item->teaser_image_copyright = $image->copyright;
            $item->headline = $data->headline_default;
            $item->subline = $data->subline_default;
            $item->detail_link = '/reise/detail/' . $trip->getPrettyUrl();
            $item->zielgebiet = $data->zielgebiet_default[0]->item->name;
            $item->price = HelperFunctions::number_format($cheapest_price->price_total);
            $item->occupancy = $cheapest_price->option_occupancy;
            $item->duration = $cheapest_price->duration;
            $item->date_departure = $cheapest_price->date_departure->format('Y-m-d');
            $unset_date = array_search($item->date_departure, $trip_dates);
            unset($trip_dates[$unset_date]);
            sort($trip_dates);
            $item->additional_dates = $trip_dates;
            $result['trips'][] = $item;
        }
        sort($prices);
        sort($departure_dates);
        //$result['filters']['price_range']['from'] = $prices[0];
        //$result['filters']['price_range']['to'] = $prices[count($prices) - 1];
        //$result['filters']['date_range']['from'] = current($departure_dates);
        //$result['filters']['date_range']['to'] = end($departure_dates);
        $result['filters']['touristic_transports'] = $available_touristic_transports;
        $result['filters']['transports'] = $available_transports;
        $result['filters']['destinations'] = $available_destinations;
        $result['debug'] = $searchparameters;
        return $result;
    }

    /**
     * @return \Pressmind\ORM\Object\Touristic\Transport[]|null
     * @throws Exception
     */
    public function test()
    {
        if(isset($this->parameters['id'])) {
            $mediaObject = new MediaObject($this->parameters['id'], true);
            return $mediaObject->getAllAvailableTransports();
        }
        return null;
    }
}
