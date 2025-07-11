<?php
namespace Pressmind\Search\Query;

use Pressmind\Search\SearchType;

class Filter
{
    public $request;
    public $occupancy = 2;
    public $page_size = 12;
    public $getFilters = false;
    public $returnFiltersOnly = false;
    public $ttl_filter = null;
    public $ttl_search = null;
    public $output = null;
    public $preview_date = null;
    public $custom_conditions = [];
    public $allowed_visibilities = [30];
    public SearchType $search_type = SearchType::DEFAULT;
}