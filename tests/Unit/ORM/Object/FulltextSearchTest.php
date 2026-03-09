<?php

namespace Pressmind\Tests\Unit\ORM\Object;

use Pressmind\DB\Adapter\AdapterInterface;
use Pressmind\ORM\Object\FulltextSearch;
use Pressmind\Registry;
use Pressmind\Tests\Unit\AbstractTestCase;
use stdClass;

class FulltextSearchTest extends AbstractTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Registry::clear();
        $config = $this->createMockConfig([
            'data' => ['media_types_fulltext_index_fields' => []],
        ]);
        $db = $this->createMockDb();
        $registry = Registry::getInstance();
        $registry->add('config', $config);
        $registry->add('db', $db);
    }

    public function testReplaceCharsReplacesGermanUmlauts(): void
    {
        $this->assertSame(
            'ae oe ue Ae Oe Ue ss',
            FulltextSearch::replaceChars('ä ö ü Ä Ö Ü ß')
        );
    }

    public function testReplaceCharsReturnsUnchangedWithoutSpecialChars(): void
    {
        $input = 'Hello World 123';
        $this->assertSame($input, FulltextSearch::replaceChars($input));
    }

    public function testReplaceCharsHandlesMixedContent(): void
    {
        $this->assertSame(
            'Schoene Gruesse aus Muenchen',
            FulltextSearch::replaceChars('Schöne Grüße aus München')
        );
    }

    public function testGetFullTextWordsReturnsEmptyStringWhenDbReturnsEmpty(): void
    {
        $result = FulltextSearch::getFullTextWords(999, 1);
        $this->assertSame('', $result);
    }

    public function testGetFullTextWordsJoinsValuesWithSpace(): void
    {
        $row1 = new stdClass();
        $row1->fulltext_values = 'alpha';
        $row2 = new stdClass();
        $row2->fulltext_values = 'beta';

        $db = $this->createMock(AdapterInterface::class);
        $db->method('fetchAll')->willReturn([$row1, $row2]);
        $db->method('getTablePrefix')->willReturn('pmt2core_');

        Registry::clear();
        $config = $this->createMockConfig([
            'data' => ['media_types_fulltext_index_fields' => []],
        ]);
        $registry = Registry::getInstance();
        $registry->add('config', $config);
        $registry->add('db', $db);

        $result = FulltextSearch::getFullTextWords(1, 1);
        $this->assertSame('alpha beta', $result);
    }

    public function testGetFullTextWordsAddsLanguageParamWhenProvided(): void
    {
        $db = $this->createMock(AdapterInterface::class);
        $db->expects($this->once())
            ->method('fetchAll')
            ->with(
                $this->stringContains('AND language = ?'),
                $this->callback(function ($params) {
                    return count($params) === 3 && $params[2] === 'de';
                })
            )
            ->willReturn([]);
        $db->method('getTablePrefix')->willReturn('pmt2core_');

        Registry::clear();
        $config = $this->createMockConfig([
            'data' => ['media_types_fulltext_index_fields' => []],
        ]);
        $registry = Registry::getInstance();
        $registry->add('config', $config);
        $registry->add('db', $db);

        FulltextSearch::getFullTextWords(1, 1, 'de');
    }
}
