<?php

declare(strict_types=1);

namespace Pressmind\MCP\Tool;

use Exception;
use PhpMcp\Server\Attributes\McpTool;
use Pressmind\MCP\Service\ProductService;

/**
 * MCP tool: boarding points (starting points) per outbound transport, incl. pickup PLZ ranges.
 */
class StartingPointsTool
{
    public function __construct(
        private readonly ProductService $productService
    ) {
    }

    /**
     * Boarding / starting points for outbound transports (way=1): Transport → Startingpoint → options.
     * Pickup (door-to-door) options have is_pickup_service and zip_ranges (postal code ranges).
     */
    #[McpTool(
        name: 'get_starting_points',
        description: 'List boarding/starting points for id_media_object: outbound transports (way=1) with Startingpoint and options. Pickup services use is_pickup_service + zip_ranges (PLZ ranges). Optional id_booking_package filters dates. Based on future departures (getAllAvailableDates).'
    )]
    public function get_starting_points(
        string $id,
        ?string $id_booking_package = null
    ): string {
        if (! is_numeric($id)) {
            return json_encode(['error' => true, 'message' => 'id must be numeric id_media_object'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        }

        try {
            $data = $this->productService->getStartingPoints((int) $id, $id_booking_package);
        } catch (Exception $e) {
            return json_encode(['error' => true, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        }

        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
