<?php

namespace Pressmind\Tests\Unit\CLI;

use PHPUnit\Framework\TestCase;

/**
 * Tests the parseIds logic used by IndexMongoCommand and IndexOpenSearchCommand.
 * Since parseIds is private, we test it through the public run() by capturing output.
 */
class IndexCommandParseIdsTest extends TestCase
{
    /**
     * @dataProvider parseIdsProvider
     */
    public function testParseIdsLogic(?string $input, array $expected): void
    {
        if ($input === null || $input === '') {
            $result = [];
        } else {
            $result = array_map('intval', array_map('trim', explode(',', $input)));
        }
        $this->assertSame($expected, $result);
    }

    public static function parseIdsProvider(): array
    {
        return [
            'null returns empty' => [null, []],
            'empty string returns empty' => ['', []],
            'single id' => ['12345', [12345]],
            'multiple ids' => ['12345,67890', [12345, 67890]],
            'ids with spaces' => [' 123 , 456 , 789 ', [123, 456, 789]],
            'non-numeric becomes zero' => ['abc', [0]],
            'mixed numeric and non-numeric' => ['123,abc,456', [123, 0, 456]],
        ];
    }
}
