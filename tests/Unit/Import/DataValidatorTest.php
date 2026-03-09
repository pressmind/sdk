<?php

namespace Pressmind\Tests\Unit\Import;

use PHPUnit\Framework\TestCase;
use Pressmind\Import\DataValidator;

class DataValidatorTest extends TestCase
{
    public function testValidateUniqueIdsPassesWithUniqueIds(): void
    {
        $data = [
            (object) ['id' => 1, 'name' => 'A'],
            (object) ['id' => 2, 'name' => 'B'],
        ];
        DataValidator::validateUniqueIds($data, 'options');
        $this->addToAssertionCount(1);
    }

    public function testValidateUniqueIdsPassesWithEmptyArray(): void
    {
        DataValidator::validateUniqueIds([], 'options');
        $this->addToAssertionCount(1);
    }

    public function testValidateUniqueIdsPassesWithNoIds(): void
    {
        $data = [(object) ['name' => 'A'], (object) ['name' => 'B']];
        DataValidator::validateUniqueIds($data, 'items');
        $this->addToAssertionCount(1);
    }

    public function testValidateUniqueIdsThrowsOnDuplicateIds(): void
    {
        $data = [
            (object) ['id' => 1, 'name' => 'A'],
            (object) ['id' => 1, 'name' => 'B'],
        ];
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Duplicate IDs detected');
        $this->expectExceptionMessage('"1":2');
        DataValidator::validateUniqueIds($data, 'options');
    }

    public function testValidateUniqueIdsRespectsExcludePaths(): void
    {
        $data = [
            (object) [
                'id' => 1,
                'price_tables' => [
                    (object) ['id' => 10],
                    (object) ['id' => 10],
                ],
            ],
        ];
        DataValidator::validateUniqueIds($data, 'insurances', ['price_tables']);
        $this->addToAssertionCount(1);
    }

    public function testValidateUniqueIdsThrowsOnDuplicateInNestedCollection(): void
    {
        $data = [
            (object) [
                'id' => 1,
                'options' => [
                    (object) ['id' => 10],
                    (object) ['id' => 10],
                ],
            ],
        ];
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Duplicate IDs');
        $this->expectExceptionMessage('options');
        DataValidator::validateUniqueIds($data, 'mediaObject');
    }

    public function testValidateUniqueIdsAcceptsObjectRoot(): void
    {
        $data = (object) [
            'startingPoints' => [
                (object) ['id' => 1],
                (object) ['id' => 2],
            ],
        ];
        DataValidator::validateUniqueIds($data, 'root');
        $this->addToAssertionCount(1);
    }

    public function testValidateUniqueIdsPassesWhenFirstElementNotObject(): void
    {
        $data = [1, 2, 3];
        DataValidator::validateUniqueIds($data, 'scalars');
        $this->addToAssertionCount(1);
    }

    public function testValidateUniqueIdsRecursesIntoNestedObject(): void
    {
        $data = (object) [
            'inner' => (object) [
                'items' => [
                    (object) ['id' => 1],
                    (object) ['id' => 2],
                ],
            ],
        ];
        DataValidator::validateUniqueIds($data, 'root');
        $this->addToAssertionCount(1);
    }

    /**
     * Scalar data is ignored (no validation); ensures no throw.
     * @param mixed $data
     */
    public function testValidateUniqueIdsWithScalarDoesNotThrow(): void
    {
        /** @phpstan-ignore argument.type (intentional: branch where data is neither array nor object) */
        DataValidator::validateUniqueIds('scalar', 'ctx');
        /** @phpstan-ignore argument.type (intentional: branch where data is neither array nor object) */
        DataValidator::validateUniqueIds(123, 'ctx');
        $this->addToAssertionCount(1);
    }

    public function testValidateUniqueIdsExceptionMessageContainsContextAndPath(): void
    {
        $data = [(object) ['id' => 5], (object) ['id' => 5]];
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Context: myContext');
        $this->expectExceptionMessage('Duplicate IDs');
        DataValidator::validateUniqueIds($data, 'myContext');
    }

    public function testValidateUniqueIdsExcludePathSkipsNestedCollection(): void
    {
        $data = [(object) ['id' => 1, 'price_tables' => [(object) ['id' => 10], (object) ['id' => 10]]]];
        DataValidator::validateUniqueIds($data, 'x', ['price_tables']);
        $this->addToAssertionCount(1);
    }

    public function testValidateUniqueIdsGatherChildCollectionsWithNonObjectItemsSkipped(): void
    {
        $data = [(object) ['children' => [(object) ['id' => 1], (object) ['id' => 2]]]];
        DataValidator::validateUniqueIds($data, 'parent');
        $this->addToAssertionCount(1);
    }

    public function testValidateUniqueIdsThrowsWithEmptyContext(): void
    {
        $data = [(object) ['id' => 7], (object) ['id' => 7]];
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Duplicate IDs detected');
        DataValidator::validateUniqueIds($data, '');
    }

    public function testValidateUniqueIdsChildArrayWithNonObjectEntriesSkipped(): void
    {
        $data = [
            (object) ['id' => 1, 'tags' => ['foo', 'bar']],
            (object) ['id' => 2, 'tags' => ['baz']],
        ];
        DataValidator::validateUniqueIds($data, 'items');
        $this->addToAssertionCount(1);
    }

    public function testValidateUniqueIdsGatherChildSkipsMissingProperty(): void
    {
        $a = new \stdClass();
        $a->id = 1;
        $a->children = [(object) ['id' => 10]];

        $b = new \stdClass();
        $b->id = 2;

        DataValidator::validateUniqueIds([$a, $b], 'mixed');
        $this->addToAssertionCount(1);
    }

    public function testValidateUniqueIdsGatherChildSkipsNonObjectInArray(): void
    {
        $data = [
            (object) ['id' => 1, 'items' => [(object) ['id' => 10], 'not-an-object']],
            (object) ['id' => 2, 'items' => [(object) ['id' => 11]]],
        ];
        DataValidator::validateUniqueIds($data, 'ctx');
        $this->addToAssertionCount(1);
    }

    public function testValidateUniqueIdsDeepNestingThreeLevels(): void
    {
        $data = [
            (object) [
                'id' => 1,
                'groups' => [
                    (object) [
                        'id' => 100,
                        'options' => [
                            (object) ['id' => 1000],
                            (object) ['id' => 1001],
                        ],
                    ],
                ],
            ],
        ];
        DataValidator::validateUniqueIds($data, 'deep');
        $this->addToAssertionCount(1);
    }

    public function testValidateUniqueIdsDeepNestingThrowsAtThirdLevel(): void
    {
        $data = [
            (object) [
                'id' => 1,
                'groups' => [
                    (object) [
                        'id' => 100,
                        'options' => [
                            (object) ['id' => 500],
                            (object) ['id' => 500],
                        ],
                    ],
                ],
            ],
        ];
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('groups.options');
        DataValidator::validateUniqueIds($data, 'deep');
    }

    public function testValidateUniqueIdsExcludePathWithDotNotation(): void
    {
        $data = [
            (object) [
                'id' => 1,
                'groups' => [
                    (object) [
                        'id' => 100,
                        'options' => [
                            (object) ['id' => 500],
                            (object) ['id' => 500],
                        ],
                    ],
                ],
            ],
        ];
        DataValidator::validateUniqueIds($data, 'deep', ['groups.options']);
        $this->addToAssertionCount(1);
    }

    public function testValidateUniqueIdsMultipleDuplicatesInSameArray(): void
    {
        $data = [
            (object) ['id' => 1],
            (object) ['id' => 1],
            (object) ['id' => 1],
            (object) ['id' => 2],
            (object) ['id' => 2],
        ];
        try {
            DataValidator::validateUniqueIds($data, 'multi');
            $this->fail('Expected exception not thrown');
        } catch (\Exception $e) {
            $this->assertStringContainsString('"1":3', $e->getMessage());
            $this->assertStringContainsString('"2":2', $e->getMessage());
        }
    }

    public function testValidateUniqueIdsObjectRootWithDuplicatesInNestedArray(): void
    {
        $data = (object) [
            'items' => [
                (object) ['id' => 10],
                (object) ['id' => 10],
            ],
        ];
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('items');
        DataValidator::validateUniqueIds($data, 'objectRoot');
    }

    public function testValidateUniqueIdsObjectRootWithNestedObjectProperty(): void
    {
        $data = (object) [
            'wrapper' => (object) [
                'children' => [
                    (object) ['id' => 1],
                    (object) ['id' => 1],
                ],
            ],
        ];
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('wrapper.children');
        DataValidator::validateUniqueIds($data, 'nested');
    }

    public function testValidateUniqueIdsCrossParentDuplicateDetection(): void
    {
        $data = [
            (object) [
                'id' => 1,
                'options' => [
                    (object) ['id' => 100],
                ],
            ],
            (object) [
                'id' => 2,
                'options' => [
                    (object) ['id' => 100],
                ],
            ],
        ];
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('options');
        DataValidator::validateUniqueIds($data, 'crossParent');
    }

    public function testValidateUniqueIdsExceptionMessageOmitsEmptyPath(): void
    {
        $data = [(object) ['id' => 3], (object) ['id' => 3]];
        try {
            DataValidator::validateUniqueIds($data, 'ctx');
            $this->fail('Expected exception not thrown');
        } catch (\Exception $e) {
            $this->assertStringContainsString('Context: ctx', $e->getMessage());
            $this->assertStringNotContainsString('Path:', $e->getMessage());
        }
    }

    public function testValidateUniqueIdsExcludePathDoesNotAffectOtherPaths(): void
    {
        $data = [
            (object) [
                'id' => 1,
                'safe' => [(object) ['id' => 50], (object) ['id' => 50]],
                'other' => [(object) ['id' => 60], (object) ['id' => 60]],
            ],
        ];
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('other');
        DataValidator::validateUniqueIds($data, 'partial-exclude', ['safe']);
    }
}
