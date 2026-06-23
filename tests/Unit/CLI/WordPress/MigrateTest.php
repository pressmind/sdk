<?php

namespace Pressmind\CLI\WordPress {
    if (!function_exists(__NAMESPACE__ . '\\is_serialized')) {
        function is_serialized($data): bool
        {
            if (!is_string($data)) {
                return false;
            }

            $data = trim($data);
            if ($data === 'N;') {
                return true;
            }

            if (strlen($data) < 4 || $data[1] !== ':') {
                return false;
            }

            $lastChar = substr($data, -1);
            if ($lastChar !== ';' && $lastChar !== '}') {
                return false;
            }

            return @unserialize($data) !== false || $data === 'b:0;';
        }
    }
}

namespace Pressmind\Tests\Unit\CLI\WordPress {
    use PHPUnit\Framework\TestCase;
    use Pressmind\CLI\WordPress\Migrate;
    use ReflectionMethod;
    use ReflectionProperty;

    class MigrateTest extends TestCase
    {
        public function testReplacerPreservesNonStringValuesInSerializedArrays(): void
        {
            self::setStaticProperty('oldSite', 'https://old.example');
            self::setStaticProperty('newSite', 'https://new.example');

            $serialized = serialize([
                'url' => 'https://old.example/trip',
                'enabled' => true,
                'empty' => null,
                'count' => 3,
                'nested' => [
                    'asset' => 'https://old.example/image.jpg',
                    'visible' => false,
                ],
            ]);

            $result = unserialize(self::invokeReplacer($serialized));

            self::assertSame('https://new.example/trip', $result['url']);
            self::assertTrue($result['enabled']);
            self::assertNull($result['empty']);
            self::assertSame(3, $result['count']);
            self::assertSame('https://new.example/image.jpg', $result['nested']['asset']);
            self::assertFalse($result['nested']['visible']);
        }

        private static function invokeReplacer(string $value): string
        {
            $method = new ReflectionMethod(Migrate::class, 'replacer');
            $method->setAccessible(true);

            return $method->invoke(null, $value);
        }

        private static function setStaticProperty(string $name, string $value): void
        {
            $property = new ReflectionProperty(Migrate::class, $name);
            $property->setAccessible(true);
            $property->setValue(null, $value);
        }
    }
}
