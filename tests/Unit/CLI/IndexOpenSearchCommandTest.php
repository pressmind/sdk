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

    public function testDestroySubcommandDeletesIds(): void
    {
        $deleted = new \ArrayObject();
        $cmd = new class($deleted) extends IndexOpenSearchCommand {
            private \ArrayObject $deleted;

            public function __construct(\ArrayObject $deleted)
            {
                parent::__construct();
                $this->deleted = $deleted;
            }

            protected function createIndexer()
            {
                return new class($this->deleted) {
                    private \ArrayObject $deleted;

                    public function __construct(\ArrayObject $deleted)
                    {
                        $this->deleted = $deleted;
                    }

                    public function deleteMediaObject(array $ids): void
                    {
                        foreach ($ids as $id) {
                            $this->deleted->append($id);
                        }
                    }
                };
            }
        };

        ob_start();
        $result = $cmd->run(['script.php', 'destroy', '123,456']);
        ob_end_clean();

        $this->assertSame(0, $result);
        $this->assertSame([123, 456], $deleted->getArrayCopy());
    }
}
