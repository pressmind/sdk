<?php

declare(strict_types=1);

namespace Pressmind\MCP;

use PhpMcp\Server\Defaults\BasicContainer;
use PhpMcp\Server\Model\Capabilities;
use PhpMcp\Server\Server;
use Pressmind\MCP\Service\ProductService;
use Pressmind\MCP\Service\SearchService;
use Pressmind\MCP\Tool\CalendarTool;
use Pressmind\MCP\Tool\CategoriesTool;
use Pressmind\MCP\Tool\CheapestPricesTool;
use Pressmind\MCP\Tool\FetchTool;
use Pressmind\MCP\Tool\FilterOptionsTool;
use Pressmind\MCP\Tool\SearchTool;
use Pressmind\MCP\Tool\SemanticSearchTool;
use Pressmind\MCP\Tool\StartingPointsTool;
use Pressmind\MCP\Tool\TouristicOptionsTool;

/**
 * Builds a php-mcp Server with Pressmind travel tools and PSR-11 container bindings.
 */
class ServerFactory
{
    public const DEFAULT_NAME = 'Pressmind Travel MCP';

    public const DEFAULT_VERSION = '1.0.0';

    /**
     * @param  array<string, mixed>  $options  Optional keys:
     *   - name (string): server name
     *   - version (string): server version
     *   - instructions (string): server capability text
     *   - site_url (string): public base URL of the website (e.g. https://example.com); used to build absolute pretty URLs
     *   - ibe_url (string): base URL of the IBE3 booking engine (e.g. https://buchung.example.com); used to build absolute booking links
     *   - search (array): search config passed to SearchService constructor:
     *       language_code, touristic_origin, agency_id_price_index,
     *       group_keys, calendar_show_departures,
     *       destination_category_field, travel_type_category_field,
     *       category_fields (list of indexed field names),
     *       atlas (array: active, definition)
     */
    public static function create(array $options = []): Server
    {
        if (! class_exists(Server::class)) {
            throw new \RuntimeException(
                'php-mcp/server is not installed. Run: composer require php-mcp/server (or composer install with require-dev in the SDK repo).'
            );
        }

        $name = isset($options['name']) && is_string($options['name']) ? $options['name'] : self::DEFAULT_NAME;
        $version = isset($options['version']) && is_string($options['version']) ? $options['version'] : self::DEFAULT_VERSION;
        $instructions = isset($options['instructions']) && is_string($options['instructions']) ? $options['instructions'] : null;
        $searchConfig = isset($options['search']) && is_array($options['search']) ? $options['search'] : [];
        $siteUrl = isset($options['site_url']) && is_string($options['site_url']) ? $options['site_url'] : '';
        $ibeUrl = isset($options['ibe_url']) && is_string($options['ibe_url']) ? $options['ibe_url'] : '';

        $container = new BasicContainer();
        $container->set(SearchService::class, new SearchService($searchConfig, $siteUrl));
        $container->set(ProductService::class, new ProductService($siteUrl, $ibeUrl));
        $container->set(
            SearchTool::class,
            new SearchTool($container->get(SearchService::class))
        );
        $container->set(
            SemanticSearchTool::class,
            new SemanticSearchTool($container->get(SearchService::class))
        );
        $container->set(
            FetchTool::class,
            new FetchTool($container->get(ProductService::class))
        );
        $container->set(
            CategoriesTool::class,
            new CategoriesTool($container->get(SearchService::class))
        );
        $container->set(
            FilterOptionsTool::class,
            new FilterOptionsTool($container->get(SearchService::class))
        );
        $container->set(
            CalendarTool::class,
            new CalendarTool($container->get(ProductService::class))
        );
        $container->set(
            CheapestPricesTool::class,
            new CheapestPricesTool($container->get(ProductService::class))
        );
        $container->set(
            TouristicOptionsTool::class,
            new TouristicOptionsTool($container->get(ProductService::class))
        );
        $container->set(
            StartingPointsTool::class,
            new StartingPointsTool($container->get(ProductService::class))
        );

        $capabilities = Capabilities::forServer(
            toolsEnabled: true,
            resourcesEnabled: false,
            promptsEnabled: false,
            instructions: $instructions
        );

        return Server::make()
            ->withServerInfo($name, $version)
            ->withCapabilities($capabilities)
            ->withContainer($container)
            ->withTool([SearchTool::class, 'search'])
            ->withTool([SemanticSearchTool::class, 'semantic_search'])
            ->withTool([FetchTool::class, 'fetch'])
            ->withTool([CategoriesTool::class, 'get_categories'])
            ->withTool([FilterOptionsTool::class, 'get_filter_options'])
            ->withTool([CalendarTool::class, 'get_calendar'])
            ->withTool([CheapestPricesTool::class, 'get_cheapest_prices'])
            ->withTool([TouristicOptionsTool::class, 'get_touristic_options'])
            ->withTool([StartingPointsTool::class, 'get_starting_points'])
            ->build();
    }
}
