<?php

namespace Pressmind\Backend\Process;

use Pressmind\Registry;

/**
 * Executes a command line via proc_open and streams stdout/stderr as SSE.
 * Uses SSEResponse for output. Keepalive every 2 seconds.
 */
class StreamExecutor
{
    private SSEResponse $sse;
    private string $phpBinary;

    public function __construct(SSEResponse $sse, ?string $phpBinary = null)
    {
        $this->sse = $sse;
        $this->phpBinary = $phpBinary ?? self::findPhpBinary([]);
    }

    /**
     * Execute command and stream output. Command line is array of strings (e.g. ['php', 'script.php', 'arg1']).
     *
     * @param array<int, string> $commandLine
     */
    public function execute(array $commandLine): void
    {
        $this->sse->sendPadding();
        $this->sse->send(['event' => 'start', 'message' => 'Process started'], 'start');

        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $cmd = implode(' ', array_map('escapeshellarg', $commandLine));
        $process = @proc_open(
            $cmd,
            $descriptorSpec,
            $pipes,
            null,
            null
        );

        if (!is_resource($process)) {
            $this->sse->send(['event' => 'error', 'message' => 'Failed to start process'], 'error');
            $this->sse->send(['event' => 'complete', 'success' => false], 'complete');
            return;
        }

        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $lastKeepalive = time();
        $stdout = '';
        $stderr = '';
        $exitCode = -1;

        while (true) {
            $read = [$pipes[1], $pipes[2]];
            $write = null;
            $except = null;
            $n = @stream_select($read, $write, $except, 1);
            if ($n > 0) {
                foreach ($read as $stream) {
                    $chunk = stream_get_contents($stream);
                    if ($chunk !== false && $chunk !== '') {
                        if ($stream === $pipes[1]) {
                            $stdout .= $chunk;
                            $this->sse->send(['type' => 'line', 'text' => $chunk], 'message');
                        } else {
                            $stderr .= $chunk;
                            $this->sse->send(['type' => 'error', 'text' => $chunk], 'message');
                        }
                    }
                }
            }

            $status = proc_get_status($process);
            if ($status !== false && !$status['running']) {
                // Use exit code from proc_get_status(); proc_close() often returns -1 after this (PHP bug)
                if (isset($status['exitcode']) && $status['exitcode'] !== -1) {
                    $exitCode = (int) $status['exitcode'];
                }
                break;
            }

            if (time() - $lastKeepalive >= 2) {
                $this->sse->keepalive();
                $lastKeepalive = time();
            }
        }

        $remainingOut = stream_get_contents($pipes[1]);
        if ($remainingOut !== false && $remainingOut !== '') {
            $this->sse->send(['type' => 'line', 'text' => $remainingOut], 'message');
        }
        $remainingErr = stream_get_contents($pipes[2]);
        if ($remainingErr !== false && $remainingErr !== '') {
            $this->sse->send(['type' => 'error', 'text' => $remainingErr], 'message');
        }
        fclose($pipes[1]);
        fclose($pipes[2]);

        $procCloseCode = proc_close($process);
        if ($exitCode === -1 && $procCloseCode !== -1) {
            $exitCode = $procCloseCode;
        }
        $this->sse->send([
            'event' => 'complete',
            'success' => $exitCode === 0,
            'exitCode' => $exitCode,
        ], 'complete');
    }

    /**
     * Find PHP CLI binary from config or common paths.
     *
     * @param array<string, mixed> $config Config array (e.g. server.php_cli_binary)
     * @return string
     */
    public static function findPhpBinary(array $config): string
    {
        $configured = $config['server']['php_cli_binary'] ?? $config['php_cli_binary'] ?? null;
        if ($configured !== null && $configured !== '' && is_string($configured)) {
            return $configured;
        }
        try {
            $fullConfig = Registry::getInstance()->get('config');
            $configured = $fullConfig['server']['php_cli_binary'] ?? null;
            if ($configured !== null && $configured !== '' && is_string($configured)) {
                return $configured;
            }
        } catch (\Throwable $e) {
            // ignore
        }
        $paths = ['/usr/bin/php', '/usr/local/bin/php', PHP_BINDIR . '/php'];
        foreach ($paths as $path) {
            if (is_executable($path)) {
                return $path;
            }
        }
        return 'php';
    }
}
