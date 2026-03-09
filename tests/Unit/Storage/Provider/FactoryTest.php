<?php

namespace Pressmind\Tests\Unit\Storage\Provider;

use Pressmind\Registry;
use Pressmind\Storage\Provider\Factory;
use Pressmind\Storage\Provider\Filesystem;
use Pressmind\Storage\Provider\S3;
use Pressmind\Storage\ProviderInterface;
use Pressmind\Tests\Unit\AbstractTestCase;

/**
 * Unit tests for Pressmind\Storage\Provider\Factory.
 */
class FactoryTest extends AbstractTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $config = $this->createMockConfig([]);
        Registry::getInstance()->add('config', $config);
    }

    public function testCreateFilesystemReturnsProvider(): void
    {
        $storage = [
            'bucket' => sys_get_temp_dir(),
            'provider' => 'filesystem',
        ];
        $provider = Factory::create($storage);
        $this->assertInstanceOf(ProviderInterface::class, $provider);
        $this->assertInstanceOf(Filesystem::class, $provider);
    }

    public function testCreateS3ReturnsProviderWhenSdkAvailable(): void
    {
        if (!class_exists(\Aws\S3\S3Client::class)) {
            $this->markTestSkipped('AWS SDK S3Client not available');
        }
        $storage = [
            'provider' => 's3',
            'version' => 'latest',
            'region' => 'eu-central-1',
            'credentials' => ['key' => 'k', 'secret' => 's'],
        ];
        $provider = Factory::create($storage);
        $this->assertInstanceOf(ProviderInterface::class, $provider);
        $this->assertInstanceOf(S3::class, $provider);
    }

    public function testCreateInvalidProviderThrows(): void
    {
        $this->expectException(\Throwable::class);
        Factory::create(['provider' => 'nonexistent']);
    }
}
