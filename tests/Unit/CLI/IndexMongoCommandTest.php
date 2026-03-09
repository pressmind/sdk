<?php

namespace Pressmind\Tests\Unit\CLI;

use PHPUnit\Framework\TestCase;
use Pressmind\CLI\IndexMongoCommand;

class IndexMongoCommandTest extends TestCase
{
    public function testHelpOptionShowsUsage(): void
    {
        $cmd = new IndexMongoCommand();
        ob_start();
        $result = $cmd->run(['script.php', '--help']);
        $output = ob_get_clean();
        $this->assertSame(0, $result);
        $this->assertStringContainsString('index_mongo.php', $output);
    }

    public function testHelpArgumentShowsUsage(): void
    {
        $cmd = new IndexMongoCommand();
        ob_start();
        $result = $cmd->run(['script.php', 'help']);
        $output = ob_get_clean();
        $this->assertSame(0, $result);
        $this->assertStringContainsString('usage:', $output);
    }

    public function testNoArgumentShowsHelp(): void
    {
        $cmd = new IndexMongoCommand();
        ob_start();
        $result = $cmd->run(['script.php']);
        $output = ob_get_clean();
        $this->assertSame(0, $result);
        $this->assertStringContainsString('Example usages:', $output);
    }

}
