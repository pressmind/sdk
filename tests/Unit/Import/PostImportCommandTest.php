<?php

namespace Pressmind {
    function strtolower($string)
    {
        return \Pressmind\Tests\Unit\Import\PostImportCommandExecSpy::strtolower($string);
    }

    function exec($command, &$output = null, &$result_code = null)
    {
        return \Pressmind\Tests\Unit\Import\PostImportCommandExecSpy::exec($command, $output, $result_code);
    }
}

namespace Pressmind\Tests\Unit\Import {

    use Pressmind\Import;
    use Pressmind\Registry;
    use Pressmind\Tests\Unit\AbstractTestCase;

    class PostImportCommandTest extends AbstractTestCase
    {
        protected function tearDown(): void
        {
            PostImportCommandExecSpy::deactivate();
            parent::tearDown();
        }

        public function testPostImportStartsFileDownloaderWhenImageProcessorIsAlreadyRunning(): void
        {
            $this->configureImportRuntime();
            $imageProcessorPath = APPLICATION_PATH . '/cli/image_processor.php mediaobject 123';
            PostImportCommandExecSpy::activate([
                'www-data 1234 1 0 20:00 ? 00:00:00 php ' . $imageProcessorPath,
            ]);

            $import = new Import();
            ob_start();
            try {
                $import->postImport(123);
            } finally {
                ob_end_clean();
            }

            $commands = PostImportCommandExecSpy::getCommands();
            $this->assertTrue(
                $this->commandsContain($commands, '/cli/file_downloader.php'),
                'Expected postImport() to start file_downloader.php. Commands: ' . implode("\n", $commands)
            );
            $this->assertFalse(
                $this->commandsContain($commands, '/cli/image_processor.php'),
                'Image processor was marked as already running and must not be started again. Commands: ' . implode("\n", $commands)
            );
        }

        private function configureImportRuntime(): void
        {
            Registry::getInstance()->add('config', $this->createMockConfig([
                'server' => [
                    'php_cli_binary' => PHP_BINARY,
                ],
                'data' => [
                    'media_type_custom_post_import_hooks' => [],
                    'schema_migration' => [
                        'mode' => 'log_only',
                    ],
                ],
                'file_storage' => [
                    'import_enabled' => false,
                ],
                'logging' => [
                    'log_file_path' => 'APPLICATION_PATH/logs',
                ],
            ]));
        }

        /**
         * @param string[] $commands
         */
        private function commandsContain(array $commands, string $needle): bool
        {
            foreach ($commands as $command) {
                if (strpos($command, $needle) !== false) {
                    return true;
                }
            }
            return false;
        }
    }

    final class PostImportCommandExecSpy
    {
        /**
         * @var bool
         */
        private static $active = false;

        /**
         * @var string[]
         */
        private static $processList = [];

        /**
         * @var string[]
         */
        private static $commands = [];

        /**
         * @param string[] $processList
         */
        public static function activate(array $processList): void
        {
            self::$active = true;
            self::$processList = $processList;
            self::$commands = [];
        }

        public static function deactivate(): void
        {
            self::$active = false;
            self::$processList = [];
            self::$commands = [];
        }

        public static function exec($command, &$output = null, &$result_code = null)
        {
            if (!self::$active) {
                return \exec($command, $output, $result_code);
            }

            if ($command === 'ps -C php -f') {
                $output = self::$processList;
                $result_code = 0;
                return end(self::$processList) ?: '';
            }

            self::$commands[] = $command;
            $output = [];
            $result_code = 0;
            return '';
        }

        public static function strtolower($string): string
        {
            if (self::$active && $string === PHP_OS) {
                return 'linux';
            }
            return \strtolower($string);
        }

        /**
         * @return string[]
         */
        public static function getCommands(): array
        {
            return self::$commands;
        }
    }
}
