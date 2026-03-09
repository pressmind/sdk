<?php

namespace Pressmind\Tests\Unit\CLI;

use PHPUnit\Framework\TestCase;
use Pressmind\CLI\RebuildRoutesCommand;

class RebuildRoutesCommandTest extends TestCase
{
    /**
     * Test _isChannelStrategy via reflection (private method, pure logic).
     *
     * @dataProvider channelStrategyProvider
     */
    public function testIsChannelStrategy(array $config, int $objectTypeId, bool $expected): void
    {
        $cmd = new RebuildRoutesCommand();
        $ref = new \ReflectionMethod($cmd, '_isChannelStrategy');
        $ref->setAccessible(true);
        $this->assertSame($expected, $ref->invoke($cmd, $config, $objectTypeId));
    }

    public static function channelStrategyProvider(): array
    {
        return [
            'empty config' => [
                ['data' => []],
                100,
                false,
            ],
            'no media_types_pretty_url key' => [
                [],
                100,
                false,
            ],
            'legacy format with channel strategy' => [
                ['data' => ['media_types_pretty_url' => [
                    100 => ['strategy' => 'channel'],
                    200 => ['strategy' => 'object'],
                ]]],
                100,
                true,
            ],
            'legacy format without channel strategy' => [
                ['data' => ['media_types_pretty_url' => [
                    100 => ['strategy' => 'object'],
                ]]],
                100,
                false,
            ],
            'legacy format missing type' => [
                ['data' => ['media_types_pretty_url' => [
                    200 => ['strategy' => 'channel'],
                ]]],
                100,
                false,
            ],
            'new format with channel strategy' => [
                ['data' => ['media_types_pretty_url' => [
                    ['id_object_type' => 100, 'strategy' => 'channel'],
                    ['id_object_type' => 200, 'strategy' => 'object'],
                ]]],
                100,
                true,
            ],
            'new format without channel strategy' => [
                ['data' => ['media_types_pretty_url' => [
                    ['id_object_type' => 100, 'strategy' => 'object'],
                ]]],
                100,
                false,
            ],
            'new format type not found' => [
                ['data' => ['media_types_pretty_url' => [
                    ['id_object_type' => 200, 'strategy' => 'channel'],
                ]]],
                100,
                false,
            ],
            'new format with string id_object_type' => [
                ['data' => ['media_types_pretty_url' => [
                    ['id_object_type' => '100', 'strategy' => 'channel'],
                ]]],
                100,
                true,
            ],
        ];
    }
}
