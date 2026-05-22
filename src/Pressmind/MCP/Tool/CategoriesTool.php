<?php

declare(strict_types=1);

namespace Pressmind\MCP\Tool;

use Exception;
use InvalidArgumentException;
use PhpMcp\Server\Attributes\McpTool;
use Pressmind\MCP\Service\SearchService;

/**
 * MCP tool: category tree facets for any indexed field_name (pm-c keys).
 */
class CategoriesTool
{
    public function __construct(
        private readonly SearchService $searchService
    ) {
    }

    /**
     * List category facet rows for one field, or list available field names when field_name is omitted.
     */
    #[McpTool(name: 'get_categories', description: 'List category facets for a field_name (e.g. zielgebiet_default), or omit field_name to get available_fields. Optional parent_id filters children.')]
    public function get_categories(?string $field_name = null, ?string $parent_id = null): string
    {
        try {
            if ($field_name === null || $field_name === '') {
                $fields = $this->searchService->getAvailableCategoryFields();

                return json_encode(['available_fields' => $fields], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            }

            $list = $this->searchService->getCategoryFacets($field_name, $parent_id);

            return json_encode(['categories' => $list], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        } catch (InvalidArgumentException $e) {
            return json_encode(['error' => true, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        } catch (Exception $e) {
            return json_encode(['error' => true, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        }
    }
}
