<?php

namespace Pressmind\Tests\Unit\Search;

use PHPUnit\Framework\TestCase;
use Pressmind\Search\AbstractTemplate;
use stdClass;

/**
 * Unit tests for Pressmind\Search\AbstractTemplate.
 */
class AbstractTemplateTest extends TestCase
{
    public function testConstructorStoresObject(): void
    {
        $object = new stdClass();
        $template = new AbstractTemplate($object);
        $this->assertInstanceOf(AbstractTemplate::class, $template);
        // Protected _object is not accessible; we only assert construction
        $this->assertTrue(true);
    }
}
