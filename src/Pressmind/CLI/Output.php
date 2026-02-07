<?php

namespace Pressmind\CLI;

use jc21\CliTable;

/**
 * Reusable class for CLI output.
 * Provides colored output, prompts and table formatting.
 */
class Output
{
    private bool $supportsColors;

    public function __construct()
    {
        $this->supportsColors = $this->detectColorSupport();
    }

    /**
     * Outputs a success message (green).
     */
    public function success(string $message): void
    {
        $this->writeln('[OK] ' . $message, 'green');
    }

    /**
     * Outputs an error message (red).
     */
    public function error(string $message): void
    {
        $this->writeln('[ERROR] ' . $message, 'red');
    }

    /**
     * Outputs a warning message (yellow).
     */
    public function warning(string $message): void
    {
        $this->writeln('[WARNING] ' . $message, 'yellow');
    }

    /**
     * Outputs an info message (cyan).
     */
    public function info(string $message): void
    {
        $this->writeln('[INFO] ' . $message, 'cyan');
    }

    /**
     * Outputs a line with newline.
     */
    public function writeln(string $message, ?string $color = null): void
    {
        $this->write($message, $color);
        echo PHP_EOL;
    }

    /**
     * Outputs text without newline.
     */
    public function write(string $message, ?string $color = null): void
    {
        if ($this->supportsColors && $color !== null) {
            echo $this->colorize($message, $color);
        } else {
            echo $message;
        }
    }

    /**
     * Outputs empty line(s).
     */
    public function newLine(int $count = 1): void
    {
        echo str_repeat(PHP_EOL, $count);
    }

    /**
     * Interactive yes/no prompt.
     *
     * @param string $question The question to ask
     * @param bool $default Default answer when Enter is pressed
     * @return bool True for yes, false for no
     */
    public function prompt(string $question, bool $default = false): bool
    {
        $hint = $default ? '[Y/n]' : '[y/N]';
        $this->write($question . ' ' . $hint . ': ');

        $handle = fopen('php://stdin', 'r');
        $input = strtolower(trim(fgets($handle)));
        fclose($handle);

        if ($input === '') {
            return $default;
        }

        return $input === 'y' || $input === 'yes';
    }

    /**
     * Interactive yes/no/all prompt.
     * Use when the same question is asked repeatedly; "a" means yes and apply to all remaining.
     *
     * @param string $question The question to ask
     * @param bool $default Default answer when Enter is pressed
     * @return string 'y' for yes, 'n' for no, 'a' for all (yes to this and all remaining)
     */
    public function promptWithAll(string $question, bool $default = false): string
    {
        $hint = $default ? '[Y/n/a]' : '[y/N/a]';
        $this->write($question . ' ' . $hint . ': ');

        $handle = fopen('php://stdin', 'r');
        $input = strtolower(trim(fgets($handle)));
        fclose($handle);

        if ($input === '') {
            return $default ? 'y' : 'n';
        }
        if ($input === 'a' || $input === 'all') {
            return 'a';
        }
        return ($input === 'y' || $input === 'yes') ? 'y' : 'n';
    }

    /**
     * Outputs a formatted table.
     *
     * @param array $headers Column headers
     * @param array $rows Rows as array of arrays
     */
    public function table(array $headers, array $rows): void
    {
        $table = new CliTable();
        $table->setTableColor('blue');
        $table->setHeaderColor('cyan');

        foreach ($headers as $header) {
            $table->addField($header, $header);
        }

        $tableData = [];
        foreach ($rows as $row) {
            $rowData = [];
            foreach ($headers as $index => $header) {
                $rowData[$header] = $row[$index] ?? '';
            }
            $tableData[] = $rowData;
        }

        $table->injectData($tableData);
        $table->display();
    }

    /**
     * Colorizes text.
     */
    private function colorize(string $text, string $color): string
    {
        $colors = [
            'black' => "\033[30m",
            'red' => "\033[31m",
            'green' => "\033[32m",
            'yellow' => "\033[33m",
            'blue' => "\033[34m",
            'magenta' => "\033[35m",
            'cyan' => "\033[36m",
            'white' => "\033[37m",
            'reset' => "\033[0m"
        ];

        if (!isset($colors[$color])) {
            return $text;
        }

        return $colors[$color] . $text . $colors['reset'];
    }

    /**
     * Detects if terminal supports colors.
     */
    private function detectColorSupport(): bool
    {
        if (php_sapi_name() !== 'cli') {
            return false;
        }

        if (DIRECTORY_SEPARATOR === '\\') {
            // Windows: Check for ConEmu, ANSICON or Windows 10+
            return getenv('ANSICON') !== false
                || getenv('ConEmuANSI') === 'ON'
                || getenv('TERM') === 'xterm';
        }

        return stream_isatty(STDOUT);
    }
}
