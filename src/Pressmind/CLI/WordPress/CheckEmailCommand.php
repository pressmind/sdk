<?php

namespace Pressmind\CLI\WordPress;

use Pressmind\CLI\AbstractCommand;

/**
 * Check Email Command
 *
 * Sends a test e-mail via WordPress wp_mail() to verify the mail configuration.
 *
 * Usage:
 *   php cli/check_email.php
 *   php cli/check_email.php test@example.com
 *   php cli/check_email.php test@example.com --smtp-debug
 */
class CheckEmailCommand extends AbstractCommand
{
    protected function execute(): int
    {
        $emailTo = $this->getArgument(0);

        if ($emailTo === null) {
            $emailTo = readline('Send email to <name@email.de>: ');
        }

        $smtpDebug = $this->hasOption('smtp-debug');

        try {
            $result = Tools::sendTestEmail($emailTo, $smtpDebug);
            if ($result) {
                $this->output->success('email sent, check your inbox');
            } else {
                $this->output->error('wp_mail() returned false');
                return 1;
            }
        } catch (\RuntimeException $e) {
            $this->output->error($e->getMessage());
            return 1;
        }

        return 0;
    }
}
