<?php

namespace Pressmind\Tests\Unit\System;

use PHPUnit\Framework\TestCase;
use Pressmind\System\Requirements;

/**
 * Unit tests for Pressmind\System\Requirements.
 */
class RequirementsTest extends TestCase
{
    public function testGetRequirementsReturnsArray(): void
    {
        $req = new Requirements();
        $this->assertIsArray($req->getRequirements());
    }

    public function testGetRequirementsContainsExpectedKeys(): void
    {
        $req = new Requirements();
        $result = $req->getRequirements();
        $this->assertArrayHasKey('PHP', $result);
        $this->assertArrayHasKey('extensions', $result);
        $this->assertArrayHasKey('database', $result);
        $this->assertArrayHasKey('memory', $result);
    }

    public function testGetRequirementsExtensionsContainsExpectedExtensions(): void
    {
        $req = new Requirements();
        $extensions = $req->getRequirements()['extensions'];
        $expected = ['yaml', 'curl', 'bcmath', 'xml', 'PDO', 'imagick'];
        foreach ($expected as $ext) {
            $this->assertContains($ext, $extensions);
        }
    }
}
