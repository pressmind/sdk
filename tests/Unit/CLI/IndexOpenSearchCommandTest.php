<?php

namespace Pressmind\Tests\Unit\CLI;

use PHPUnit\Framework\TestCase;
use Pressmind\CLI\IndexOpenSearchCommand;

class IndexOpenSearchCommandTest extends TestCase
{
    public function testHelpOptionShowsUsage(): void
    {
        $cmd = new IndexOpenSearchCommand();
        ob_start();
        $result = $cmd->run(['script.php', '--help']);
        $output = ob_get_clean();
        $this->assertSame(0, $result);
        $this->assertStringContainsString('index_opensearch.php', $output);
    }

    public function testHelpArgumentShowsUsage(): void
    {
        $cmd = new IndexOpenSearchCommand();
        ob_start();
        $result = $cmd->run(['script.php', 'help']);
        $output = ob_get_clean();
        $this->assertSame(0, $result);
        $this->assertStringContainsString('usage:', $output);
    }

    public function testNoArgumentShowsHelp(): void
    {
        $cmd = new IndexOpenSearchCommand();
        ob_start();
        $result = $cmd->run(['script.php']);
        $output = ob_get_clean();
        $this->assertSame(0, $result);
        $this->assertStringContainsString('Example usages:', $output);
    }

    public function testUnknownSubcommandShowsHelp(): void
    {
        $cmd = new IndexOpenSearchCommand();
        ob_start();
        $result = $cmd->run(['script.php', 'unknown_subcommand']);
        $output = ob_get_clean();
        $this->assertSame(0, $result);
        $this->assertStringContainsString('usage:', $output);
    }
}
