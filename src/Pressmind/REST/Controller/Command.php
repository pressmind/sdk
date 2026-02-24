<?php

namespace Pressmind\REST\Controller;

use Pressmind\Backend\CommandRegistry;
use Pressmind\Backend\Process\SSEResponse;
use Pressmind\Backend\Process\StreamExecutor;
use Pressmind\Registry;

/**
 * REST controller for CLI command streaming (SSE) and listing.
 * Command endpoints require both API key and Basic Auth; other REST endpoints are unchanged.
 */
class Command
{
    use RequireApiKeyAndBasicAuthTrait;

    /** @var string[] Commands that must not be run via REST API (e.g. destructive system reset). */
    private static $excludedCommands = ['reset'];

    /**
     * Stream CLI command output as Server-Sent Events.
     * Sends CORS + SSE headers, runs StreamExecutor, then exits.
     *
     * @param array $parameters Request parameters (command, api_key, and command-specific args like ids)
     * @return void Exits after streaming
     */
    public function stream($parameters)
    {
        $this->sendCorsHeaders();
        $this->requireApiKeyAndBasicAuth($parameters);

        $commandName = isset($parameters['command']) ? $parameters['command'] : '';
        if ($commandName === '' || !CommandRegistry::has($commandName)) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(400);
            echo json_encode(['error' => 'Unknown command']);
            exit;
        }
        if (in_array($commandName, self::$excludedCommands, true)) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(403);
            echo json_encode(['error' => 'Command not allowed via REST API']);
            exit;
        }

        $args = $this->buildCommandArgs($parameters);
        $cliRunner = $this->getCliRunnerPath();

        if ($cliRunner === null || $cliRunner === '') {
            $sse = new SSEResponse();
            $sse->sendPadding();
            $sse->send(['event' => 'error', 'message' => 'CLI runner not configured (backend.cli_runner)'], 'error');
            $sse->send(['event' => 'complete', 'success' => false, 'message' => 'CLI runner not configured'], 'complete');
            exit;
        }

        $phpBinary = StreamExecutor::findPhpBinary([]);
        $argv = CommandRegistry::buildArgv($commandName, $args);
        $commandLine = array_merge([$phpBinary, $cliRunner], $argv);

        $sse = new SSEResponse();
        $executor = new StreamExecutor($sse, $phpBinary);
        $executor->execute($commandLine);
        exit;
    }

    /**
     * Return all available CLI commands (for Electron App UI).
     *
     * @param array $parameters Request parameters (must include valid API key and Basic Auth)
     * @return array
     * @throws \Exception When API key or Basic Auth is not configured or not provided/valid
     */
    public function listCommands($parameters)
    {
        $this->requireApiKeyAndBasicAuth($parameters);
        return CommandRegistry::getAll();
    }

    /**
     * Build command args from request parameters, excluding REST/auth keys.
     *
     * @param array $parameters
     * @return array<string, string>
     */
    private function buildCommandArgs(array $parameters)
    {
        $exclude = ['command', 'api_key'];
        $args = [];
        foreach ($parameters as $k => $v) {
            if (in_array($k, $exclude, true)) {
                continue;
            }
            if (is_string($v)) {
                $args[$k] = $v;
            }
        }
        return $args;
    }

    private function getCliRunnerPath()
    {
        try {
            $config = Registry::getInstance()->get('config');
            $backend = $config['backend'] ?? null;
            if (!is_array($backend)) {
                return null;
            }
            $path = $backend['cli_runner'] ?? null;
            if ($path === null || $path === '') {
                return null;
            }
            if (defined('APPLICATION_PATH')) {
                $path = str_replace('APPLICATION_PATH', APPLICATION_PATH, $path);
            }
            if (defined('BASE_PATH')) {
                $path = str_replace('BASE_PATH', BASE_PATH, $path);
            }
            return $path;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Send CORS headers for cross-origin requests (e.g. Electron/Browser app).
     */
    private function sendCorsHeaders()
    {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS, HEAD');
        header('Access-Control-Allow-Headers: Origin, Content-Type, X-Auth-Token, Authorization, Cache-Control, Pragma, Expires');
    }
}
