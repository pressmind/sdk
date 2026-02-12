<?php

namespace Pressmind\CLI;

use Pressmind\Log\Service;

/**
 * Log Cleanup Command
 *
 * Cleans up log entries. Intended to run as cron job (e.g. every night).
 *
 * Usage:
 *   php cli/log_cleanup.php
 *   php bin/log-cleanup
 */
class LogCleanupCommand extends AbstractCommand
{
    protected function execute(): int
    {
        $logService = new Service();
        $result = $logService->cleanUp();
        $this->output->writeln($result . "\n", null);
        return 0;
    }
}
