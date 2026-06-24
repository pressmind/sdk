<?php

declare(strict_types=1);

namespace Pressmind\MCP\Discovery;

use Pressmind\MCP\Tool\CalendarTool;
use Pressmind\MCP\Tool\CategoriesTool;
use Pressmind\MCP\Tool\CheapestPricesTool;
use Pressmind\MCP\Tool\FetchTool;
use Pressmind\MCP\Tool\FilterOptionsTool;
use Pressmind\MCP\Tool\SearchTool;
use Pressmind\MCP\Tool\SemanticSearchTool;
use Pressmind\MCP\Tool\StartingPointsTool;
use Pressmind\MCP\Tool\TouristicOptionsTool;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;

class ToolMetadataProvider
{
    private const MCP_TOOL_ATTRIBUTE = 'PhpMcp\\Server\\Attributes\\McpTool';

    /**
     * @var array<int, class-string>
     */
    private array $toolClasses;

    /**
     * @param array<int, class-string>|null $toolClasses
     */
    public function __construct(?array $toolClasses = null)
    {
        $this->toolClasses = $toolClasses ?? [
            SearchTool::class,
            SemanticSearchTool::class,
            FetchTool::class,
            CategoriesTool::class,
            FilterOptionsTool::class,
            CalendarTool::class,
            CheapestPricesTool::class,
            TouristicOptionsTool::class,
            StartingPointsTool::class,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getTools(): array
    {
        $tools = [];

        foreach ($this->toolClasses as $toolClass) {
            $reflection = new ReflectionClass($toolClass);
            foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                $attribute = $this->findMcpToolAttribute($method);
                if ($attribute === null) {
                    continue;
                }

                $tools[] = [
                    'name' => (string) ($attribute['name'] ?? $method->getName()),
                    'description' => (string) ($attribute['description'] ?? ''),
                    'inputSchema' => $this->buildInputSchema($method),
                ];
            }
        }

        return $tools;
    }

    /**
     * @return array<string, string>|null
     */
    private function findMcpToolAttribute(ReflectionMethod $method): ?array
    {
        foreach ($method->getAttributes() as $attribute) {
            if ($attribute->getName() !== self::MCP_TOOL_ATTRIBUTE) {
                continue;
            }

            $arguments = $attribute->getArguments();
            if (isset($arguments[0]) && !isset($arguments['name'])) {
                $arguments['name'] = $arguments[0];
            }
            if (isset($arguments[1]) && !isset($arguments['description'])) {
                $arguments['description'] = $arguments[1];
            }

            return $arguments;
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildInputSchema(ReflectionMethod $method): array
    {
        $properties = [];
        $required = [];
        $paramDescriptions = $this->extractParamDescriptions((string) $method->getDocComment());

        foreach ($method->getParameters() as $parameter) {
            $name = $parameter->getName();
            $properties[$name] = [
                'type' => $this->mapJsonType($parameter),
            ];

            if (isset($paramDescriptions[$name])) {
                $properties[$name]['description'] = $paramDescriptions[$name];
            }

            if ($parameter->isDefaultValueAvailable()) {
                $default = $parameter->getDefaultValue();
                if ($default !== null) {
                    $properties[$name]['default'] = $default;
                }
            }

            if (!$parameter->allowsNull() && !$parameter->isDefaultValueAvailable()) {
                $required[] = $name;
            }
        }

        $schema = [
            'type' => 'object',
            'properties' => $properties,
        ];

        if ($required !== []) {
            $schema['required'] = $required;
        }

        return $schema;
    }

    private function mapJsonType(ReflectionParameter $parameter): string
    {
        $type = $parameter->getType();
        if (!$type instanceof ReflectionNamedType) {
            return 'string';
        }

        return match ($type->getName()) {
            'int' => 'integer',
            'float' => 'number',
            'bool' => 'boolean',
            default => 'string',
        };
    }

    /**
     * @return array<string, string>
     */
    private function extractParamDescriptions(string $docComment): array
    {
        $descriptions = [];
        if ($docComment === '') {
            return $descriptions;
        }

        if (preg_match_all('/@param\s+[^\s]+\s+\$([a-zA-Z0-9_]+)\s*(.*)$/m', $docComment, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $description = trim($match[2]);
                if ($description !== '') {
                    $descriptions[$match[1]] = $description;
                }
            }
        }

        return $descriptions;
    }
}
