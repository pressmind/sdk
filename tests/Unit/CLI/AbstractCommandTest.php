<?php

namespace Pressmind\Tests\Unit\CLI;

use PHPUnit\Framework\TestCase;
use Pressmind\CLI\AbstractCommand;

class ConcreteTestCommand extends AbstractCommand
{
    public int $executeCallCount = 0;
    public int $returnCode = 0;

    protected function execute(): int
    {
        $this->executeCallCount++;
        return $this->returnCode;
    }

    public function exposeOptions(): array
    {
        return $this->options;
    }

    public function exposeArguments(): array
    {
        return $this->arguments;
    }

    public function exposeHasOption(string $name): bool
    {
        return $this->hasOption($name);
    }

    public function exposeGetOption(string $name, mixed $default = null): mixed
    {
        return $this->getOption($name, $default);
    }

    public function exposeGetArgument(int $index, mixed $default = null): mixed
    {
        return $this->getArgument($index, $default);
    }

    public function exposeIsNonInteractive(): bool
    {
        return $this->isNonInteractive();
    }
}

class AbstractCommandTest extends TestCase
{
    public function testRunCallsExecuteAndReturnsExitCode(): void
    {
        $cmd = new ConcreteTestCommand();
        $result = $cmd->run(['script.php']);
        $this->assertSame(0, $result);
        $this->assertSame(1, $cmd->executeCallCount);
    }

    public function testRunReturnsNonZeroExitCode(): void
    {
        $cmd = new ConcreteTestCommand();
        $cmd->returnCode = 42;
        $this->assertSame(42, $cmd->run(['script.php']));
    }

    public function testParseArgumentsLongOptionWithValue(): void
    {
        $cmd = new ConcreteTestCommand();
        $cmd->run(['script.php', '--config=/path/to/file']);
        $this->assertSame('/path/to/file', $cmd->exposeGetOption('config'));
    }

    public function testParseArgumentsLongOptionBoolean(): void
    {
        $cmd = new ConcreteTestCommand();
        $cmd->run(['script.php', '--verbose']);
        $this->assertTrue($cmd->exposeHasOption('verbose'));
        $this->assertTrue($cmd->exposeGetOption('verbose'));
    }

    public function testParseArgumentsShortOption(): void
    {
        $cmd = new ConcreteTestCommand();
        $cmd->run(['script.php', '-v']);
        $this->assertTrue($cmd->exposeHasOption('v'));
    }

    public function testParseArgumentsPositionalArguments(): void
    {
        $cmd = new ConcreteTestCommand();
        $cmd->run(['script.php', 'all', '123,456']);
        $this->assertSame('all', $cmd->exposeGetArgument(0));
        $this->assertSame('123,456', $cmd->exposeGetArgument(1));
    }

    public function testParseArgumentsMixed(): void
    {
        $cmd = new ConcreteTestCommand();
        $cmd->run(['script.php', '--host=localhost', '-n', 'install', '--drop-tables']);
        $this->assertSame('localhost', $cmd->exposeGetOption('host'));
        $this->assertTrue($cmd->exposeHasOption('n'));
        $this->assertTrue($cmd->exposeHasOption('drop-tables'));
        $this->assertSame('install', $cmd->exposeGetArgument(0));
    }

    public function testGetOptionDefaultValue(): void
    {
        $cmd = new ConcreteTestCommand();
        $cmd->run(['script.php']);
        $this->assertNull($cmd->exposeGetOption('missing'));
        $this->assertSame('fallback', $cmd->exposeGetOption('missing', 'fallback'));
    }

    public function testGetArgumentDefaultValue(): void
    {
        $cmd = new ConcreteTestCommand();
        $cmd->run(['script.php']);
        $this->assertNull($cmd->exposeGetArgument(0));
        $this->assertSame('default', $cmd->exposeGetArgument(0, 'default'));
    }

    public function testHasOptionReturnsFalseForMissing(): void
    {
        $cmd = new ConcreteTestCommand();
        $cmd->run(['script.php']);
        $this->assertFalse($cmd->exposeHasOption('nonexistent'));
    }

    public function testIsNonInteractiveLongOption(): void
    {
        $cmd = new ConcreteTestCommand();
        $cmd->run(['script.php', '--non-interactive']);
        $this->assertTrue($cmd->exposeIsNonInteractive());
    }

    public function testIsNonInteractiveShortOption(): void
    {
        $cmd = new ConcreteTestCommand();
        $cmd->run(['script.php', '-n']);
        $this->assertTrue($cmd->exposeIsNonInteractive());
    }

    public function testIsNonInteractiveFalseByDefault(): void
    {
        $cmd = new ConcreteTestCommand();
        $cmd->run(['script.php']);
        $this->assertFalse($cmd->exposeIsNonInteractive());
    }

    public function testScriptNameIsStrippedFromArguments(): void
    {
        $cmd = new ConcreteTestCommand();
        $cmd->run(['myscript.php', 'first']);
        $this->assertSame('first', $cmd->exposeGetArgument(0));
        $this->assertNotContains('myscript.php', $cmd->exposeArguments());
    }

    public function testLongOptionWithEqualsContainingEquals(): void
    {
        $cmd = new ConcreteTestCommand();
        $cmd->run(['script.php', '--query=SELECT * FROM t WHERE a=1']);
        $this->assertSame('SELECT * FROM t WHERE a=1', $cmd->exposeGetOption('query'));
    }

    public function testEmptyArgvArray(): void
    {
        $cmd = new ConcreteTestCommand();
        $cmd->run([]);
        $this->assertEmpty($cmd->exposeArguments());
        $this->assertEmpty($cmd->exposeOptions());
    }
}
