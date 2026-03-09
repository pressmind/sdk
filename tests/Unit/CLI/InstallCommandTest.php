<?php

namespace Pressmind\Tests\Unit\CLI;

use PHPUnit\Framework\TestCase;
use Pressmind\CLI\InstallCommand;

class InstallCommandTest extends TestCase
{
    public function testVarExportEmptyArray(): void
    {
        $result = InstallCommand::varExport([]);
        $this->assertIsString($result);
        $this->assertStringContainsString('[', $result);
    }

    public function testVarExportSimpleArray(): void
    {
        $result = InstallCommand::varExport(['key' => 'value']);
        $this->assertStringContainsString("'key'", $result);
        $this->assertStringContainsString("'value'", $result);
        $this->assertStringNotContainsString('array (', $result);
    }

    public function testVarExportNestedArray(): void
    {
        $result = InstallCommand::varExport([
            'database' => [
                'host' => 'localhost',
                'port' => 3306,
            ],
        ]);
        $this->assertStringContainsString("'database'", $result);
        $this->assertStringContainsString("'host'", $result);
        $this->assertStringContainsString("'localhost'", $result);
        $this->assertStringContainsString('3306', $result);
        $this->assertStringNotContainsString('array (', $result);
    }

    public function testVarExportOutputUsesShortArraySyntax(): void
    {
        $result = InstallCommand::varExport(['a', 'b', 'c']);
        $this->assertStringNotContainsString('array (', $result);
        $this->assertStringContainsString('[', $result);
    }

    public function testVarExportWithBooleanValues(): void
    {
        $result = InstallCommand::varExport(['enabled' => true, 'debug' => false]);
        $this->assertStringContainsString('true', $result);
        $this->assertStringContainsString('false', $result);
    }

    public function testVarExportWithNullValue(): void
    {
        $result = InstallCommand::varExport(['value' => null]);
        $this->assertStringContainsString('NULL', $result);
    }
}
