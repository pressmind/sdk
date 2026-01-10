<?php

namespace Pressmind\CLI;

use Pressmind\System\EnvironmentValidation;

/**
 * CLI command for SDK installation integrity check.
 *
 * Usage:
 *   php bin/integrity-check [--custom-path=/path/to/Custom] [--non-interactive]
 *                           [--env=ENV] [--db-user=USER] [--mongo-uri=URI] [--mongo-db=DB]
 *
 * Options:
 *   --custom-path=PATH    Path to Custom directory (default: ./Custom)
 *   --non-interactive     No interactive prompts, output only
 *   -n                    Short form for --non-interactive
 *   --env=ENV             Environment (development, production, testing)
 *   --app-path=PATH       APPLICATION_PATH (default: getcwd())
 *   --db-user=USER        Database username
 *   --mongo-uri=URI       MongoDB connection string
 *   --mongo-db=DB         MongoDB database name
 */
class IntegrityCheckCommand extends AbstractCommand
{
    protected function execute(): int
    {
        $this->output->newLine();
        $this->output->writeln('=== Pressmind SDK Integrity Check ===', 'cyan');
        $this->output->newLine();

        $hasErrors = false;

        // 1. Check PHP version
        $hasErrors = $this->checkPHPVersion() || $hasErrors;

        // 2. Check IBE-Team version
        $hasErrors = $this->checkIBETeamVersion() || $hasErrors;

        // 3. Check environment consistency
        $hasErrors = $this->checkEnvironmentConsistency() || $hasErrors;

        $this->output->newLine();

        if ($hasErrors) {
            $this->output->error('Problems found. Please fix them.');
            return 1;
        }

        $this->output->success('All checks passed!');
        return 0;
    }

    /**
     * Checks PHP version.
     *
     * @return bool True if errors were found
     */
    private function checkPHPVersion(): bool
    {
        $this->output->writeln('Checking PHP version...', 'cyan');

        $result = EnvironmentValidation::validatePHPVersion();

        if ($result['valid']) {
            $this->output->success($result['message']);
            return false;
        }

        $this->output->error($result['message']);
        return true;
    }

    /**
     * Checks IBE-Team version.
     *
     * @return bool True if errors were found
     */
    private function checkIBETeamVersion(): bool
    {
        $customPath = $this->getOption('custom-path', getcwd() . '/Custom');

        $this->output->newLine();
        $this->output->writeln('Checking IBE-Team version...', 'cyan');
        $this->output->writeln('Custom path: ' . $customPath);

        $result = EnvironmentValidation::validateIBETeamVersion($customPath);

        if ($result['valid']) {
            $this->output->success($result['message']);
            if ($result['configPath']) {
                $this->output->writeln('Config: ' . $result['configPath']);
            }
            return false;
        }

        // Error case
        $this->output->error($result['message']);

        if ($result['configPath']) {
            $this->output->writeln('Config: ' . $result['configPath']);
        }

        if ($result['currentDefinition']) {
            $this->output->writeln('Current definition: ' . $result['currentDefinition']);
        }

        $this->output->writeln('Expected: define(\'IBETEAM_VERSION\', \\Pressmind\\System\\Info::IBE_IMPORT_VERSION)');

        // Only offer patch if config file exists and not non-interactive
        if ($result['configPath'] === null) {
            $this->output->info('No config file found to patch.');
            return true;
        }

        if ($this->isNonInteractive()) {
            $this->output->info('Non-interactive mode: Run patch manually.');
            return true;
        }

        // Interactive prompt
        $this->output->newLine();
        if ($this->output->prompt('Should the file be patched?', false)) {
            if (EnvironmentValidation::patchIBETeamVersion($result['configPath'])) {
                $this->output->success('File was successfully patched.');
                return false;
            } else {
                $this->output->error('Patch could not be applied.');
                return true;
            }
        }

        $this->output->warning('Patch was not applied.');
        return true;
    }

    /**
     * Checks environment consistency.
     * 
     * Production must not have "development" values and vice versa.
     *
     * @return bool True if errors were found
     */
    private function checkEnvironmentConsistency(): bool
    {
        // Get values from options or constants/environment variables
        $env = $this->getOption('env');
        $appPath = $this->getOption('app-path', getcwd());
        $dbUser = $this->getOption('db-user');
        $mongoUri = $this->getOption('mongo-uri');
        $mongoDb = $this->getOption('mongo-db');

        // Try to read ENV from constant if not passed as option
        if ($env === null && defined('ENV')) {
            $env = ENV;
        }
        
        // Try to read from environment variable
        if ($env === null) {
            $env = getenv('APP_ENV') ?: null;
        }

        // If no environment defined, skip check
        if ($env === null) {
            $this->output->newLine();
            $this->output->writeln('Checking environment consistency...', 'cyan');
            $this->output->info('No environment defined (--env, ENV constant or APP_ENV). Check skipped.');
            return false;
        }

        $this->output->newLine();
        $this->output->writeln('Checking environment consistency...', 'cyan');
        $this->output->writeln('Environment: ' . $env);

        $result = EnvironmentValidation::validateEnvironmentConsistency(
            $env,
            $appPath,
            $dbUser,
            $mongoUri,
            $mongoDb
        );

        if ($result['valid']) {
            $this->output->success($result['message']);
            return false;
        }

        // Critical error - display prominent banner
        $this->displayCriticalWarningBanner($result);

        return true;
    }

    /**
     * Displays a prominent warning banner for critical environment errors.
     */
    private function displayCriticalWarningBanner(array $result): void
    {
        $this->output->newLine();

        // ASCII banner
        $lines = [
            '╔═══════════════════════════════════════════════════════════════════════╗',
            '║                                                                       ║',
            '║   ██╗    ██╗ █████╗ ██████╗ ███╗   ██╗██╗███╗   ██╗ ██████╗           ║',
            '║   ██║    ██║██╔══██╗██╔══██╗████╗  ██║██║████╗  ██║██╔════╝           ║',
            '║   ██║ █╗ ██║███████║██████╔╝██╔██╗ ██║██║██╔██╗ ██║██║  ███╗          ║',
            '║   ██║███╗██║██╔══██║██╔══██╗██║╚██╗██║██║██║╚██╗██║██║   ██║          ║',
            '║   ╚███╔███╔╝██║  ██║██║  ██║██║ ╚████║██║██║ ╚████║╚██████╔╝          ║',
            '║    ╚══╝╚══╝ ╚═╝  ╚═╝╚═╝  ╚═╝╚═╝  ╚═══╝╚═╝╚═╝  ╚═══╝ ╚═════╝           ║',
            '║                                                                       ║',
            '╠═══════════════════════════════════════════════════════════════════════╣',
            '║   CRITICAL ERROR: ENVIRONMENT CONFIGURATION INCONSISTENT!             ║',
            '╠═══════════════════════════════════════════════════════════════════════╣',
        ];

        // Output header
        foreach ($lines as $line) {
            $this->output->writeln($line, 'red');
        }

        // Dynamic lines for ENV and violations
        $envLine = sprintf('║   ENV is "%s", but following values contain "%s":', 
            $result['env'], 
            $result['forbiddenString']
        );
        $this->output->writeln(str_pad($envLine, 75) . '║', 'red');
        $this->output->writeln('║                                                                       ║', 'red');

        foreach ($result['violations'] as $violation) {
            $violationLine = sprintf('║   - %s: %s', 
                $violation['field'],
                $this->truncateString($violation['value'], 50)
            );
            $this->output->writeln(str_pad($violationLine, 75) . '║', 'red');
        }

        $this->output->writeln('║                                                                       ║', 'red');
        $this->output->writeln('╚═══════════════════════════════════════════════════════════════════════╝', 'red');
        $this->output->newLine();
    }

    /**
     * Truncates a string to a maximum length.
     */
    private function truncateString(string $string, int $maxLength): string
    {
        if (strlen($string) <= $maxLength) {
            return $string;
        }
        return substr($string, 0, $maxLength - 3) . '...';
    }
}
