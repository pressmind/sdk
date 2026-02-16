<?php

namespace Pressmind\Backend;

/**
 * Central registry of CLI commands with metadata for the Backend UI.
 * Commands are executed via Process\StreamExecutor (proc_open); this class only holds definitions.
 */
class CommandRegistry
{
    public const DANGER_LOW = 'low';
    public const DANGER_MEDIUM = 'medium';
    public const DANGER_HIGH = 'high';
    public const DANGER_CRITICAL = 'critical';

    /**
     * All registered commands. Key = display name / CLI identifier.
     *
     * @var array<string, array{name: string, description: string, arguments: array, danger: string}>
     */
    private static $commands = [
        'fullimport' => [
            'name' => 'fullimport',
            'description' => 'Vollimport aller Media Objects',
            'arguments' => [],
            'danger' => self::DANGER_LOW,
        ],
        'import mediaobject' => [
            'name' => 'import mediaobject',
            'description' => 'Einzelne MOs importieren',
            'arguments' => [['name' => 'ids', 'label' => 'IDs (kommasepariert)', 'required' => false]],
            'danger' => self::DANGER_LOW,
        ],
        'import touristic' => [
            'name' => 'import touristic',
            'description' => 'Touristic-Daten importieren',
            'arguments' => [['name' => 'ids', 'label' => 'IDs (kommasepariert)', 'required' => false]],
            'danger' => self::DANGER_LOW,
        ],
        'import depublish' => [
            'name' => 'import depublish',
            'description' => 'MOs depublizieren',
            'arguments' => [['name' => 'ids', 'label' => 'IDs', 'required' => true]],
            'danger' => self::DANGER_MEDIUM,
        ],
        'import destroy' => [
            'name' => 'import destroy',
            'description' => 'MOs loeschen',
            'arguments' => [['name' => 'ids', 'label' => 'IDs', 'required' => true]],
            'danger' => self::DANGER_HIGH,
        ],
        'import remove_orphans' => [
            'name' => 'import remove_orphans',
            'description' => 'Orphan-Records entfernen',
            'arguments' => [],
            'danger' => self::DANGER_MEDIUM,
        ],
        'import offer' => [
            'name' => 'import offer',
            'description' => 'Cheapest Price neu berechnen',
            'arguments' => [['name' => 'ids', 'label' => 'IDs', 'required' => false]],
            'danger' => self::DANGER_LOW,
        ],
        'import calendar' => [
            'name' => 'import calendar',
            'description' => 'Kalender neu berechnen',
            'arguments' => [['name' => 'ids', 'label' => 'IDs', 'required' => false]],
            'danger' => self::DANGER_LOW,
        ],
        'import powerfilter' => [
            'name' => 'import powerfilter',
            'description' => 'Powerfilter importieren',
            'arguments' => [],
            'danger' => self::DANGER_LOW,
        ],
        'import categories' => [
            'name' => 'import categories',
            'description' => 'Kategorie-Baeume importieren',
            'arguments' => [['name' => 'ids', 'label' => 'IDs', 'required' => false]],
            'danger' => self::DANGER_LOW,
        ],
        'import reset_insurances' => [
            'name' => 'import reset_insurances',
            'description' => 'Insurance-Tabellen leeren',
            'arguments' => [],
            'danger' => self::DANGER_HIGH,
        ],
        'import unlock' => [
            'name' => 'import unlock',
            'description' => 'Import-Lock entfernen',
            'arguments' => [],
            'danger' => self::DANGER_LOW,
        ],
        'rebuild-cache' => [
            'name' => 'rebuild-cache',
            'description' => 'Object-Cache neu aufbauen',
            'arguments' => [],
            'danger' => self::DANGER_LOW,
        ],
        'rebuild-routes' => [
            'name' => 'rebuild-routes',
            'description' => 'URLs/Routes neu aufbauen',
            'arguments' => [],
            'danger' => self::DANGER_LOW,
        ],
        'index-mongo all' => [
            'name' => 'index-mongo all',
            'description' => 'MongoDB komplett neu indexieren',
            'arguments' => [],
            'danger' => self::DANGER_MEDIUM,
        ],
        'index-mongo mediaobject' => [
            'name' => 'index-mongo mediaobject',
            'description' => 'Einzelne MOs indexieren',
            'arguments' => [['name' => 'ids', 'label' => 'IDs', 'required' => false]],
            'danger' => self::DANGER_LOW,
        ],
        'index-mongo destroy' => [
            'name' => 'index-mongo destroy',
            'description' => 'MongoDB-Index loeschen',
            'arguments' => [],
            'danger' => self::DANGER_HIGH,
        ],
        'index-opensearch all' => [
            'name' => 'index-opensearch all',
            'description' => 'OpenSearch komplett indexieren',
            'arguments' => [],
            'danger' => self::DANGER_MEDIUM,
        ],
        'index-opensearch mediaobject' => [
            'name' => 'index-opensearch mediaobject',
            'description' => 'Einzelne MOs indexieren',
            'arguments' => [['name' => 'ids', 'label' => 'IDs', 'required' => false]],
            'danger' => self::DANGER_LOW,
        ],
        'fulltext-indexer' => [
            'name' => 'fulltext-indexer',
            'description' => 'MySQL-Fulltext-Index',
            'arguments' => [['name' => 'ids', 'label' => 'IDs (optional)', 'required' => false]],
            'danger' => self::DANGER_LOW,
        ],
        'file-downloader' => [
            'name' => 'file-downloader',
            'description' => 'Dateien herunterladen',
            'arguments' => [],
            'danger' => self::DANGER_LOW,
        ],
        'log-cleanup' => [
            'name' => 'log-cleanup',
            'description' => 'Log-Bereinigung',
            'arguments' => [],
            'danger' => self::DANGER_LOW,
        ],
        'database-integrity-check' => [
            'name' => 'database-integrity-check',
            'description' => 'DB-Schema pruefen',
            'arguments' => [],
            'danger' => self::DANGER_LOW,
        ],
        'touristic-orphans' => [
            'name' => 'touristic-orphans',
            'description' => 'Verwaiste Produkte finden',
            'arguments' => [['name' => '--stats-only', 'label' => 'Nur Statistik', 'required' => false]],
            'danger' => self::DANGER_LOW,
        ],
        'reset' => [
            'name' => 'reset',
            'description' => 'System komplett zuruecksetzen',
            'arguments' => [],
            'danger' => self::DANGER_CRITICAL,
        ],
    ];

    /**
     * Get all commands (for list UI).
     *
     * @return array<string, array{name: string, description: string, arguments: array, danger: string}>
     */
    public static function getAll(): array
    {
        return self::$commands;
    }

    /**
     * Get one command by name.
     *
     * @return array{name: string, description: string, arguments: array, danger: string}|null
     */
    public static function get(string $name): ?array
    {
        return self::$commands[$name] ?? null;
    }

    /**
     * Check if a command exists.
     */
    public static function has(string $name): bool
    {
        return isset(self::$commands[$name]);
    }

    /**
     * Build CLI argv for a command (for proc_open). Script path and php binary are not included.
     * Example: ['fullimport'] or ['mediaobject', '123,456'] for import mediaobject with ids.
     *
     * @param string $name Command name from registry
     * @param array<string, string> $args Key-value args (e.g. ['ids' => '123,456'])
     * @return array<int, string> argv parts (without script name and php binary)
     */
    public static function buildArgv(string $name, array $args = []): array
    {
        $def = self::get($name);
        if ($def === null) {
            return [];
        }
        $parts = explode(' ', $def['name']);
        foreach ($def['arguments'] as $argDef) {
            $key = $argDef['name'];
            $value = $args[$key] ?? null;
            if ($value !== null && $value !== '') {
                if (strpos($key, '--') === 0) {
                    $parts[] = $key;
                    if ($value !== '1' && $value !== 'true') {
                        $parts[] = $value;
                    }
                } else {
                    $parts[] = $value;
                }
            }
        }
        return $parts;
    }
}
