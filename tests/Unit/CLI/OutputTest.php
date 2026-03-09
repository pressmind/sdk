<?php

namespace Pressmind\Tests\Unit\CLI;

use PHPUnit\Framework\TestCase;
use Pressmind\CLI\Output;

class OutputTest extends TestCase
{
    public function testSuccessContainsOkPrefix(): void
    {
        $output = new Output();
        ob_start();
        $output->success('done');
        $result = ob_get_clean();
        $this->assertStringContainsString('[OK] done', $result);
    }

    public function testErrorContainsErrorPrefix(): void
    {
        $output = new Output();
        ob_start();
        $output->error('fail');
        $result = ob_get_clean();
        $this->assertStringContainsString('[ERROR] fail', $result);
    }

    public function testWarningContainsWarningPrefix(): void
    {
        $output = new Output();
        ob_start();
        $output->warning('caution');
        $result = ob_get_clean();
        $this->assertStringContainsString('[WARNING] caution', $result);
    }

    public function testInfoContainsInfoPrefix(): void
    {
        $output = new Output();
        ob_start();
        $output->info('note');
        $result = ob_get_clean();
        $this->assertStringContainsString('[INFO] note', $result);
    }

    public function testWritelnAppendsNewline(): void
    {
        $output = new Output();
        ob_start();
        $output->writeln('hello');
        $result = ob_get_clean();
        $this->assertStringEndsWith(PHP_EOL, $result);
    }

    public function testWriteDoesNotAppendNewline(): void
    {
        $output = new Output();
        ob_start();
        $output->write('hello');
        $result = ob_get_clean();
        $this->assertStringEndsNotWith(PHP_EOL, $result);
    }

    public function testNewLineOutputsEmptyLines(): void
    {
        $output = new Output();
        ob_start();
        $output->newLine(3);
        $result = ob_get_clean();
        $this->assertSame(str_repeat(PHP_EOL, 3), $result);
    }

    public function testNewLineDefaultIsOne(): void
    {
        $output = new Output();
        ob_start();
        $output->newLine();
        $result = ob_get_clean();
        $this->assertSame(PHP_EOL, $result);
    }

    public function testWriteWithNullColorOutputsPlainText(): void
    {
        $output = new Output();
        ob_start();
        $output->write('plain', null);
        $result = ob_get_clean();
        $this->assertSame('plain', $result);
    }

    public function testWriteWithUnknownColorOutputsPlainText(): void
    {
        $output = new Output();
        ob_start();
        $output->write('text', 'nonexistent');
        $result = ob_get_clean();
        $this->assertStringContainsString('text', $result);
    }

    public function testWritelnWithColorContainsMessage(): void
    {
        $output = new Output();
        ob_start();
        $output->writeln('colored', 'green');
        $result = ob_get_clean();
        $this->assertStringContainsString('colored', $result);
    }

    public function testMultipleWriteCalls(): void
    {
        $output = new Output();
        ob_start();
        $output->write('a');
        $output->write('b');
        $output->write('c');
        $result = ob_get_clean();
        $this->assertStringContainsString('a', $result);
        $this->assertStringContainsString('b', $result);
        $this->assertStringContainsString('c', $result);
    }
}
