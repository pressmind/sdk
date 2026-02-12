<?php

namespace Pressmind\CLI;

use Exception;
use Pressmind\Registry;
use Pressmind\Search\MongoDB\Indexer;

/**
 * Reset Command
 *
 * Drops all tables in the database and flushes MongoDB collections.
 * Requires interactive confirmation (or --non-interactive with --confirm).
 *
 * Usage:
 *   php cli/reset.php
 *   php bin/reset
 *   php bin/reset --non-interactive --confirm
 */
class ResetCommand extends AbstractCommand
{
    private const DROP_TABLES_SQL = "SET FOREIGN_KEY_CHECKS = 0;
SET GROUP_CONCAT_MAX_LEN=32768;
SET @tables = NULL;
SELECT GROUP_CONCAT('`', table_name, '`') INTO @tables
  FROM information_schema.tables
  WHERE table_schema = (SELECT DATABASE());
SELECT IFNULL(@tables,'dummy') INTO @tables;
SET @tables = CONCAT('DROP TABLE IF EXISTS ', @tables);
PREPARE stmt FROM @tables;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
SET FOREIGN_KEY_CHECKS = 1;";

    protected function execute(): int
    {
        $config = Registry::getInstance()->get('config');
        $dbName = $config['database']['dbname'] ?? '';
        $mongoDbName = $config['data']['search_mongodb']['database']['db'] ?? '';

        if ($this->isNonInteractive()) {
            if (!$this->hasOption('confirm')) {
                $this->output->error('Use --confirm to run reset without interactive prompt.');
                return 1;
            }
        } else {
            $question = "Type 'yes' to drop all tables in database '" . $dbName . "' and flush MongoDB '" . $mongoDbName . "'";
            if (!$this->output->prompt($question, false)) {
                $this->output->writeln('aborted by user', null);
                return 0;
            }
        }

        /** @var \Pressmind\DB\Adapter\Pdo $db */
        $db = Registry::getInstance()->get('db');

        $this->output->writeln('Dropping all tables in database: ' . $dbName, null);

        try {
            $db->execute(self::DROP_TABLES_SQL);
        } catch (Exception $e) {
            $this->output->error($e->getMessage());
            return 1;
        }

        $this->output->writeln('mysql flushed', null);
        $this->output->writeln('flushing mongo db', null);

        $indexer = new Indexer();
        $indexer->flushCollections();

        $this->output->writeln('mongodb flushed', null);
        $this->output->writeln('done', null);

        return 0;
    }
}
