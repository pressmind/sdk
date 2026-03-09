<?php

namespace Pressmind\Tests\Unit\Search\MongoDB;

use Pressmind\Registry;
use Pressmind\Search\MongoDB\AbstractIndex;
use Pressmind\Tests\Unit\AbstractTestCase;

/**
 * Unit tests for Search\MongoDB\AbstractIndex: clearConnectionCache (static).
 * Other methods require real DB; full indexing is covered by Integration tests.
 */
class AbstractIndexTest extends AbstractTestCase
{
    public function testClearConnectionCache(): void
    {
        AbstractIndex::clearConnectionCache();
        $this->assertTrue(true, 'No exception');
    }
}
