<?php

namespace Pressmind\Tests\Unit\CLI;

use PHPUnit\Framework\TestCase;
use Pressmind\CLI\IntegrityCheckCommand;

class IntegrityCheckCommandTest extends TestCase
{
    /**
     * Test truncateString via reflection (private method, pure logic).
     *
     * @dataProvider truncateStringProvider
     */
    public function testTruncateString(string $input, int $maxLength, string $expected): void
    {
        $cmd = new IntegrityCheckCommand();
        $ref = new \ReflectionMethod($cmd, 'truncateString');
        $ref->setAccessible(true);
        $this->assertSame($expected, $ref->invoke($cmd, $input, $maxLength));
    }

    public static function truncateStringProvider(): array
    {
        return [
            'short string unchanged' => ['hello', 10, 'hello'],
            'exact length unchanged' => ['hello', 5, 'hello'],
            'long string truncated' => ['hello world this is long', 10, 'hello w...'],
            'very short max' => ['abcdef', 4, 'a...'],
        ];
    }

    public function testCommandCanBeInstantiated(): void
    {
        $cmd = new IntegrityCheckCommand();
        $this->assertInstanceOf(IntegrityCheckCommand::class, $cmd);
    }
}
