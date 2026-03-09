<?php

namespace Pressmind\Tests\Unit\CLI;

use PHPUnit\Framework\TestCase;
use Pressmind\CLI\FulltextIndexerCommand;

class FulltextIndexerCommandTest extends TestCase
{
    public function testHelpOptionReturnsZero(): void
    {
        $cmd = new FulltextIndexerCommand();
        ob_start();
        $result = $cmd->run(['script.php', '--help']);
        $output = ob_get_clean();
        $this->assertSame(0, $result);
        $this->assertStringContainsString('fulltext_indexer.php', $output);
    }

    public function testHelpArgumentReturnsZero(): void
    {
        $cmd = new FulltextIndexerCommand();
        ob_start();
        $result = $cmd->run(['script.php', 'help']);
        $output = ob_get_clean();
        $this->assertSame(0, $result);
        $this->assertStringContainsString('usage:', $output);
    }

    public function testShortHelpOptionReturnsZero(): void
    {
        $cmd = new FulltextIndexerCommand();
        ob_start();
        $result = $cmd->run(['script.php', '-h']);
        $output = ob_get_clean();
        $this->assertSame(0, $result);
        $this->assertStringContainsString('Example usages:', $output);
    }
}
