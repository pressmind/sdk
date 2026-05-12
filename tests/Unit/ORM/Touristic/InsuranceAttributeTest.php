<?php

namespace Pressmind\Tests\Unit\ORM\Touristic;

use Pressmind\ORM\Object\Touristic\Insurance\Attribute;
use Pressmind\Tests\Unit\AbstractTestCase;

class InsuranceAttributeTest extends AbstractTestCase
{
    public function testFromStdClassImportsPriorityAsInteger(): void
    {
        $attribute = new Attribute(null, false);
        $data = (object) [
            'id' => 'attr-1',
            'name' => 'Coverage',
            'description' => 'Coverage description',
            'code' => 'COVERAGE',
            'code_ibe' => 'COV',
            'priority' => '7',
        ];

        $attribute->fromStdClass($data);

        $this->assertSame(7, $attribute->priority);
        $this->assertSame(7, $attribute->toStdClass(false)->priority);
    }

    /**
     * @dataProvider missingPriorityProvider
     */
    public function testFromStdClassDefaultsPriorityTo99WhenMissingEmptyOrNull(object $data): void
    {
        $attribute = new Attribute(null, false);

        $attribute->fromStdClass($data);

        $this->assertSame(99, $attribute->priority);
    }

    public function testFromStdClassKeepsZeroPriority(): void
    {
        $attribute = new Attribute(null, false);

        $attribute->fromStdClass((object) [
            'id' => 'attr-zero',
            'name' => 'Zero priority',
            'priority' => 0,
        ]);

        $this->assertSame(0, $attribute->priority);
    }

    public static function missingPriorityProvider(): array
    {
        return [
            'missing priority' => [(object) ['id' => 'attr-missing', 'name' => 'Missing priority']],
            'empty priority' => [(object) ['id' => 'attr-empty', 'name' => 'Empty priority', 'priority' => '']],
            'null priority' => [(object) ['id' => 'attr-null', 'name' => 'Null priority', 'priority' => null]],
        ];
    }
}
