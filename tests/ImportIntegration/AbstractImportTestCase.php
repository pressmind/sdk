<?php

namespace Pressmind\Tests\ImportIntegration;

use Pressmind\CLI\InstallCommand;
use Pressmind\Config;
use Pressmind\Import;
use Pressmind\Registry;
use Pressmind\REST\ReplayClient;
use Pressmind\Tests\Integration\AbstractIntegrationTestCase;

/**
 * Base for ImportIntegration tests. Runs install + full import once across ALL subclasses
 * using ReplayClient (fixtures from tests/fixtures/api). Shared DB/Mongo state for all tests.
 */
abstract class AbstractImportTestCase extends AbstractIntegrationTestCase
{
    private static bool $_initialized = false;
    private static bool $_autoloaderRegistered = false;

    /** @var string Config file path written by install (shared) */
    protected static $configFilePath;

    /** @var string Application path used for install (Custom/MediaType, logs, etc.) */
    protected static $applicationPath;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        if (self::$_initialized) {
            return;
        }

        $baseDir = __DIR__;
        $sdkRoot = dirname($baseDir, 2);
        $appPath = $baseDir . '/_app';
        if (!is_dir($appPath)) {
            mkdir($appPath, 0775, true);
        }
        self::$applicationPath = $appPath;
        foreach (['Custom/MediaType', 'Custom/Views', 'ObjectTypeScaffolderTemplates', 'logs', 'tmp', 'docs/objecttypes', 'assets/files', 'assets/images'] as $subdir) {
            $dir = $appPath . '/' . $subdir;
            if (!is_dir($dir)) {
                mkdir($dir, 0775, true);
            }
        }
        $configOutput = $appPath . '/pm-config.php';
        if (is_file($configOutput)) {
            unlink($configOutput);
        }
        $configDefault = $sdkRoot . '/config.default.json';
        if (!is_file($configDefault)) {
            self::fail('config.default.json not found at ' . $configDefault);
        }

        if (!self::$_autoloaderRegistered) {
            spl_autoload_register(function ($class) use ($appPath) {
                if (str_starts_with($class, 'Custom\\')) {
                    $file = $appPath . DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, $class) . '.php';
                    if (is_file($file)) {
                        require_once $file;
                        return true;
                    }
                }
                return false;
            });
            self::$_autoloaderRegistered = true;
        }

        $ref = new \ReflectionClass(\Pressmind\DB\Config\Pdo::class);
        $prop = $ref->getProperty('instance');
        $prop->setAccessible(true);
        $prop->setValue(null, null);

        Registry::clear();
        $fixturePath = $baseDir . '/../Fixtures/api';
        $replay = new ReplayClient($fixturePath);
        Registry::getInstance()->add('rest_client', $replay);

        $argv = [
            'run.php',
            'install',
            '--config-default=' . $configDefault,
            '--config-output=' . $configOutput,
            '--db-host=' . (getenv('DB_HOST') ?: 'localhost'),
            '--db-name=' . (getenv('DB_NAME') ?: 'pressmind_test'),
            '--db-user=' . (getenv('DB_USER') ?: 'root'),
            '--db-password=' . (getenv('DB_PASS') ?: ''),
            '--db-port=' . (getenv('DB_PORT') ?: '3306'),
            '--mongodb-uri=' . (getenv('MONGODB_URI') ?: 'mongodb://localhost:27017'),
            '--mongodb-db=' . (getenv('MONGODB_DB') ?: 'pressmind_test'),
            '--api-key=test',
            '--api-user=test',
            '--api-password=test',
            '--application-path=' . $appPath,
            '--non-interactive',
            '--drop-tables',
        ];
        ob_start();
        $cmd = new InstallCommand();
        $exitCode = $cmd->run($argv);
        $installOutput = ob_get_clean();
        if ($exitCode !== 0) {
            self::fail("Install command failed (exit code $exitCode):\n$installOutput");
        }

        self::applyTestSearchConfig($configOutput);

        ob_start();
        $importStart = microtime(true);
        $importer = new Import('full');
        $importer->import();
        $importDuration = round(microtime(true) - $importStart, 1);
        $importOutput = ob_get_clean();

        $moCount = substr_count($importOutput, 'media object imported');
        $mongoCount = substr_count($importOutput, 'createMongoDBIndex');
        $heapMatch = [];
        preg_match('/Heap: ([\d.]+) MByte/', strrev(strstr(strrev($importOutput), 'Heap')), $heapMatch);
        $peakMem = round(memory_get_peak_usage(true) / 1024 / 1024, 1);

        fwrite(STDERR, "\n  [Setup] Install + Import completed in {$importDuration}s"
            . " | {$moCount} objects imported"
            . " | {$mongoCount} MongoDB indexes"
            . " | Peak memory: {$peakMem} MB\n\n");

        self::$configFilePath = $configOutput;
        self::$_initialized = true;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $config = $this->loadConfigFromFile();
        if ($config !== null) {
            Registry::getInstance()->add('config', $config);
            $db = $this->createDbFromConfig($config);
            if ($db !== null) {
                Registry::getInstance()->add('db', $db);
                $this->db = $db;
            }
            $fixturePath = __DIR__ . '/../Fixtures/api';
            Registry::getInstance()->add('rest_client', new ReplayClient($fixturePath));
        }
    }

    /**
     * Override MongoDB search config after InstallCommand.
     * InstallCommand correctly sets import-related config (media_types, visibilities, etc.)
     * but MongoDB search config uses placeholder type IDs from config.default.json.
     * This adjusts build_for/groups/descriptions to match actual imported types.
     */
    private static function applyTestSearchConfig(string $configPath): void
    {
        $config = Registry::getInstance()->get('config');

        $importedTypes = array_keys($config['data']['media_types'] ?? []);
        if (empty($importedTypes)) {
            return;
        }

        $buildFor = [];
        $groups = [];
        $allowInvalid = [];
        foreach ($importedTypes as $typeId) {
            $buildFor[(int)$typeId] = [['language' => 'de', 'origin' => 0, 'disable_language_prefix_in_url' => false]];
            $groups[(int)$typeId] = ['field' => 'agencies', 'filter' => null];
            $allowInvalid[] = (int)$typeId;
        }

        $config['data']['search_mongodb']['enabled'] = true;
        $config['data']['search_mongodb']['database']['uri'] = getenv('MONGODB_URI') ?: 'mongodb://localhost:27017';
        $config['data']['search_mongodb']['database']['db'] = getenv('MONGODB_DB') ?: 'pressmind_test';
        $config['data']['search_mongodb']['search']['build_for'] = $buildFor;
        $config['data']['search_mongodb']['search']['groups'] = $groups;
        $config['data']['search_mongodb']['search']['allow_invalid_offers'] = $allowInvalid;
        $config['data']['search_mongodb']['search']['descriptions'] = [];
        $config['data']['search_mongodb']['search']['categories'] = [];
        $config['data']['search_mongodb']['search']['custom_order'] = [];

        Registry::getInstance()->add('config', $config);

        $fullConfig = ['development' => $config];
        $export = "<?php\n\$config = " . var_export($fullConfig, true) . ";\n";
        file_put_contents($configPath, $export);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function loadConfigFromFile(): ?array
    {
        if (self::$configFilePath === null || !is_file(self::$configFilePath)) {
            return null;
        }
        $config = null;
        include self::$configFilePath;
        if (!isset($config) || !is_array($config)) {
            return null;
        }
        return $config['development'] ?? $config;
    }

    private function createDbFromConfig(array $config): ?object
    {
        $db = $config['database'] ?? [];
        $host = $db['host'] ?? '127.0.0.1';
        $port = $db['port'] ?? '3306';
        $name = $db['dbname'] ?? '';
        $user = $db['username'] ?? '';
        $pass = $db['password'] ?? '';
        if ($name === '') {
            return null;
        }
        try {
            $pdoConfig = \Pressmind\DB\Config\Pdo::create($host, $name, $user, $pass, $port);
            return new \Pressmind\DB\Adapter\Pdo($pdoConfig);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
