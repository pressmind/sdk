<?php

namespace Pressmind\Tests\Integration;

/**
 * Placeholder integration test: ensures Integration suite runs and skips when no DB is configured.
 */
class IntegrationTest extends AbstractIntegrationTestCase
{
    public function testIntegrationSuiteLoads(): void
    {
        if ($this->db === null) {
            $this->markTestSkipped('DB_HOST/DB_NAME not set or connection failed');
        }
        $this->assertNotNull($this->db);
    }
}
