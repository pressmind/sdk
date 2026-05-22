<?php

declare(strict_types=1);

namespace Pressmind\MCP\Tool;

use Exception;
use PhpMcp\Server\Attributes\McpTool;
use Pressmind\MCP\Service\ProductService;

/**
 * MCP tool: departure calendar for one product.
 */
class CalendarTool
{
    public function __construct(
        private readonly ProductService $productService
    ) {
    }

    /**
     * Calendar for one product. Requires cheapest price + CalendarFilter fields (same as app-feed calendar.php GET).
     */
    #[McpTool(name: 'get_calendar', description: 'Departure calendar for id_media_object. Pass filter fields (id_booking_package, id_housing_package, transport_type, duration, startingpoint_id_city, airport, agency) as needed; offer id for calendar is taken from cheapest price if not set.')]
    public function get_calendar(
        string $id,
        ?string $month = null,
        ?string $id_booking_package = null,
        ?string $id_housing_package = null,
        ?string $housing_package_code_ibe = null,
        ?string $transport_type = null,
        ?string $duration = null,
        ?string $startingpoint_id_city = null,
        ?string $airport = null,
        ?string $agency = null,
        ?string $housing_package_id_name = null
    ): string {
        if (! is_numeric($id)) {
            return json_encode(['error' => true, 'message' => 'id must be numeric id_media_object'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        }

        $calendarArgs = array_filter([
            'id_booking_package' => $id_booking_package,
            'id_housing_package' => $id_housing_package,
            'housing_package_code_ibe' => $housing_package_code_ibe,
            'transport_type' => $transport_type,
            'duration' => $duration,
            'startingpoint_id_city' => $startingpoint_id_city,
            'airport' => $airport,
            'agency' => $agency,
            'housing_package_id_name' => $housing_package_id_name,
        ], static fn ($v) => $v !== null && $v !== '');

        try {
            $payload = $this->productService->getCalendar((int) $id, $calendarArgs, $month);
        } catch (Exception $e) {
            return json_encode(['error' => true, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        }

        return json_encode(['payload' => $payload], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
