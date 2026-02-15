<?php

namespace Pressmind\Backend\Controller;

use Pressmind\Backend\CommandRegistry;
use Pressmind\Backend\Process\SSEResponse;
use Pressmind\Backend\Process\StreamExecutor;
use Pressmind\Registry;

/**
 * Import: queue, status, quick actions, SSE streaming.
 */
class ImportController extends AbstractController
{
    /**
     * Import page removed: redirect to Commands (fullimport, import mediaobject, etc. available there).
     * Stream endpoint (action=stream) is still used by Validation orphan re-import.
     */
    public function indexAction(): void
    {
        $base = isset($_SERVER['REQUEST_URI']) ? strtok($_SERVER['REQUEST_URI'], '?') : '';
        $url = ($base !== '' ? $base . '?' : '?') . 'page=commands';
        header('Location: ' . $url, true, 302);
        exit;
    }

    public function streamAction(): void
    {
        $command = $this->get('command');
        if ($command === null || $command === '') {
            $command = 'fullimport';
        }
        $ids = $this->get('ids');
        $args = [];
        if ($ids !== null && $ids !== '') {
            $args['ids'] = $ids;
        }
        $commandMap = [
            'fullimport' => 'fullimport',
            'mediaobject' => 'import mediaobject',
            'touristic' => 'import touristic',
        ];
        $registryName = $commandMap[$command] ?? 'fullimport';
        if (!CommandRegistry::has($registryName)) {
            $registryName = 'fullimport';
        }
        $cliRunner = $this->getCliRunnerPath();
        if ($cliRunner === null || $cliRunner === '') {
            $msg = 'CLI runner not configured (backend.cli_runner)';
            $sse = new SSEResponse();
            $sse->sendPadding();
            $sse->send(['event' => 'error', 'message' => $msg], 'error');
            $sse->send(['event' => 'complete', 'success' => false, 'message' => $msg], 'complete');
            return;
        }
        $phpBinary = StreamExecutor::findPhpBinary([]);
        $argv = CommandRegistry::buildArgv($registryName, $args);
        $commandLine = array_merge([$phpBinary, $cliRunner], $argv);
        $sse = new SSEResponse();
        $executor = new StreamExecutor($sse, $phpBinary);
        $executor->execute($commandLine);
    }

    private function getCliRunnerPath(): ?string
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

}
