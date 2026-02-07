<?php

namespace Pressmind\CLI;

use Exception;
use Pressmind\DB\Scaffolder\Mysql as ScaffolderMysql;
use Pressmind\Log\Writer as LogWriter;
use Pressmind\ObjectIntegrityCheck;
use Pressmind\ObjectTypeScaffolder;
use Pressmind\ORM\Object\AbstractObject;
use Pressmind\Registry;
use Pressmind\REST\Client;
use Pressmind\System\Info;

/**
 * CLI command for database schema integrity check.
 * Compares ORM definitions with database tables and optionally applies fixes.
 *
 * Usage:
 *   php bin/database-integrity-check [--non-interactive] [-n]
 *
 * Options:
 *   --non-interactive  No interactive prompts, output only
 *   -n                 Short form for --non-interactive
 *
 * Requires bootstrap to be loaded (e.g. via travelshop cli/integrity_check.php wrapper).
 *
 * @see \Pressmind\DB\IntegrityCheck\Mysql
 */
class DatabaseIntegrityCheckCommand extends AbstractCommand
{
    /** Fragmentation threshold: suggest OPTIMIZE when data_free exceeds this (bytes). */
    private const FRAGMENTATION_THRESHOLD_BYTES = 10 * 1024 * 1024;

    /** Log table: suggest cleanup when row count exceeds this. */
    private const LOG_TABLE_MAX_ROWS = 100000;

    /** Log table: suggest cleanup when size (data_length + data_free) exceeds this (bytes). */
    private const LOG_TABLE_MAX_BYTES = 100 * 1024 * 1024;

    protected function execute(): int
    {
        $this->output->newLine();
        $this->output->writeln('=== Database Schema Integrity Check ===', 'cyan');
        $this->output->newLine();

        $hasErrors = false;

        $hasErrors = $this->checkStaticModels() || $hasErrors;
        $hasErrors = $this->checkCustomMediaTypes() || $hasErrors;
        $this->checkFragmentation();
        $this->checkLogTableSize();

        $this->output->newLine();

        if ($hasErrors) {
            $this->output->warning('Some tables had integrity violations. Review output above.');
            return 1;
        }

        $this->output->success('All database tables are up to date.');
        return 0;
    }

    /** Max re-check iterations per table so one script run can fix all differences. */
    private const MAX_APPLY_ITERATIONS = 5;

    /**
     * Check static ORM models against database schema.
     * After applying, re-checks and applies again until no differences or max iterations.
     */
    private function checkStaticModels(): bool
    {
        $this->output->writeln('Checking static models for integrity.', 'cyan');

        $hasErrors = false;

        foreach (Info::STATIC_MODELS as $model_name) {
            $model_name = '\Pressmind\ORM\Object' . $model_name;
            /** @var AbstractObject $object */
            $object = new $model_name();
            $iteration = 0;
            $userWantsApply = false;
            $prompted = false;

            do {
                $check = $object->checkStorageIntegrity();

                if (is_array($check)) {
                    $hasErrors = true;
                    $this->output->writeln('!!!!!!!!!!!', 'red');
                    $this->output->writeln('Integrity violation for database table ' . $object->getDbTableName(), 'red');
                    foreach ($check as $difference) {
                        $this->output->writeln('  ' . $difference['msg']);
                    }
                    $this->output->newLine();

                    if (!$prompted) {
                        $prompted = true;
                        $userWantsApply = !$this->isNonInteractive() && $this->output->prompt('Apply changes?', false);
                    }
                    if (!$userWantsApply) {
                        break;
                    }
                    $this->applyStaticModelDifferences($object, $model_name, $check);
                    $iteration++;
                } else {
                    if ($iteration > 0) {
                        $this->output->success($model_name . ' is now up to date (after ' . $iteration . ' apply round(s)).');
                    } else {
                        $this->output->writeln($model_name . ' is up to date.');
                    }
                    break;
                }
            } while ($iteration < self::MAX_APPLY_ITERATIONS);

            if (is_array($check) && $iteration >= self::MAX_APPLY_ITERATIONS) {
                $this->output->warning('Max apply iterations reached for ' . $model_name . '. Re-run script if needed.');
            }
        }

        return $hasErrors;
    }

    /**
     * Apply detected differences for a static model (same order as travelshop).
     */
    private function applyStaticModelDifferences(AbstractObject $object, string $model_name, array $check): void
    {
        $db = Registry::getInstance()->get('db');
        $tableName = $object->getDbTableName();

        foreach ($check as $difference) {
            switch ($difference['action']) {
                case 'create_table':
                    $scaffolder = new ScaffolderMysql(new $model_name());
                    $scaffolder->run(true);
                    $this->output->writeln('  Executed: create_table');
                    break;
                case 'alter_column_null':
                    $this->executeSql($db, 'ALTER TABLE ' . $tableName . ' MODIFY `' . $difference['column_name'] . '` ' . $difference['column_type'] . ' ' . $difference['column_null']);
                    break;
                case 'alter_column_type':
                    $this->executeSql($db, 'ALTER TABLE ' . $tableName . ' MODIFY `' . $difference['column_name'] . '` ' . $difference['column_type'] . ' ' . ($difference['column_null'] ?? 'NULL'));
                    break;
                case 'create_column':
                    $this->executeSql($db, 'ALTER TABLE ' . $tableName . ' ADD `' . $difference['column_name'] . '` ' . $difference['column_type'] . ' ' . ($difference['column_null'] ?? 'NULL'));
                    break;
            }
        }

        foreach ($check as $difference) {
            switch ($difference['action']) {
                case 'remove_auto_increment':
                    $this->executeSql($db, 'ALTER TABLE ' . $tableName . ' MODIFY `' . $difference['column_name'] . '` ' . $difference['column_type'] . ' ' . $difference['column_null']);
                    break;
                case 'set_auto_increment':
                    $this->executeSql($db, 'ALTER TABLE ' . $tableName . ' MODIFY `' . $difference['column_name'] . '` ' . $difference['column_type'] . ' ' . $difference['column_null'] . ' auto_increment');
                    break;
                case 'add_index':
                    $indexType = $difference['index_type'] ?? 'index';
                    if (strtolower($indexType) === 'fulltext') {
                        $this->executeSql($db, 'CREATE FULLTEXT INDEX ' . $difference['index_name'] . ' ON ' . $tableName . ' (`' . implode('`,`', $difference['column_names']) . '`)');
                    } else {
                        $this->executeSql($db, 'CREATE INDEX ' . $difference['index_name'] . ' ON ' . $tableName . ' (`' . implode('`,`', $difference['column_names']) . '`)');
                    }
                    break;
                case 'alter_primary_key':
                    $this->executeSql($db, 'ALTER TABLE ' . $tableName . ' MODIFY ' . $difference['old_column_names'] . ' varchar(255) NOT NULL');
                    $this->executeSql($db, 'ALTER TABLE ' . $tableName . ' DROP PRIMARY KEY');
                    $this->executeSql($db, 'ALTER TABLE ' . $tableName . ' ADD PRIMARY KEY (' . $difference['column_names'] . ')');
                    break;
                case 'alter_engine':
                    $this->executeSql($db, 'ALTER TABLE ' . $tableName . ' ENGINE=' . $difference['engine']);
                    break;
            }
        }

        foreach ($check as $difference) {
            if ($difference['action'] === 'drop_column') {
                $this->executeSql($db, 'ALTER TABLE ' . $tableName . ' DROP `' . $difference['column_name'] . '`');
            }
        }
    }

    /**
     * Check custom media type tables against API definitions.
     */
    private function checkCustomMediaTypes(): bool
    {
        $this->output->newLine();
        $this->output->writeln('Checking custom media objects for integrity.', 'cyan');

        $config = Registry::getInstance()->get('config');
        if (empty($config['data']['media_types']) || !is_array($config['data']['media_types'])) {
            $this->output->info('No media types configured. Skipping.');
            return false;
        }

        $media_type_ids = array_keys($config['data']['media_types']);
        $hasErrors = false;

        try {
            $rest_client = new Client();
            $response = $rest_client->sendRequest('ObjectType', 'getById', ['ids' => implode(',', $media_type_ids)]);

            if (empty($response->result)) {
                return false;
            }

            foreach ($response->result as $media_type_definition) {
                $tableName = 'objectdata_' . $media_type_definition->id;
                $this->output->writeln('Checking table ' . $tableName . '.');

                $iteration = 0;
                $userWantsApply = false;
                $prompted = false;

                do {
                    $integrityCheck = new ObjectIntegrityCheck($media_type_definition, $tableName);
                    $differences = $integrityCheck->getDifferences();

                    if (count($differences) > 0) {
                        $hasErrors = true;
                        $this->output->writeln('!!!!!!!!!!!', 'red');
                        $this->output->writeln('Integrity violation for database table ' . $tableName, 'red');
                        foreach ($differences as $difference) {
                            $this->output->writeln('  ' . $difference['msg']);
                        }
                        $this->output->newLine();

                        if (!$prompted) {
                            $prompted = true;
                            $userWantsApply = !$this->isNonInteractive() && $this->output->prompt('Apply changes?', false);
                        }
                        if (!$userWantsApply) {
                            break;
                        }
                        $this->applyCustomMediaTypeDifferences($tableName, $differences);
                        $iteration++;
                    } else {
                        if ($iteration > 0) {
                            $this->output->success('  Table ' . $tableName . ' is now up to date (after ' . $iteration . ' apply round(s)).');
                        } else {
                            $this->output->writeln('  Table ' . $tableName . ' is up to date.');
                        }
                        break;
                    }
                } while ($iteration < self::MAX_APPLY_ITERATIONS);

                if (count($differences) > 0 && $iteration >= self::MAX_APPLY_ITERATIONS) {
                    $this->output->warning('Max apply iterations reached for ' . $tableName . '. Re-run script if needed.');
                }

                if ($iteration > 0 && !$this->isNonInteractive() && $this->output->prompt('Apply changes to PHP file?', false)) {
                    $scaffolder = new ObjectTypeScaffolder($media_type_definition, $media_type_definition->id);
                    $scaffolder->parse();
                    $this->output->success('PHP file updated.');
                }
            }
        } catch (Exception $e) {
            $this->output->error($e->getMessage());
            return true;
        }

        return $hasErrors;
    }

    /**
     * Apply detected differences for a custom media type table.
     */
    private function applyCustomMediaTypeDifferences(string $tableName, array $differences): void
    {
        $db = Registry::getInstance()->get('db');

        foreach ($differences as $difference) {
            switch ($difference['action']) {
                case 'alter_column_type':
                    $this->executeSql($db, 'ALTER TABLE ' . $tableName . ' MODIFY `' . $difference['column_name'] . '` ' . $difference['column_type']);
                    break;
                case 'create_column':
                    $this->executeSql($db, 'ALTER TABLE ' . $tableName . ' ADD `' . $difference['column_name'] . '` ' . $difference['column_type'] . ' NULL');
                    break;
                case 'drop_column':
                    $this->executeSql($db, 'ALTER TABLE ' . $tableName . ' DROP `' . $difference['column_name'] . '`');
                    break;
            }
        }
    }

    /**
     * Check for fragmented tables (data_free above threshold) and optionally run OPTIMIZE TABLE.
     */
    private function checkFragmentation(): void
    {
        $this->output->newLine();
        $this->output->writeln('Checking table fragmentation.', 'cyan');

        $db = Registry::getInstance()->get('db');
        $schemaRow = $db->fetchAll('SELECT DATABASE() as db');
        $schema = $schemaRow[0]->db ?? null;
        if (!$schema) {
            $this->output->warning('Could not determine current database. Skipping fragmentation check.');
            return;
        }

        $threshold = self::FRAGMENTATION_THRESHOLD_BYTES;
        $rows = $db->fetchAll(
            "SELECT table_name, data_free FROM information_schema.tables WHERE table_schema = ? AND data_free > ?",
            [$schema, $threshold]
        );

        if (empty($rows)) {
            $this->output->writeln('No fragmented tables (data_free > ' . round($threshold / 1024 / 1024) . ' MB).');
            return;
        }

        $this->output->writeln('Fragmented tables (data_free > ' . round($threshold / 1024 / 1024) . ' MB):');
        foreach ($rows as $row) {
            $freeMb = round((int) $row->data_free / 1024 / 1024, 1);
            $this->output->writeln('  ' . $row->table_name . ' (data_free: ' . $freeMb . ' MB)');
        }
        $this->output->newLine();

        if ($this->isNonInteractive()) {
            $this->output->info('Run with interactive mode to apply OPTIMIZE TABLE.');
            return;
        }

        if (!$this->output->prompt('Run OPTIMIZE TABLE on these tables?', false)) {
            return;
        }

        foreach ($rows as $row) {
            $fullName = '`' . $schema . '`.`' . $row->table_name . '`';
            $this->executeSql($db, 'OPTIMIZE TABLE ' . $fullName);
        }
        $this->output->success('Fragmentation optimization done.');
    }

    /**
     * Check log table size; if over limits, optionally run Writer::cleanup() and OPTIMIZE TABLE.
     */
    private function checkLogTableSize(): void
    {
        $this->output->newLine();
        $this->output->writeln('Checking log table size.', 'cyan');

        $db = Registry::getInstance()->get('db');
        $schemaRow = $db->fetchAll('SELECT DATABASE() as db');
        $schema = $schemaRow[0]->db ?? null;
        if (!$schema) {
            $this->output->warning('Could not determine current database. Skipping log table check.');
            return;
        }

        $logTableName = $db->getTablePrefix() . 'pmt2core_logs';
        $row = $db->fetchRow(
            "SELECT table_rows, data_length, data_free FROM information_schema.tables WHERE table_schema = ? AND table_name = ?",
            [$schema, $logTableName]
        );

        if (!$row || ($row->table_rows == 0 && (int) $row->data_length === 0 && (int) $row->data_free === 0)) {
            $this->output->writeln('Log table not found or empty. Skipping.');
            return;
        }

        $rows = (int) $row->table_rows;
        $size = (int) $row->data_length + (int) $row->data_free;
        $overRows = $rows > self::LOG_TABLE_MAX_ROWS;
        $overSize = $size > self::LOG_TABLE_MAX_BYTES;

        if (!$overRows && !$overSize) {
            $this->output->writeln('Log table size OK (rows: ' . $rows . ', size: ' . round($size / 1024 / 1024, 1) . ' MB).');
            return;
        }

        $this->output->writeln('Log table ' . $logTableName . ' is large: rows=' . $rows . ', size=' . round($size / 1024 / 1024, 1) . ' MB.');
        if ($overRows) {
            $this->output->writeln('  (max rows threshold: ' . self::LOG_TABLE_MAX_ROWS . ')');
        }
        if ($overSize) {
            $this->output->writeln('  (max size threshold: ' . round(self::LOG_TABLE_MAX_BYTES / 1024 / 1024) . ' MB)');
        }
        $this->output->newLine();

        if ($this->isNonInteractive()) {
            $this->output->info('Run with interactive mode to run log cleanup and OPTIMIZE TABLE.');
            return;
        }

        if (!$this->output->prompt('Run log cleanup (delete entries older than 5 days) and OPTIMIZE TABLE?', false)) {
            return;
        }

        $deleted = LogWriter::cleanup();
        $this->output->writeln('  Deleted ' . $deleted . ' log row(s).');
        $fullName = '`' . $schema . '`.`' . $logTableName . '`';
        $this->executeSql($db, 'OPTIMIZE TABLE ' . $fullName);
        $this->output->success('Log table cleanup and optimization done.');
    }

    private function executeSql($db, string $sql): void
    {
        $this->output->writeln('  ' . $sql);
        $db->execute($sql);
    }
}
