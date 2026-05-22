<?php

declare(strict_types=1);

namespace Pressmind\MCP\Tool;

use Exception;
use PhpMcp\Server\Attributes\McpTool;
use Pressmind\MCP\Service\SearchService;

/**
 * MCP tool: touristic facets (board, transport, starting points, duration, price) from filter run.
 */
class FilterOptionsTool
{
    public function __construct(
        private readonly SearchService $searchService
    ) {
    }

    #[McpTool(name: 'get_filter_options', description: 'Return board_types, transport_types, startingpoint_options, duration min/max/allowed_ranges, and price min/max from search facets.')]
    public function get_filter_options(): string
    {
        try {
            $data = $this->searchService->getFilterOptions();

            return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        } catch (Exception $e) {
            return json_encode(['error' => true, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        }
    }
}
