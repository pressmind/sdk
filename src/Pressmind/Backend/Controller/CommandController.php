<?php

namespace Pressmind\Backend\Controller;

use Pressmind\Backend\CommandRegistry;
use Pressmind\Backend\Process\SSEResponse;
use Pressmind\Backend\Process\StreamExecutor;
use Pressmind\Registry;

/**
 * CLI commands: list, execute UI, SSE stream.
 */
class CommandController extends AbstractController
{
    public function indexAction(): void
    {
        $commands = CommandRegistry::getAll();
        $this->render('command/index.php', [
            'title' => 'Commands',
            'commands' => $commands,
        ]);
    }

    public function executeAction(): void
    {
        $commandName = $this->get('command');
        $command = $commandName ? CommandRegistry::get($commandName) : null;
        $streamNonce = $this->getAuth()->createNonce('command_stream');
        $this->render('command/execute.php', [
            'title' => 'Execute Command',
            'command' => $command,
            'commandName' => $commandName,
            'allCommands' => CommandRegistry::getAll(),
            'streamNonce' => $streamNonce,
        ]);
    }

    public function streamAction(): void
    {
        $nonce = $this->get('_pm_nonce') ?? $_POST['_pm_nonce'] ?? '';
        if ($nonce === '' || !$this->getAuth()->verifyNonce($nonce, 'command_stream')) {
            $this->json(['error' => 'Invalid or missing nonce'], 403);
            return;
        }
        $commandName = $this->get('command');
        if ($commandName === null || $commandName === '' || !CommandRegistry::has($commandName)) {
            $this->json(['error' => 'Unknown command'], 400);
            return;
        }
        $args = [];
        foreach ($_GET as $k => $v) {
            if ($k !== 'page' && $k !== 'action' && $k !== 'command' && $k !== '_pm_nonce' && is_string($v)) {
                $args[$k] = $v;
            }
        }
        foreach ($_POST as $k => $v) {
            if ($k !== 'page' && $k !== 'action' && $k !== 'command' && $k !== '_pm_nonce' && is_string($v)) {
                $args[$k] = $v;
            }
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
        $argv = CommandRegistry::buildArgv($commandName, $args);
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
