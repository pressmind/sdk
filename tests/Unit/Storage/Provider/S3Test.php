<?php

namespace Pressmind\Tests\Unit\Storage\Provider;

use Aws\MockHandler;
use Aws\Result;
use Aws\S3\S3Client;
use Pressmind\Registry;
use Pressmind\Storage\Bucket;
use Pressmind\Storage\File;
use Pressmind\Storage\Provider\S3;
use Pressmind\Tests\Unit\AbstractTestCase;

/**
 * Unit tests for Pressmind\Storage\Provider\S3.
 * Uses AWS SDK MockHandler when SDK is available; skips when not installed.
 */
class S3Test extends AbstractTestCase
{
    /** @var S3 */
    private $provider;

    /** @var Bucket */
    private $bucket;

    /** @var File */
    private $file;

    protected function setUp(): void
    {
        parent::setUp();
        if (!class_exists(S3Client::class)) {
            $this->markTestSkipped('AWS SDK S3Client not available');
        }
        $config = $this->createMockConfig([]);
        Registry::getInstance()->add('config', $config);
        $storage = [
            'provider' => 's3',
            'version' => 'latest',
            'region' => 'eu-central-1',
            'credentials' => ['key' => 'test', 'secret' => 'test'],
        ];
        $this->provider = new S3($storage);
        $this->bucket = new Bucket([
            'bucket' => 'test-bucket',
            'provider' => 's3',
        ]);
        $this->file = new File($this->bucket);
        $this->file->name = 'test-key.txt';
        $this->file->content = 'body';
    }

    private function createClientWithMockResults(array $results): S3Client
    {
        $handler = new MockHandler();
        foreach ($results as $result) {
            $handler->append($result instanceof Result ? $result : new Result($result));
        }
        return new S3Client([
            'region' => 'eu-central-1',
            'version' => 'latest',
            'credentials' => ['key' => 'test', 'secret' => 'test'],
            'handler' => $handler,
        ]);
    }

    public function testSetFileModeIsNoOp(): void
    {
        $this->provider->setS3Client($this->createClientWithMockResults([]));
        $this->provider->setFileMode($this->file, $this->bucket);
        $this->addToAssertionCount(1);
    }

    public function testSaveCallsPutObject(): void
    {
        $this->provider->setS3Client($this->createClientWithMockResults([new Result([])]));
        $this->provider->save($this->file, $this->bucket);
        $this->addToAssertionCount(1);
    }

    public function testDeleteCallsDeleteObject(): void
    {
        $this->provider->setS3Client($this->createClientWithMockResults([new Result([])]));
        $this->assertTrue($this->provider->delete($this->file, $this->bucket));
    }

    public function testFileExistsCallsDoesObjectExistV2(): void
    {
        $this->provider->setS3Client($this->createClientWithMockResults([new Result([])]));
        $this->assertTrue($this->provider->fileExists($this->file, $this->bucket));
    }

    public function testReadFileCallsGetObject(): void
    {
        $this->provider->setS3Client($this->createClientWithMockResults([['Body' => 'file content']]));
        $result = $this->provider->readFile($this->file, $this->bucket);
        $this->assertSame($this->file, $result);
        $this->assertSame('file content', $this->file->content);
    }

    public function testFilesizeCallsHeadObject(): void
    {
        $this->provider->setS3Client($this->createClientWithMockResults([['ContentLength' => 99]]));
        $this->assertSame(99, $this->provider->filesize($this->file, $this->bucket));
    }

    public function testListBucketCallsListObjects(): void
    {
        $this->provider->setS3Client($this->createClientWithMockResults([
            ['Contents' => [['Key' => 'a.txt'], ['Key' => 'b.txt']]],
        ]));
        $list = $this->provider->listBucket($this->bucket);
        $this->assertCount(2, $list);
        $this->assertSame('a.txt', $list[0]->name);
        $this->assertSame('b.txt', $list[1]->name);
    }

    public function testListByPrefixCallsListObjectsV2(): void
    {
        $this->provider->setS3Client($this->createClientWithMockResults([
            [
                'Contents' => [
                    ['Key' => 'pre_one', 'Size' => 10],
                    ['Key' => 'pre_two', 'Size' => 20],
                ],
            ],
        ]));
        $result = $this->provider->listByPrefix('pre_', $this->bucket);
        $this->assertSame(['pre_one' => 10, 'pre_two' => 20], $result);
    }

    public function testScanAllKeysCallsListObjectsV2(): void
    {
        $this->provider->setS3Client($this->createClientWithMockResults([
            ['Contents' => [['Key' => 'k1', 'Size' => 1]]],
        ]));
        $keys = [];
        $this->provider->scanAllKeys(function ($key, $size) use (&$keys) {
            $keys[$key] = $size;
        }, $this->bucket);
        $this->assertSame(['k1' => 1], $keys);
    }

    public function testDeleteAllEmptyBucketReturnsTrue(): void
    {
        $this->provider->setS3Client($this->createClientWithMockResults([['Contents' => []]]));
        $this->assertTrue($this->provider->deleteAll($this->bucket));
    }

    public function testDeleteAllWithContentsCallsDeleteObjects(): void
    {
        $this->provider->setS3Client($this->createClientWithMockResults([
            ['Contents' => [['Key' => 'x'], ['Key' => 'y']]],
            new Result([]),
        ]));
        $this->assertTrue($this->provider->deleteAll($this->bucket));
    }
}
