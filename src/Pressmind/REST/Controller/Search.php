<?php


namespace Pressmind\REST\Controller;


use Exception;
use Pressmind\Search\Condition\ConditionInterface;
use Pressmind\Search\Condition\Validity;
use Pressmind\Search\Filter\FilterInterface;
use Pressmind\Search\Paginator;

class Search
{
    /**
     * @param array $searchparameters
     * @return array
     * @throws Exception
     */
    public function search($searchparameters) {
        $searchparameters = json_decode(json_encode($searchparameters));
        $search_conditions = $searchparameters->conditions;
        $search_sort = isset($searchparameters->sort) ? $searchparameters->sort : null;
        $search_limit = isset($searchparameters->limit) ? $searchparameters->limit : null;
        $return_filters_only = isset($searchparameters->return_filters_only) ? boolval($searchparameters->return_filters_only) : false;
        $conditions = [];

        foreach($search_conditions as $search_condition) {
            $condition_name = 'Pressmind\\Search\\Condition\\' . ucfirst($search_condition->type);
            /** @var ConditionInterface $condition */
            $condition = new $condition_name();
            $condition->setConfig($search_condition->config);
            $conditions[] = $condition;
        }

        $sort = !is_null($search_sort) ? [$search_sort->property => $search_sort->direction] : null;
        $limit = !is_null($search_limit) ? ['start' => $search_limit->start, 'length' => $search_limit->length] : null;
        $search = new \Pressmind\Search($conditions, $limit, $sort);

        if(isset($searchparameters->pagination)) {
            $paginator = new Paginator($searchparameters->pagination->pageSize, $searchparameters->pagination->currentPage);
            $search->setPaginator($paginator);
        }

        $result = [];

        try {
            $trips = $search->getResults();
        } catch (Exception $e) {
            $result['error'] = $e->getMessage();
            return $result;
        }

        if($return_filters_only === false) {
            $result['media_objects'] = [];

            foreach ($trips as $trip) {
                if(isset($searchparameters->apiTemplate) && !empty($searchparameters->apiTemplate)) {
                    $result['media_objects'][] = $trip->renderApiOutputTemplate($searchparameters->apiTemplate);
                } else {
                    $result['media_objects'][] = $trip;
                }
            }
        }

        $result['totalResultCount'] = $search->getTotalResultCount();
        $result['resultCount'] =  count($trips);

        if($search->getPaginator() != null) {
            $result['pagination'] = [];
            $result['pagination']['totalPages'] = $search->getPaginator()->getTotalPages();
            $result['pagination']['pageSize'] = $search->getPaginator()->getPageSize();
            $result['pagination']['currentPage'] = $search->getPaginator()->getCurrentPage();
        }

        if(isset($searchparameters->filters) && !empty($searchparameters->filters)) {
            $filters = [];
            foreach ($searchparameters->filters as $filter_name => $filter) {
                $filter_class = 'Pressmind\\Search\\Filter\\' . ucfirst($filter->type);
                /** @var FilterInterface $new_filter */
                $new_filter = new $filter_class();
                $new_filter->setSearch($search);
                $new_filter->setConfig($filter->config);
                $filters[$filter_name] = $new_filter->getResult();
                //$filters[$filter_name] = $filter_class;
            }
            $result['filters'] = $filters;
        }
        /*$result['debug'] = [
            'query' => $search->getQuery(),
            'values' => $search->getValues(),
            'foo' => $search->getTotalResultCount(),
            //'pagesize' => $search->getPaginator()->getPageSize(),
            'searchParameters' => $searchparameters
        ];*/

        return $result;
    }
}
