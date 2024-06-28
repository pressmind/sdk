<?php
namespace Pressmind\Search\Query;

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
}