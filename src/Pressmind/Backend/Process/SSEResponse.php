<?php

namespace Pressmind\Backend\Process;

/**
 * SSE response helper: headers, padding, send, keepalive.
 * Use for streaming command output to the browser.
 */
class SSEResponse
{
    private bool $sentHeaders = false;

    public function __construct()
    {
        if (function_exists('apache_setenv')) {
            @apache_setenv('no-gzip', '1');
        }
        @ini_set('zlib.output_compression', '0');
        set_time_limit(0);
        if (ob_get_level()) {
            while (ob_get_level()) {
                ob_end_clean();
            }
        }
        ob_implicit_flush(true);
    }

    /**
     * Send SSE headers. Call once before first send.
     */
    public function sendHeaders(): void
    {
        if ($this->sentHeaders) {
            return;
        }
        header('Content-Type: text/event-stream; charset=utf-8');
        header('Cache-Control: no-cache');
        header('X-Accel-Buffering: no');
        header('Content-Encoding: none');
        $this->sentHeaders = true;
        $this->flush();
    }

    /**
     * Send 4KB padding to overcome server buffering.
     */
    public function sendPadding(): void
    {
        $this->sendHeaders();
        echo str_repeat(' ', 4096);
        $this->flush();
    }

    /**
     * Send one SSE message (JSON payload).
     *
     * @param array|object|string $data
     */
    public function send($data, ?string $event = null): void
    {
        $this->sendHeaders();
        if ($event !== null && $event !== '') {
            echo 'event: ' . $event . "\n";
        }
        $payload = is_string($data) ? $data : json_encode($data);
        echo 'data: ' . $payload . "\n\n";
        $this->flush();
    }

    /**
     * Send a keepalive comment.
     */
    public function keepalive(): void
    {
        $this->sendHeaders();
        echo ": keepalive\n\n";
        $this->flush();
    }

    private function flush(): void
    {
        if (ob_get_level()) {
            ob_flush();
        }
        flush();
    }
}
