<?php

namespace Pressmind\Backend;

use Pressmind\CLI\AbstractCommand;
use Pressmind\CLI\DatabaseIntegrityCheckCommand;
use Pressmind\CLI\FileDownloaderCommand;
use Pressmind\CLI\FulltextIndexerCommand;
use Pressmind\CLI\ImportCommand;
use Pressmind\CLI\IndexMongoCommand;
use Pressmind\CLI\IndexOpenSearchCommand;
use Pressmind\CLI\LogCleanupCommand;
use Pressmind\CLI\RebuildCacheCommand;
use Pressmind\CLI\RebuildRoutesCommand;
use Pressmind\CLI\ResetCommand;
use Pressmind\CLI\TouristicOrphansCommand;

/**
 * Dispatches CLI argv to the correct SDK command class.
 * Used by a single bootstrap script (e.g. cli/run.php) so backend.cli_runner
 * can point to one script and all CommandRegistry commands work.
 *
 * Expects argv as from PHP CLI: $argv[0] = script path, $argv[1]+ = arguments
 * (same format as produced by CommandRegistry::buildArgv).
 */
class CLIRouter
{
    /** @var callable|null Optional callback for ImportCommand (e.g. Redis cache prime after import) */
    private static $importCallback = null;

    /**
     * Set a callback to run after import (e.g. theme-specific Redis cache update).
     * Call before run() from your bootstrap script if needed.
     *
     * @param callable $callback function(array $importedIds): void
     */
    public static function setImportCallback(callable $callback): void
    {
        self::$importCallback = $callback;
    }

    /**
     * Run the appropriate command based on argv. Returns exit code.
     *
     * @param array<int, string> $argv Full argv (script path + args)
     * @return int Exit code (0 = success)
     */
    public static function run(array $argv): int
    {
        $script = $argv[0] ?? '';
        $first = $argv[1] ?? null;
        $second = $argv[2] ?? null;

        if ($first === null || $first === '' || $first === '--help' || $first === '-h') {
            self::printUsage($script);
            return 0;
        }

        $command = self::resolveCommand($argv);
        if ($command === null) {
            self::printUsage($script);
            return 1;
        }

        try {
            return $command->run($argv);
        } catch (\Throwable $e) {
            return 1;
        }
    }

    /**
     * Resolve command instance and normalize argv for that command.
     * Some commands expect subcommand as first arg (e.g. ImportCommand expects 'fullimport' or 'mediaobject'),
     * so we strip the router prefix when needed.
     *
     * @param array<int, string> $argv
     * @return AbstractCommand|null
     */
    private static function resolveCommand(array &$argv): ?AbstractCommand
    {
        $first = $argv[1] ?? '';
        $second = $argv[2] ?? null;

        // Import: "fullimport" -> ImportCommand with [script, fullimport]; "import mediaobject" -> [script, mediaobject, ...]
        if ($first === 'fullimport') {
            return self::createImportCommand();
        }
        if ($first === 'import' && $second !== null && $second !== '') {
            $argv = array_merge([$argv[0]], array_slice($argv, 2));
            return self::createImportCommand();
        }

        // Index commands: strip "index-mongo" / "index-opensearch" so command sees subcommand as first arg
        if ($first === 'index-mongo') {
            $argv = array_merge([$argv[0]], array_slice($argv, 2));
            return new IndexMongoCommand();
        }
        if ($first === 'index-opensearch') {
            $argv = array_merge([$argv[0]], array_slice($argv, 2));
            return new IndexOpenSearchCommand();
        }

        // Commands that expect optional positional args after their name: strip name so getArgument(0) is the value
        if ($first === 'fulltext-indexer') {
            $argv = array_merge([$argv[0]], array_slice($argv, 2));
            return new FulltextIndexerCommand();
        }
        if ($first === 'touristic-orphans') {
            $argv = array_merge([$argv[0]], array_slice($argv, 2));
            return new TouristicOrphansCommand();
        }

        // Single-token commands: pass argv as-is (command may ignore positional args)
        switch ($first) {
            case 'rebuild-cache':
                return new RebuildCacheCommand();
            case 'rebuild-routes':
                return new RebuildRoutesCommand();
            case 'file-downloader':
                return new FileDownloaderCommand();
            case 'log-cleanup':
                return new LogCleanupCommand();
            case 'database-integrity-check':
                return new DatabaseIntegrityCheckCommand();
            case 'reset':
                return new ResetCommand();
        }

        return null;
    }

    private static function createImportCommand(): ImportCommand
    {
        $cmd = new ImportCommand();
        if (self::$importCallback !== null) {
            $cmd->setOnAfterImportCallback(self::$importCallback);
        }
        return $cmd;
    }

    private static function printUsage(string $script): void
    {
        $scriptName = basename($script);
        echo "Usage: php {$scriptName} <command> [args...]\n";
        echo "\nCommands (see Backend CommandRegistry):\n";
        echo "  fullimport, import <subcommand> [ids], rebuild-cache, rebuild-routes,\n";
        echo "  index-mongo <all|mediaobject|destroy>, index-opensearch <all|mediaobject|...>,\n";
        echo "  fulltext-indexer [ids], file-downloader, log-cleanup,\n";
        echo "  database-integrity-check, touristic-orphans [options], reset\n";
    }
}
