<?php

declare(strict_types=1);

namespace Pressmind\MCP\Tool;

use Exception;
use PhpMcp\Server\Attributes\McpTool;
use Pressmind\MCP\Service\ProductService;

/**
 * MCP tool: extras, tickets, sightseeing (Touristic Option) with required groups.
 */
class TouristicOptionsTool
{
    public function __construct(
        private readonly ProductService $productService
    ) {
    }

    /**
     * Touristic add-ons (extras, tickets, sightseeing) with prices and required_group / selection_type.
     */
    #[McpTool(
        name: 'get_touristic_options',
        description: 'List touristic options (extras, tickets, sightseeing) for id_media_object: prices, required, required_group, selection_type (MIN_ONE_OF_GROUP, EXACTLY_ONE_OF_GROUP, …), auto_book. Optional id_booking_package filter; type: extra|ticket|sightseeing or omit for all.'
    )]
    public function get_touristic_options(
        string $id,
        ?string $id_booking_package = null,
        ?string $type = null
    ): string {
        if (! is_numeric($id)) {
            return json_encode(['error' => true, 'message' => 'id must be numeric id_media_object'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        }

        if ($type !== null && $type !== '') {
            $t = strtolower(trim($type));
            if (! in_array($t, ['extra', 'ticket', 'sightseeing'], true)) {
                return json_encode(['error' => true, 'message' => 'type must be extra, ticket, sightseeing, or empty'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            }
        }

        try {
            $data = $this->productService->getTouristicOptions((int) $id, $id_booking_package, $type);
        } catch (Exception $e) {
            return json_encode(['error' => true, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        }

        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
