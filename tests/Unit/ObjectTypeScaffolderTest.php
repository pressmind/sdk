<?php

namespace Pressmind\Tests\Unit;

use Pressmind\ObjectTypeScaffolder;

class ObjectTypeScaffolderTest extends AbstractTestCase
{
    public function testIconFieldsAreScaffoldedAsRelations(): void
    {
        $scaffolder = new ObjectTypeScaffolder((object) ['name' => 'Test', 'fields' => []], 'test');
        $property = new \ReflectionProperty($scaffolder, '_mysql_type_map');
        $property->setAccessible(true);
        $map = $property->getValue($scaffolder);

        $this->assertSame('relation', $map['icon']);
    }
}
