<?php

declare(strict_types=1);

namespace Pressmind\MCP\Tool;

use Exception;
use InvalidArgumentException;
use JsonException;
use PhpMcp\Server\Attributes\McpTool;
use Pressmind\MCP\Service\SearchService;

/**
 * MCP tool: vector / hybrid search via OpenSearch k-NN + Query::getResult().
 */
class SemanticSearchTool
{
    public function __construct(
        private readonly SearchService $searchService
    ) {
    }

    /**
     * Semantic (vector / hybrid) travel search. Same filters as `search` but ranking from OpenSearch k-NN. Requires `data.search_opensearch.vector.enabled` and indexed vectors.
     *
     * @param  string|null  $query  Required. Natural-language query to embed.
     */
    #[McpTool(name: 'semantic_search', description: 'Semantic travel search (OpenSearch k-NN + hybrid lexical fusion when configured). Same filters as search; requires vector index. Returns JSON: results, total_result, current_page, pages.')]
    public function semantic_search(
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
        int $occupancy = 2
    ): string {
        $args = [
            'page' => $page,
            'page_size' => $page_size,
            'occupancy' => $occupancy,
        ];
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
            $data = $this->searchService->searchSemantic($args);
        } catch (InvalidArgumentException $e) {
            return json_encode(['error' => true, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            return json_encode(['error' => true, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        } catch (Exception $e) {
            return json_encode(['error' => true, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        }

        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
