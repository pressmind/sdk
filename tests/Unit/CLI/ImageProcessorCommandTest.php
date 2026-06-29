<?php

namespace Pressmind\Tests\Unit\CLI;

use PHPUnit\Framework\TestCase;
use Pressmind\CLI\ImageProcessorCommand;

class ImageProcessorCommandTest extends TestCase
{
    /**
     * @dataProvider formatBytesProvider
     */
    public function testFormatBytes(int $bytes, int $precision, string $expected): void
    {
        $this->assertSame($expected, ImageProcessorCommand::formatBytes($bytes, $precision));
    }

    public static function formatBytesProvider(): array
    {
        return [
            'zero bytes' => [0, 2, '0 B'],
            '500 bytes' => [500, 2, '500 B'],
            '1 KB' => [1024, 2, '1 KB'],
            '1.5 KB' => [1536, 2, '1.5 KB'],
            '1 MB' => [1048576, 2, '1 MB'],
            '1 GB' => [1073741824, 2, '1 GB'],
            'precision 0' => [1536, 0, '2 KB'],
            'precision 1' => [1536, 1, '1.5 KB'],
        ];
    }
}
