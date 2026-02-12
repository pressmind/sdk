<?php

namespace Pressmind\CLI\WordPress;

use Pressmind\CLI\AbstractCommand;
use RuntimeException;

/**
 * Migrate Site Command
 *
 * Migrates a WordPress site from one domain to another.
 * Changes all database records, config files, and regenerates .htaccess.
 *
 * Usage:
 *   php cli/migrate-site.php --new-site=http://wordpress.local
 *   php cli/migrate-site.php --new-site=https://wordpress.de --old-site=http://wordpress.local
 *   php cli/migrate-site.php --new-site=https://wordpress.de --old-site=http://wordpress.local --id-blog=1
 */
class MigrateSiteCommand extends AbstractCommand
{
    protected function execute(): int
    {
        $newSite = $this->getOption('new-site');
        $oldSite = $this->getOption('old-site');
        $idBlog = $this->getOption('id-blog') ?? $this->getOption('id_blog');

        if ($newSite === null || $newSite === true) {
            $this->printHelp();
            return 0;
        }

        if ($this->hasOption('help') || $this->hasOption('h')) {
            $this->printHelp();
            return 0;
        }

        try {
            Migrate::toSite(
                (string) $newSite,
                ($oldSite !== null && $oldSite !== true) ? (string) $oldSite : null,
                ($idBlog !== null && $idBlog !== true) ? (int) $idBlog : null
            );
        } catch (RuntimeException $e) {
            $this->output->error($e->getMessage());
            return 1;
        }

        return 0;
    }

    private function printHelp(): void
    {
        $helptext = "usage:\n";
        $helptext .= "--new-site    example: php migrate-site.php --new-site=http://wordpress.local (the old site will read from installation)\n";
        $helptext .= "--old-site    example: php migrate-site.php --new-site=https://wordpress.de --old-site=http://wordpress.local\n";
        $helptext .= "--id-blog     example for multisites (old-site must be set): php migrate-site.php --new-site=https://wordpress.de --old-site=http://wordpress.local --id-blog=1\n";
        $this->output->write($helptext, null);
    }
}
