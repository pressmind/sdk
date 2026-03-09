<?php

namespace Pressmind\Tests\Unit\Import\Mapper;

use PHPUnit\Framework\TestCase;
use Pressmind\Import\Mapper\Factory;

class FactoryMapperTest extends TestCase
{
    public function testCreateReturnsMapperWhenFileExists(): void
    {
        $mapper = Factory::create('File');
        $this->assertNotNull($mapper);
        $this->assertInstanceOf(\Pressmind\Import\Mapper\File::class, $mapper);
    }

    public function testCreateReturnsFalseWhenFileDoesNotExist(): void
    {
        $result = Factory::create('NonExistentMapper');
        $this->assertFalse($result);
    }
}
