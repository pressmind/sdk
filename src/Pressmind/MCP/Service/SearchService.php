<?php

declare(strict_types=1);

namespace Pressmind\MCP\Service;

use InvalidArgumentException;
use JsonException;
use Pressmind\Registry;
use Pressmind\Search\Embedding\QueryEmbedding;
use Pressmind\Search\OpenSearch;
use Pressmind\Search\Query;
use Pressmind\Search\Query\Filter;

/**
 * Travel search via Query::getResult() (same pipeline as Travelshop).
 * All search-global settings are injected through the constructor (set by ServerFactory).
 */
class SearchService
{
    private ?string $languageCode;

    private int $touristicOrigin;

    /** @var mixed */
    private $agencyIdPriceIndex;

    /** @var mixed */
    private $groupKeys;

    private bool $calendarShowDepartures;

    /** @var mixed */
    private $atlasActive;

    /** @var mixed */
    private $atlasDefinition;

    private string $destinationCategoryField;

    private string $travelTypeCategoryField;

    /** @var list<string> */
    private array $categoryFields;

    private string $siteUrl;

    /**
     * @param  array<string, mixed>  $searchConfig  Flat config from ServerFactory options['search']
     * @param  string  $siteUrl  Public base URL for absolute product URLs
     */
    public function __construct(array $searchConfig = [], string $siteUrl = '')
    {
        $this->siteUrl = rtrim($siteUrl, '/');
        $this->languageCode = isset($searchConfig['language_code']) && is_string($searchConfig['language_code']) ? $searchConfig['language_code'] : null;
        $this->touristicOrigin = isset($searchConfig['touristic_origin']) ? (int) $searchConfig['touristic_origin'] : 0;
        $this->agencyIdPriceIndex = $searchConfig['agency_id_price_index'] ?? null;
        $this->groupKeys = $searchConfig['group_keys'] ?? null;
        $this->calendarShowDepartures = ! empty($searchConfig['calendar_show_departures']);
        $this->destinationCategoryField = isset($searchConfig['destination_category_field']) && is_string($searchConfig['destination_category_field']) && $searchConfig['destination_category_field'] !== ''
            ? $searchConfig['destination_category_field']
            : 'zielgebiet_default';
        $this->travelTypeCategoryField = isset($searchConfig['travel_type_category_field']) && is_string($searchConfig['travel_type_category_field']) && $searchConfig['travel_type_category_field'] !== ''
            ? $searchConfig['travel_type_category_field']
            : 'reiseart_default';

        $this->categoryFields = [];
        if (isset($searchConfig['category_fields']) && is_array($searchConfig['category_fields'])) {
            foreach ($searchConfig['category_fields'] as $fn) {
                if (is_string($fn) && $fn !== '') {
                    $this->categoryFields[$fn] = true;
                }
            }
        }
        $this->categoryFields = array_keys($this->categoryFields);
        sort($this->categoryFields);

        if (isset($searchConfig['atlas']) && is_array($searchConfig['atlas'])) {
            $this->atlasActive = $searchConfig['atlas']['active'] ?? null;
            $this->atlasDefinition = $searchConfig['atlas']['definition'] ?? null;
        } else {
            $this->atlasActive = null;
            $this->atlasDefinition = null;
        }
    }

    /**
     * Apply Query static flags from constructor-injected config.
     */
    private function applyQueryGlobals(): void
    {
        Query::$agency_id_price_index = $this->agencyIdPriceIndex;
        Query::$touristic_origin = $this->touristicOrigin;
        Query::$language_code = $this->languageCode;
        Query::$group_keys = $this->groupKeys;
        Query::$calendar_show_departures = $this->calendarShowDepartures;
        Query::$atlas_active = $this->atlasActive;
        Query::$atlas_definition = $this->atlasDefinition;
    }

    /**
     * Category field names from index config (or discovered when empty).
     *
     * @return list<string>
     */
    public function getAvailableCategoryFields(): array
    {
        if ($this->categoryFields !== []) {
            return $this->categoryFields;
        }

        $this->applyQueryGlobals();

        $filter = new Filter();
        $filter->request = [];
        $filter->page_size = 1;
        $filter->getFilters = true;
        $filter->returnFiltersOnly = true;

        $raw = Query::getResult($filter);

        $keys = array_keys($raw['categories'] ?? []);
        sort($keys);

        return $keys;
    }

    /**
     * Touristic facets from the filter run (board, transport, starting points, duration, price).
     *
     * @return array<string, mixed>
     */
    public function getFilterOptions(): array
    {
        $this->applyQueryGlobals();

        $filter = new Filter();
        $filter->request = [];
        $filter->page_size = 1;
        $filter->getFilters = true;
        $filter->returnFiltersOnly = true;

        $raw = Query::getResult($filter);

        return [
            'board_types' => $this->normalizeKeyedFacetMap($raw['board_types'] ?? []),
            'transport_types' => $this->normalizeKeyedFacetMap($raw['transport_types'] ?? []),
            'startingpoint_options' => $this->normalizeStartingpointOptions($raw['startingpoint_options'] ?? []),
            'duration' => [
                'min' => $raw['duration_min'] ?? null,
                'max' => $raw['duration_max'] ?? null,
                'allowed_ranges' => $raw['duration_allowed_ranges'] ?? [],
            ],
            'price' => [
                'min' => $raw['price_min'] ?? null,
                'max' => $raw['price_max'] ?? null,
            ],
        ];
    }

    /**
     * @param  array<string|int, mixed>  $map
     * @return list<array{id: string, name: string, count_in_system: mixed, count_in_search: mixed}>
     */
    private function normalizeKeyedFacetMap(array $map): array
    {
        $out = [];
        foreach ($map as $key => $item) {
            if (! is_object($item)) {
                continue;
            }
            $id = $item->_id ?? $key;
            $out[] = [
                'id' => (string) $id,
                'name' => (string) ($item->name ?? $id),
                'count_in_system' => $item->count_in_system ?? null,
                'count_in_search' => $item->count_in_search ?? null,
            ];
        }

        return $out;
    }

    /**
     * @param  list<mixed>  $list
     * @return list<array{id: string, city: string, count_in_system: mixed, count_in_search: mixed}>
     */
    private function normalizeStartingpointOptions(array $list): array
    {
        $out = [];
        foreach ($list as $item) {
            if (! is_object($item)) {
                continue;
            }
            $out[] = [
                'id' => isset($item->id) ? (string) $item->id : '',
                'city' => (string) ($item->city ?? ''),
                'count_in_system' => $item->count_in_system ?? null,
                'count_in_search' => $item->count_in_search ?? null,
            ];
        }

        return $out;
    }

    /**
     * Build pm-* request array from high-level MCP arguments.
     *
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    public function buildRequestFromArgs(array $args): array
    {
        $req = [];

        if (! empty($args['query']) && is_string($args['query'])) {
            $req['pm-t'] = $args['query'];
        }

        $categories = $this->mergeCategoryArguments($args);

        if ($categories !== []) {
            $req['pm-c'] = $categories;
        }

        if (! empty($args['date_from']) && is_string($args['date_from']) && ! empty($args['date_to']) && is_string($args['date_to'])) {
            $from = $this->normalizeDateToYmd($args['date_from']);
            $to = $this->normalizeDateToYmd($args['date_to']);
            if ($from !== null && $to !== null) {
                $req['pm-dr'] = $from . '-' . $to;
            }
        } elseif (! empty($args['date_expression']) && is_string($args['date_expression'])) {
            $range = NaturalDateRangeParser::parse($args['date_expression']);
            if ($range !== null) {
                $req['pm-dr'] = $range[0] . '-' . $range[1];
            }
        }

        if (isset($args['price_min'], $args['price_max']) && is_numeric($args['price_min']) && is_numeric($args['price_max'])) {
            $req['pm-pr'] = (int) $args['price_min'] . '-' . (int) $args['price_max'];
        }

        if (isset($args['duration_min'], $args['duration_max']) && is_numeric($args['duration_min']) && is_numeric($args['duration_max'])) {
            $req['pm-du'] = (int) $args['duration_min'] . '-' . (int) $args['duration_max'];
        }

        if (! empty($args['transport_type']) && is_string($args['transport_type'])) {
            $req['pm-tr'] = $args['transport_type'];
        }

        if (! empty($args['board_type']) && is_string($args['board_type'])) {
            $req['pm-bt'] = $args['board_type'];
        }

        if (! empty($args['object_type']) && is_string($args['object_type'])) {
            $req['pm-ot'] = $args['object_type'];
        }

        $page = isset($args['page']) && is_numeric($args['page']) ? max(1, (int) $args['page']) : 1;
        $pageSize = isset($args['page_size']) && is_numeric($args['page_size']) ? min(100, max(1, (int) $args['page_size'])) : 10;
        $req['pm-l'] = $page . ',' . $pageSize;

        if (! empty($args['order']) && is_string($args['order'])) {
            $req['pm-o'] = $args['order'];
        }

        return $req;
    }

    /**
     * Merge generic `categories` JSON/object with destination and travel_type convenience args.
     *
     * @param  array<string, mixed>  $args
     * @return array<string, string>
     */
    private function mergeCategoryArguments(array $args): array
    {
        $categories = [];

        if (isset($args['categories'])) {
            $decoded = null;
            if (is_string($args['categories']) && $args['categories'] !== '') {
                try {
                    $decoded = json_decode($args['categories'], true, 512, JSON_THROW_ON_ERROR);
                } catch (JsonException $e) {
                    throw new InvalidArgumentException('categories must be valid JSON object: ' . $e->getMessage(), 0, $e);
                }
            } elseif (is_array($args['categories'])) {
                $decoded = $args['categories'];
            }

            if (is_array($decoded)) {
                foreach ($decoded as $field => $value) {
                    if (! is_string($field) || $field === '') {
                        continue;
                    }
                    if ($value === null || $value === '') {
                        continue;
                    }
                    if (is_string($value) || is_int($value) || is_float($value)) {
                        $categories[$field] = (string) $value;
                    }
                }
            }
        }

        if (! empty($args['destination'])) {
            $dest = $args['destination'];
            if (is_string($dest) || is_int($dest)) {
                $categories[$this->destinationCategoryField] = (string) $dest;
            }
        }

        if (! empty($args['travel_type'])) {
            $tt = $args['travel_type'];
            if (is_string($tt) || is_int($tt)) {
                $categories[$this->travelTypeCategoryField] = (string) $tt;
            }
        }

        return $categories;
    }

    private function normalizeDateToYmd(string $date): ?string
    {
        $date = trim($date);
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $m)) {
            return $m[1] . $m[2] . $m[3];
        }
        if (preg_match('/^(\d{8})$/', $date)) {
            return $date;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array{results: list<array<string, mixed>>, total_result: int, current_page: int, pages: int}
     */
    public function search(array $args): array
    {
        $this->applyQueryGlobals();

        if (isset($args['semantic']) && filter_var($args['semantic'], FILTER_VALIDATE_BOOLEAN)) {
            $cfg = Registry::getInstance()->get('config');
            $vec = $cfg['data']['search_opensearch']['vector'] ?? [];
            if (empty($vec['enabled'])) {
                throw new InvalidArgumentException('semantic search requires data.search_opensearch.vector.enabled');
            }

            return $this->searchSemantic($args);
        }

        $pageSize = isset($args['page_size']) && is_numeric($args['page_size']) ? min(100, max(1, (int) $args['page_size'])) : 10;
        $occupancy = isset($args['occupancy']) && is_numeric($args['occupancy']) ? (int) $args['occupancy'] : 2;

        $filter = new Filter();
        $filter->request = $this->buildRequestFromArgs($args);
        $filter->occupancy = $occupancy;
        $filter->page_size = $pageSize;
        $filter->getFilters = false;
        $filter->returnFiltersOnly = false;

        if (! empty($filter->request['pm-dr'])) {
            $filter->request['pm-o'] = $filter->request['pm-o'] ?? 'date_departure-asc';
            $filter->output = 'date_list';
        }

        $raw = Query::getResult($filter);

        $results = [];
        foreach ($raw['items'] ?? [] as $item) {
            $results[] = $this->mapItemToResult($item);
        }

        return [
            'results' => $results,
            'total_result' => (int) ($raw['total_result'] ?? 0),
            'current_page' => (int) ($raw['current_page'] ?? 1),
            'pages' => (int) ($raw['pages'] ?? 0),
        ];
    }

    /**
     * Vector / hybrid search: OpenSearch k-NN + same MongoDB pipeline via pm-id + list order.
     *
     * @param  array<string, mixed>  $args  Same shape as {@see search()} (requires non-empty `query`)
     * @return array{results: list<array<string, mixed>>, total_result: int, current_page: int, pages: int}
     */
    public function searchSemantic(array $args): array
    {
        $this->applyQueryGlobals();
        $config = Registry::getInstance()->get('config');
        $vec = $config['data']['search_opensearch']['vector'] ?? [];
        if (empty($vec['enabled']) || empty($args['query']) || ! is_string($args['query']) || trim($args['query']) === '') {
            throw new InvalidArgumentException('semantic search requires vector.enabled and a non-empty query string');
        }
        $queryText = trim($args['query']);
        $vector = QueryEmbedding::getVector($queryText, $vec);
        $k = (int) ($vec['k'] ?? 50);
        $os = new OpenSearch($queryText, $this->languageCode, $k);
        $mode = isset($vec['search_mode']) ? (string) $vec['search_mode'] : 'hybrid';
        if ($mode === 'vector') {
            $ids = $os->getVectorSearchResultIds($vector, $k);
        } else {
            $ids = $os->getHybridSearchResultIds($queryText, $vector, $k);
        }
        if ($ids === []) {
            $ids = ['0'];
        }
        $req = $this->buildRequestFromArgs($args);
        unset($req['pm-t']);
        $req['pm-id'] = implode(',', $ids);
        if (empty($req['pm-o'])) {
            $req['pm-o'] = 'list';
        }

        $pageSize = isset($args['page_size']) && is_numeric($args['page_size']) ? min(100, max(1, (int) $args['page_size'])) : 10;
        $occupancy = isset($args['occupancy']) && is_numeric($args['occupancy']) ? (int) $args['occupancy'] : 2;

        $filter = new Filter();
        $filter->request = $req;
        $filter->occupancy = $occupancy;
        $filter->page_size = $pageSize;
        $filter->getFilters = false;
        $filter->returnFiltersOnly = false;

        if (! empty($filter->request['pm-dr'])) {
            $filter->request['pm-o'] = $filter->request['pm-o'] ?? 'date_departure-asc';
            $filter->output = 'date_list';
        }

        $raw = Query::getResult($filter);

        $results = [];
        foreach ($raw['items'] ?? [] as $item) {
            $results[] = $this->mapItemToResult($item);
        }

        return [
            'results' => $results,
            'total_result' => (int) ($raw['total_result'] ?? 0),
            'current_page' => (int) ($raw['current_page'] ?? 1),
            'pages' => (int) ($raw['pages'] ?? 0),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getDestinations(?string $parentId = null): array
    {
        return $this->getCategoryFacets($this->destinationCategoryField, $parentId);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getTravelTypes(?string $parentId = null): array
    {
        return $this->getCategoryFacets($this->travelTypeCategoryField, $parentId);
    }

    /**
     * Category facet rows for one indexed field_name.
     *
     * @return list<array<string, mixed>>
     */
    public function getCategoryFacets(string $categoryField, ?string $parentId = null): array
    {
        if ($this->categoryFields !== [] && ! in_array($categoryField, $this->categoryFields, true)) {
            throw new InvalidArgumentException('Unknown category field: ' . $categoryField);
        }

        $this->applyQueryGlobals();

        $filter = new Filter();
        $filter->request = [];
        $filter->page_size = 1;
        $filter->getFilters = true;
        $filter->returnFiltersOnly = true;

        $raw = Query::getResult($filter);
        $categories = $raw['categories'][$categoryField] ?? [];

        $out = [];
        foreach ($categories as $level => $levelItems) {
            if (! is_array($levelItems)) {
                continue;
            }
            foreach ($levelItems as $row) {
                if (! is_object($row)) {
                    continue;
                }
                $out[] = [
                    'id' => $row->id_item ?? null,
                    'name' => $row->name ?? $row->item ?? '',
                    'level' => $row->level ?? $level,
                    'id_parent' => $row->id_parent ?? null,
                    'count_in_system' => $row->count_in_system ?? null,
                    'count_in_search' => $row->count_in_search ?? null,
                ];
            }
        }

        if ($parentId !== null) {
            $out = array_values(array_filter($out, function ($r) use ($parentId) {
                return isset($r['id_parent']) && (string) $r['id_parent'] === (string) $parentId;
            }));
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $item  Single item from Query::getResult items
     * @return array<string, mixed>
     */
    private function mapItemToResult(array $item): array
    {
        $title = $item['name'] ?? $item['headline'] ?? '';
        $text = '';
        if (! empty($item['subline']) && is_string($item['subline'])) {
            $text = $item['subline'];
        } elseif (! empty($item['description']) && is_string($item['description'])) {
            $text = mb_substr(strip_tags($item['description']), 0, 500);
        }

        $imageUrl = null;
        if (! empty($item['image']) && is_array($item['image']) && ! empty($item['image']['url'])) {
            $imageUrl = $item['image']['url'];
        }

        $price = null;
        $duration = null;
        $departure = null;
        if (! empty($item['cheapest_price']) && is_object($item['cheapest_price'])) {
            $cp = $item['cheapest_price'];
            $price = $cp->price_total ?? null;
            $duration = $cp->duration ?? null;
            if (! empty($cp->date_departures) && is_array($cp->date_departures) && $cp->date_departures[0] instanceof \DateTimeInterface) {
                $departure = $cp->date_departures[0]->format('Y-m-d');
            }
        }

        $id = $item['id_media_object'] ?? null;

        $url = $item['url'] ?? '';
        if ($url !== '' && $this->siteUrl !== '' && ! str_starts_with($url, 'http')) {
            $url = $this->siteUrl . '/' . ltrim($url, '/');
        }

        return [
            'id' => $id !== null ? (string) $id : '',
            'title' => is_string($title) ? $title : '',
            'text' => $text,
            'url' => $url,
            'image_url' => $imageUrl,
            'price' => $price,
            'duration' => $duration,
            'departure_date' => $departure,
        ];
    }
}
