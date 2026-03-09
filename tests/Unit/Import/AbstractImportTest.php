<?php

namespace Pressmind\Tests\Unit\Import;

use Exception;
use Pressmind\Import\AbstractImport;
use Pressmind\Tests\Unit\AbstractTestCase;

/**
 * Test AbstractImport via a concrete subclass that exposes protected methods.
 */
class AbstractImportTest extends AbstractTestCase
{
    public function testConstructorAndGetters(): void
    {
        $import = new class extends AbstractImport {
            public function runImport(): void
            {
                // no-op to satisfy interface
            }
        };
        $this->assertIsArray($import->getLog());
        $this->assertIsArray($import->getErrors());
        $this->assertCount(0, $import->getLog());
        $this->assertCount(0, $import->getErrors());
    }

    public function testGetLogReturnsLogAfterAdding(): void
    {
        $import = new class extends AbstractImport {
            public function runImport(): void
            {
                $this->_log[] = 'test entry';
            }
        };
        $import->runImport();
        $this->assertCount(1, $import->getLog());
        $this->assertSame('test entry', $import->getLog()[0]);
    }

    public function testGetErrorsReturnsErrorsAfterAdding(): void
    {
        $import = new class extends AbstractImport {
            public function runImport(): void
            {
                $this->_errors[] = 'test error';
            }
        };
        $import->runImport();
        $this->assertCount(1, $import->getErrors());
        $this->assertSame('test error', $import->getErrors()[0]);
    }

    public function testCheckApiResponseSuccess(): void
    {
        $import = new class extends AbstractImport {
            public function runImport(): void
            {
            }
            public function checkApiResponse($response): bool
            {
                return $this->_checkApiResponse($response);
            }
        };
        $response = (object) ['result' => [], 'error' => false];
        $this->assertTrue($import->checkApiResponse($response));
    }

    public function testCheckApiResponseThrowsOnError(): void
    {
        $import = new class extends AbstractImport {
            public function checkApiResponse($response): bool
            {
                return $this->_checkApiResponse($response);
            }
        };
        $response = (object) ['error' => true, 'msg' => 'API error'];
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('API error');
        $import->checkApiResponse($response);
    }

    public function testCheckApiResponseThrowsOnMalformed(): void
    {
        $import = new class extends AbstractImport {
            public function checkApiResponse($response): bool
            {
                return $this->_checkApiResponse($response);
            }
        };
        $response = (object) ['no_result' => true];
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('API response is not well formatted');
        $import->checkApiResponse($response);
    }

    public function testCheckApiResponseThrowsWhenNotStdClass(): void
    {
        $import = new class extends AbstractImport {
            public function checkApiResponse($response): bool
            {
                return $this->_checkApiResponse($response);
            }
        };
        $response = [];
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('API response is not well formatted');
        $import->checkApiResponse($response);
    }

    public function testGetElapsedTimeAndHeap(): void
    {
        $import = new class extends AbstractImport {
            public function runImport(): void
            {
            }
            public function getElapsedAndHeap(): string
            {
                return $this->_getElapsedTimeAndHeap();
            }
        };
        $text = $import->getElapsedAndHeap();
        $this->assertStringContainsString('sec', $text);
        $this->assertStringContainsString('Heap', $text);
        $this->assertStringContainsString('MByte', $text);
    }
}
