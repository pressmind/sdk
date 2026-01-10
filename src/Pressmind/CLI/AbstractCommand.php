<?php

namespace Pressmind\CLI;

/**
 * Base class for all CLI commands.
 */
abstract class AbstractCommand
{
    protected Output $output;
    protected array $arguments = [];
    protected array $options = [];

    public function __construct()
    {
        $this->output = new Output();
    }

    /**
     * Executes the command.
     *
     * @param array $argv Command line arguments
     * @return int Exit code (0 = success, 1+ = error)
     */
    public function run(array $argv): int
    {
        $this->parseArguments($argv);
        return $this->execute();
    }

    /**
     * Implements the actual command logic.
     *
     * @return int Exit code
     */
    abstract protected function execute(): int;

    /**
     * Parses command line arguments.
     */
    protected function parseArguments(array $argv): void
    {
        // First element is the script name
        array_shift($argv);

        foreach ($argv as $arg) {
            if (str_starts_with($arg, '--')) {
                // Long option: --option or --option=value
                $option = substr($arg, 2);
                if (str_contains($option, '=')) {
                    [$key, $value] = explode('=', $option, 2);
                    $this->options[$key] = $value;
                } else {
                    $this->options[$option] = true;
                }
            } elseif (str_starts_with($arg, '-')) {
                // Short option: -o or -o value
                $option = substr($arg, 1);
                $this->options[$option] = true;
            } else {
                // Positional argument
                $this->arguments[] = $arg;
            }
        }
    }

    /**
     * Checks if an option is set.
     */
    protected function hasOption(string $name): bool
    {
        return isset($this->options[$name]);
    }

    /**
     * Returns the value of an option.
     */
    protected function getOption(string $name, mixed $default = null): mixed
    {
        return $this->options[$name] ?? $default;
    }

    /**
     * Returns a positional argument.
     */
    protected function getArgument(int $index, mixed $default = null): mixed
    {
        return $this->arguments[$index] ?? $default;
    }

    /**
     * Checks if command runs in non-interactive mode.
     */
    protected function isNonInteractive(): bool
    {
        return $this->hasOption('non-interactive') || $this->hasOption('n');
    }
}
