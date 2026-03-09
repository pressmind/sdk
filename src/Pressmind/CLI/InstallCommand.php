<?php

namespace Pressmind\CLI;

use Exception;
use Pressmind\Config;
use Pressmind\DB\Scaffolder\Mysql;
use Pressmind\HelperFunctions;
use Pressmind\Import;
use Pressmind\Log\Writer;
use Pressmind\Registry;
use Pressmind\REST\Client;
use Pressmind\System\Info;

/**
 * SDK core install command: config generation, directory creation, DB schema, ObjectType scaffolding.
 * Theme-specific config (TS_SEARCH_ROUTES, image derivatives, etc.) remains in the theme.
 *
 * Usage (non-interactive, e.g. tests):
 *   php run.php install --config-default=path/to/config.default.json --config-output=path/to/pm-config.php
 *     --db-host=127.0.0.1 --db-name=db --db-user=u --db-password=p
 *     --mongodb-uri=mongodb://localhost --mongodb-db=pressmind
 *     --api-key=... --api-user=... --api-password=...
 *     [--application-path=/path] [--drop-tables] [--only-static]
 *
 * When config-output already exists and Registry has config/db (e.g. theme bootstrap), only directories,
 * schema, and ObjectType import are run.
 */
class InstallCommand extends AbstractCommand
{
    private const NAMESPACE_ORM = 'Pressmind\\ORM\\Object';

    protected function execute(): int
    {
        $configDefault = $this->getOption('config-default');
        $configOutput = $this->getOption('config-output');
        $dropTables = $this->hasOption('drop-tables') || $this->getArgument(0) === 'drop_tables';
        $onlyStatic = $this->getArgument(0) === 'only_static';

        if ($configOutput === null || $configOutput === true) {
            $this->output->error('Missing --config-output path.');
            return 1;
        }
        $configOutput = trim((string) $configOutput);

        $firstInstall = !is_file($configOutput);

        if ($firstInstall) {
            if ($configDefault === null || $configDefault === true) {
                $this->output->error('Missing --config-default path (required for first install).');
                return 1;
            }
            $configDefault = trim((string) $configDefault);
            if (!is_file($configDefault)) {
                $this->output->error('Config default file does not exist: ' . $configDefault);
                return 1;
            }
            $config = $this->buildInitialConfig($configDefault);
            if ($config === null) {
                return 1;
            }
            $this->writeConfigFile($configOutput, $config);
            $this->output->writeln('Config written to ' . $configOutput, null);
        }

        $config = $this->loadConfigFile($configOutput);
        if ($config === null) {
            return 1;
        }

        Registry::getInstance()->add('config', $config);
        if (Registry::getInstance()->get('config_adapter') === null) {
            Registry::getInstance()->add('config_adapter', new Config('php', $configOutput, 'development'));
        }
        $this->ensureConstants();

        $db = $this->createDbConnection($config);
        if ($db === null) {
            $this->output->error('Database connection failed.');
            return 1;
        }
        Registry::getInstance()->add('db', $db);

        if (!$onlyStatic) {
            $dirsOk = $this->createDirectories($config);
            if (!$dirsOk) {
                $this->output->error('Creating required directories failed.');
                return 1;
            }
        }

        $this->createSchema($dropTables);

        if ($onlyStatic) {
            $this->output->writeln('Only static tables created (only_static).', null);
            return 0;
        }

        try {
            $this->fetchAndApplyObjectTypes($configOutput, $config);
        } catch (Exception $e) {
            Writer::write($e->getMessage(), Writer::OUTPUT_SCREEN, 'install', Writer::TYPE_ERROR);
            $this->output->error($e->getMessage());
            return 1;
        }

        return 0;
    }

    private function buildInitialConfig(string $configDefaultPath): ?array
    {
        $json = file_get_contents($configDefaultPath);
        if ($json === false) {
            $this->output->error('Could not read config default file.');
            return null;
        }
        $config = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->output->error('Invalid JSON in config default: ' . json_last_error_msg());
            return null;
        }

        $env = $config['development'] ?? [];
        $env['database'] = array_merge($env['database'] ?? [], [
            'host' => $this->getOption('db-host') ?? '127.0.0.1',
            'port' => $this->getOption('db-port') ?? '3306',
            'dbname' => $this->getOption('db-name') ?? '',
            'username' => $this->getOption('db-user') ?? '',
            'password' => $this->getOption('db-password') ?? '',
            'engine' => 'MySQL',
        ]);
        $env['rest']['client'] = array_merge($env['rest']['client'] ?? [], [
            'api_key' => $this->getOption('api-key') ?? '',
            'api_user' => $this->getOption('api-user') ?? '',
            'api_password' => $this->getOption('api-password') ?? '',
        ]);
        $env['data'] = $env['data'] ?? [];
        $env['data']['search_mongodb'] = $env['data']['search_mongodb'] ?? [];
        $env['data']['search_mongodb']['database'] = [
            'uri' => $this->getOption('mongodb-uri') ?? '',
            'db' => $this->getOption('mongodb-db') ?? '',
        ];
        $env['data']['search_mongodb']['enabled'] = true;
        $env['server'] = array_merge($env['server'] ?? [], [
            'webserver_http' => $this->getOption('webserver-http') ?? '',
            'php_cli_binary' => PHP_BINARY,
        ]);
        $config['development'] = $env;
        return $config;
    }

    private function writeConfigFile(string $path, array $config): void
    {
        $content = "<?php\n\$config = " . self::varExport($config) . ';';
        file_put_contents($path, $content);
    }

    /**
     * Export array to PHP code (short array syntax).
     */
    public static function varExport(array $expression): string
    {
        $export = var_export($expression, true);
        $export = preg_replace("/^([ ]*)(.*)/m", '$1$1$2', $export);
        $lines = preg_split("/\r\n|\n|\r/", $export);
        $lines = preg_replace(["/\s*array\s\($/", "/\)(,)?$/", "/\s=>\s$/"], [null, ']$1', ' => ['], $lines);
        return implode(PHP_EOL, array_filter(array_merge(['['], $lines)));
    }

    private function loadConfigFile(string $path): ?array
    {
        if (!is_file($path)) {
            return null;
        }
        $config = null;
        include $path;
        if (!isset($config) || !is_array($config)) {
            $this->output->error('Config file did not define $config array.');
            return null;
        }
        return $config['development'] ?? [];
    }

    private function ensureConstants(): void
    {
        $appPath = $this->getOption('application-path');
        if ($appPath !== null && $appPath !== true) {
            $appPath = rtrim(trim((string) $appPath), DIRECTORY_SEPARATOR);
        } else {
            $appPath = getcwd() ?: '';
        }
        if (!defined('APPLICATION_PATH')) {
            define('APPLICATION_PATH', $appPath);
        }
        if (!defined('BASE_PATH')) {
            define('BASE_PATH', dirname(APPLICATION_PATH));
        }
        if (!defined('WEBSERVER_DOCUMENT_ROOT')) {
            define('WEBSERVER_DOCUMENT_ROOT', $appPath);
        }
        if (!defined('WEBSERVER_HTTP')) {
            $config = Registry::getInstance()->get('config');
            define('WEBSERVER_HTTP', $config['server']['webserver_http'] ?? 'http://127.0.0.1');
        }
    }

    private function createDbConnection(array $config): ?object
    {
        $db = $config['database'] ?? [];
        $host = $db['host'] ?? '127.0.0.1';
        $port = $db['port'] ?? '3306';
        $name = $db['dbname'] ?? '';
        $user = $db['username'] ?? '';
        $pass = $db['password'] ?? '';
        if ($name === '') {
            $this->output->error('Database name is empty in config.');
            return null;
        }
        try {
            $pdoConfig = \Pressmind\DB\Config\Pdo::create($host, $name, $user, $pass, $port);
            return new \Pressmind\DB\Adapter\Pdo($pdoConfig);
        } catch (\Throwable $e) {
            $this->output->error('DB connect to ' . $user . '@' . $host . ':' . $port . '/' . $name . ' failed: ' . $e->getMessage());
            return null;
        }
    }

    private function createDirectories(array $config): bool
    {
        Writer::write('Creating required directories', Writer::OUTPUT_SCREEN, 'install', Writer::TYPE_INFO);
        $required = [
            HelperFunctions::buildPathString([APPLICATION_PATH, 'Custom', 'MediaType']),
            HelperFunctions::replaceConstantsFromConfig($config['logging']['log_file_path'] ?? 'APPLICATION_PATH/logs'),
            HelperFunctions::replaceConstantsFromConfig($config['tmp_dir'] ?? 'APPLICATION_PATH/tmp'),
            HelperFunctions::buildPathString([
                HelperFunctions::replaceConstantsFromConfig($config['docs_dir'] ?? 'APPLICATION_PATH/docs'),
                'objecttypes',
            ]),
        ];
        if (($config['file_handling']['storage']['provider'] ?? '') === 'filesystem') {
            $required[] = HelperFunctions::replaceConstantsFromConfig($config['file_handling']['storage']['bucket'] ?? '');
        }
        if (($config['image_handling']['storage']['provider'] ?? '') === 'filesystem') {
            $required[] = HelperFunctions::replaceConstantsFromConfig($config['image_handling']['storage']['bucket'] ?? '');
        }
        $ok = true;
        foreach ($required as $dir) {
            if ($dir === '') {
                continue;
            }
            if (!is_dir($dir)) {
                if (mkdir($dir, 0775, true)) {
                    Writer::write('Directory ' . $dir . ' created', Writer::OUTPUT_SCREEN, 'install', Writer::TYPE_INFO);
                } else {
                    Writer::write('Failed to create directory ' . $dir, Writer::OUTPUT_SCREEN, 'install', Writer::TYPE_ERROR);
                    $ok = false;
                }
            }
        }
        return $ok;
    }

    private function createSchema(bool $dropTables): void
    {
        foreach (Info::STATIC_MODELS as $model) {
            $modelName = self::NAMESPACE_ORM . $model;
            try {
                Writer::write('Creating database table for model: ' . $modelName, Writer::OUTPUT_SCREEN, 'install', Writer::TYPE_INFO);
                $scaffolder = new Mysql(new $modelName());
                $scaffolder->run($dropTables);
                foreach ($scaffolder->getLog() as $log) {
                    Writer::write($log, Writer::OUTPUT_SCREEN, 'install', Writer::TYPE_INFO);
                }
            } catch (Exception $e) {
                Writer::write($modelName, Writer::OUTPUT_SCREEN, 'install', Writer::TYPE_ERROR);
                Writer::write($e->getMessage(), Writer::OUTPUT_SCREEN, 'install', Writer::TYPE_ERROR);
            }
        }
    }

    private function fetchAndApplyObjectTypes(string $configOutputPath, array $config): void
    {
        Writer::write('Requesting and parsing information on media object types ...', Writer::OUTPUT_SCREEN, 'install', Writer::TYPE_INFO);
        $client = Registry::getInstance()->get('rest_client') ?? new Client();
        $response = $client->sendRequest('ObjectType', 'getAll');
        if (empty($response->result) || !is_array($response->result)) {
            throw new Exception('ObjectType getAll returned no result.');
        }

        $mediaTypes = [];
        $mediaTypesPrettyUrl = [];
        $mediaTypesAllowedVisibilities = [];
        $ids = [];
        $config['data']['primary_media_type_ids'] = $config['data']['primary_media_type_ids'] ?? [];

        foreach ($response->result as $item) {
            Writer::write('Parsing media object type ' . $item->type_name, Writer::OUTPUT_SCREEN, 'install', Writer::TYPE_INFO);
            $mediaTypes[$item->id_type] = ucfirst(HelperFunctions::human_to_machine($item->type_name));
            $ids[] = $item->id_type;
            $mediaTypesPrettyUrl[] = [
                'prefix' => '/' . HelperFunctions::human_to_machine($item->type_name) . '/',
                'fields' => ['name' => 'name'],
                'strategy' => 'count-up',
                'suffix' => '/',
                'language' => 'de',
                'id_object_type' => $item->id_type,
            ];
            $mediaTypesAllowedVisibilities[$item->id_type] = [30];
            if (in_array($item->gtxf_product_type ?? '', ['TOUR', 'DAYTRIP', 'HOLIDAYHOME'], true)) {
                $config['data']['primary_media_type_ids'][] = $item->id_type;
            }
        }

        $config['data']['primary_media_type_ids'] = array_unique($config['data']['primary_media_type_ids']);
        $primaryIds = $config['data']['primary_media_type_ids'];
        if (empty($primaryIds)) {
            $config['data']['media_types'] = $mediaTypes;
            $config['data']['media_types_pretty_url'] = $mediaTypesPrettyUrl;
            $config['data']['media_types_allowed_visibilities'] = $mediaTypesAllowedVisibilities;
            $this->writeConfigFile($configOutputPath, ['development' => $config]);
            Registry::getInstance()->add('config', $config);
            $importer = new Import('objecttypes');
            $importer->importMediaObjectTypes($ids);
            return;
        }

        $response2 = $client->sendRequest('ObjectType', 'getById', ['ids' => implode(',', $primaryIds)]);
        $result = $response2->result;
        if (!empty($result->error)) {
            throw new Exception('ObjectType getById error: ' . ($result->msg ?? 'unknown'));
        }
        $mongodbSearchCategories = [];
        $mongodbSearchDescriptions = [];
        $mongodbSearchBuildFor = [];
        $items = is_array($result) ? $result : [$result];
        foreach ($items as $item) {
            if (empty($item->gtxf_product_type)) {
                continue;
            }
            $mongodbSearchBuildFor[$item->id] = [['language' => null, 'origin' => 0]];
            $fields = $item->fields ?? [];
            foreach ($fields as $field) {
                if (empty($field->sections)) {
                    continue;
                }
                if (empty($mongodbSearchDescriptions[$item->id]['headline'])) {
                    $mongodbSearchDescriptions[$item->id]['headline'] = ['field' => 'name', 'from' => null, 'filter' => null];
                }
                foreach ($field->sections as $section) {
                    $fieldName = HelperFunctions::human_to_machine($field->var_name . '_' . $section->name);
                    if ($field->type === 'categorytree') {
                        $mongodbSearchCategories[$item->id][$fieldName] = null;
                    }
                    if (in_array($field->type, ['text', 'plaintext'], true) && preg_match('/subline/', $field->var_name) && empty($mongodbSearchDescriptions[$item->id]['subline'])) {
                        $mongodbSearchDescriptions[$item->id]['subline'] = ['field' => $fieldName, 'from' => null, 'filter' => '\\Custom\\Filter::strip'];
                    }
                    if (in_array($field->type, ['text', 'plaintext'], true) && preg_match('/intro|einleitung/', $field->var_name) && empty($mongodbSearchDescriptions[$item->id]['intro'])) {
                        $mongodbSearchDescriptions[$item->id]['intro'] = ['field' => $fieldName, 'from' => null, 'filter' => '\\Custom\\Filter::strip'];
                    }
                    if (in_array($field->type, ['picture'], true) && preg_match('/bilder|picture|image/', $field->var_name)) {
                        if (empty($mongodbSearchDescriptions[$item->id]['image'])) {
                            $mongodbSearchDescriptions[$item->id]['image'] = ['field' => $fieldName, 'from' => null, 'filter' => '\\Custom\\Filter::firstPicture', 'params' => ['derivative' => 'teaser', 'section' => 'base']];
                        }
                        if (empty($mongodbSearchDescriptions[$item->id]['bigslide'])) {
                            $mongodbSearchDescriptions[$item->id]['bigslide'] = ['field' => $fieldName, 'from' => null, 'filter' => '\\Custom\\Filter::firstPicture', 'params' => ['derivative' => 'bigslide', 'section' => 'panorama']];
                        }
                        if (empty($mongodbSearchDescriptions[$item->id]['image_square'])) {
                            $mongodbSearchDescriptions[$item->id]['image_square'] = ['field' => $fieldName, 'from' => null, 'filter' => '\\Custom\\Filter::firstPicture', 'params' => ['derivative' => 'square', 'section' => 'quadrate']];
                        }
                    }
                    if (in_array($field->type, ['categorytree'], true) && preg_match('/^zielgebiet|^destination/', $field->var_name) && empty($mongodbSearchDescriptions[$item->id]['destination'])) {
                        $mongodbSearchDescriptions[$item->id]['destination'] = ['field' => $fieldName, 'from' => null, 'filter' => '\\Custom\\Filter::lastTreeItemAsString'];
                    }
                    if (in_array($field->type, ['categorytree'], true) && preg_match('/^reiseart/', $field->var_name) && empty($mongodbSearchDescriptions[$item->id]['travel_type'])) {
                        $mongodbSearchDescriptions[$item->id]['travel_type'] = ['field' => $fieldName, 'from' => null, 'filter' => '\\Custom\\Filter::lastTreeItemAsString'];
                    }
                }
            }
        }

        $config['data']['search_mongodb']['search'] = $config['data']['search_mongodb']['search'] ?? [];
        $config['data']['search_mongodb']['search']['descriptions'] = $mongodbSearchDescriptions;
        $config['data']['search_mongodb']['search']['categories'] = $mongodbSearchCategories;
        $config['data']['search_mongodb']['search']['build_for'] = $mongodbSearchBuildFor;
        $config['data']['media_types'] = $mediaTypes;
        $config['data']['media_types_pretty_url'] = $mediaTypesPrettyUrl;
        $config['data']['media_types_allowed_visibilities'] = $mediaTypesAllowedVisibilities;

        $fullConfig = ['development' => $config];
        $this->writeConfigFile($configOutputPath, $fullConfig);
        Registry::getInstance()->add('config', $config);
        Writer::write('pm-config.php updated', Writer::OUTPUT_SCREEN, 'install', Writer::TYPE_INFO);

        $importer = new Import('objecttypes');
        $importer->importMediaObjectTypes($ids);
    }
}
