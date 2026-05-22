<?php

declare(strict_types=1);

namespace Pressmind\MCP\Tool;

use Exception;
use PhpMcp\Server\Attributes\McpTool;
use Pressmind\MCP\Service\ProductService;

/**
 * MCP tool: price matrix from cheapest_price_speed with per-offer booking URLs.
 */
class CheapestPricesTool
{
    public function __construct(
        private readonly ProductService $productService
    ) {
    }

    /**
     * All bookable price rows for one product (filtered). Each row includes a booking_url for that offer.
     */
    #[McpTool(
        name: 'get_cheapest_prices',
        description: 'Price matrix for id_media_object: rows from cheapest_price_speed with filters (duration, dates, price range, occupancy, transport, packages, airport, startingpoint). Returns filter_options (facets) and prices[] with booking_url per row. Default limit 50, max 200. Order: price-asc (default), date-asc, price-desc.'
    )]
    public function get_cheapest_prices(
        string $id,
        ?int $duration_from = null,
        ?int $duration_to = null,
        ?string $date_from = null,
        ?string $date_to = null,
        ?float $price_min = null,
        ?float $price_max = null,
        ?int $occupancy = null,
        ?string $transport_type = null,
        ?string $id_booking_package = null,
        ?string $id_housing_package = null,
        ?string $startingpoint_id_city = null,
        ?string $airport = null,
        ?string $order = null,
        ?int $limit = null
    ): string {
        if (! is_numeric($id)) {
            return json_encode(['error' => true, 'message' => 'id must be numeric id_media_object'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        }

        $params = [
            'order' => $order,
            'limit' => $limit,
        ];
        if ($duration_from !== null) {
            $params['duration_from'] = $duration_from;
        }
        if ($duration_to !== null) {
            $params['duration_to'] = $duration_to;
        }
        if ($date_from !== null && $date_from !== '') {
            $params['date_from'] = $date_from;
        }
        if ($date_to !== null && $date_to !== '') {
            $params['date_to'] = $date_to;
        }
        if ($price_min !== null) {
            $params['price_min'] = $price_min;
        }
        if ($price_max !== null) {
            $params['price_max'] = $price_max;
        }
        if ($occupancy !== null) {
            $params['occupancy'] = $occupancy;
        }
        if ($transport_type !== null && $transport_type !== '') {
            $params['transport_type'] = $transport_type;
        }
        if ($id_booking_package !== null && $id_booking_package !== '') {
            $params['id_booking_package'] = $id_booking_package;
        }
        if ($id_housing_package !== null && $id_housing_package !== '') {
            $params['id_housing_package'] = $id_housing_package;
        }
        if ($startingpoint_id_city !== null && $startingpoint_id_city !== '') {
            $params['startingpoint_id_city'] = $startingpoint_id_city;
        }
        if ($airport !== null && $airport !== '') {
            $params['airport'] = $airport;
        }

        try {
            $data = $this->productService->getCheapestPricesMatrix((int) $id, $params);
        } catch (Exception $e) {
            return json_encode(['error' => true, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        }

        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
