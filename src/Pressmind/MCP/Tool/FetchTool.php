<?php

declare(strict_types=1);

namespace Pressmind\MCP\Tool;

use Exception;
use PhpMcp\Server\Attributes\McpTool;
use Pressmind\MCP\Service\ProductService;

/**
 * MCP tool: load one travel product by id_media_object.
 */
class FetchTool
{
    public function __construct(
        private readonly ProductService $productService
    ) {
    }

    /**
     * Full product document for one id_media_object (ORM + booking URL when possible).
     */
    #[McpTool(name: 'fetch', description: 'Load full travel product by Pressmind id_media_object. Returns JSON: id, title, text, url, metadata.')]
    public function fetch(string $id): string
    {
        if (! is_numeric($id)) {
            return json_encode(['error' => true, 'message' => 'id must be numeric id_media_object'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        }

        try {
            $data = $this->productService->fetchDetails((int) $id);
        } catch (Exception $e) {
            return json_encode(['error' => true, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        }

        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
