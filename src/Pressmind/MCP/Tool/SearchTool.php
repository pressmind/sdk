<?php

declare(strict_types=1);

namespace Pressmind\MCP\Tool;

use Exception;
use InvalidArgumentException;
use PhpMcp\Server\Attributes\McpTool;
use Pressmind\MCP\Service\SearchService;

/**
 * MCP tool: travel search via Query::getResult().
 */
class SearchTool
{
    public function __construct(
        private readonly SearchService $searchService
    ) {
    }

    /**
     * Search travel offers via Query::getResult(). Returns JSON: { results, total_result, current_page, pages }.
     *
     * @param  string|null  $query  Full-text search term (pm-t)
     * @param  string|null  $destination  Category id(s) for configured destination field (default zielgebiet_default)
     * @param  string|null  $travel_type  Category id(s) for configured travel type field (default reiseart_default)
     * @param  string|null  $categories  JSON object: arbitrary pm-c fields, e.g. {"sterne_default":"123"}
     * @param  string|null  $date_from  YYYY-MM-DD
     * @param  string|null  $date_to  YYYY-MM-DD
     * @param  string|null  $date_expression  Natural-language date range (DE/EN), e.g. "next month", "im Juli", "diesen Sommer" — sets pm-dr when date_from/to not set
     * @param  int|null  $price_min
     * @param  int|null  $price_max
     * @param  int|null  $duration_min
     * @param  int|null  $duration_max
     * @param  string|null  $transport_type
     * @param  string|null  $board_type
     * @param  string|null  $object_type  pm-ot (comma-separated ids)
     * @param  string|null  $order  e.g. price-asc, date_departure-asc
     * @param  int  $page  1-based page index
     * @param  int  $page_size  Max 100
     * @param  int  $occupancy  Default 2 (persons per room)
     * @param  bool  $semantic  When true, use OpenSearch vector/hybrid search (requires data.search_opensearch.vector.enabled)
     */
    #[McpTool(name: 'search', description: 'Search travel products (Pressmind / Travelshop). Returns JSON with results array (id, title, url, text, image_url, price, duration, departure_date). Use categories JSON for extra pm-c fields. Optional semantic=true for vector/hybrid ranking when configured.')]
    public function search(
        ?string $query = null,
        ?string $destination = null,
        ?string $travel_type = null,
        ?string $categories = null,
        ?string $date_from = null,
        ?string $date_to = null,
        ?string $date_expression = null,
        ?int $price_min = null,
        ?int $price_max = null,
        ?int $duration_min = null,
        ?int $duration_max = null,
        ?string $transport_type = null,
        ?string $board_type = null,
        ?string $object_type = null,
        ?string $order = null,
        int $page = 1,
        int $page_size = 10,
        int $occupancy = 2,
        bool $semantic = false
    ): string {
        $args = [
            'page' => $page,
            'page_size' => $page_size,
            'occupancy' => $occupancy,
        ];
        if ($semantic) {
            $args['semantic'] = true;
        }
        if ($query !== null && $query !== '') {
            $args['query'] = $query;
        }
        if ($destination !== null && $destination !== '') {
            $args['destination'] = $destination;
        }
        if ($travel_type !== null && $travel_type !== '') {
            $args['travel_type'] = $travel_type;
        }
        if ($categories !== null && $categories !== '') {
            $args['categories'] = $categories;
        }
        if ($date_from !== null && $date_from !== '') {
            $args['date_from'] = $date_from;
        }
        if ($date_to !== null && $date_to !== '') {
            $args['date_to'] = $date_to;
        }
        if ($date_expression !== null && $date_expression !== '') {
            $args['date_expression'] = $date_expression;
        }
        if ($price_min !== null) {
            $args['price_min'] = $price_min;
        }
        if ($price_max !== null) {
            $args['price_max'] = $price_max;
        }
        if ($duration_min !== null) {
            $args['duration_min'] = $duration_min;
        }
        if ($duration_max !== null) {
            $args['duration_max'] = $duration_max;
        }
        if ($transport_type !== null && $transport_type !== '') {
            $args['transport_type'] = $transport_type;
        }
        if ($board_type !== null && $board_type !== '') {
            $args['board_type'] = $board_type;
        }
        if ($object_type !== null && $object_type !== '') {
            $args['object_type'] = $object_type;
        }
        if ($order !== null && $order !== '') {
            $args['order'] = $order;
        }

        try {
            $data = $this->searchService->search($args);
        } catch (InvalidArgumentException $e) {
            return json_encode(['error' => true, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        } catch (Exception $e) {
            return json_encode(['error' => true, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        }

        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
