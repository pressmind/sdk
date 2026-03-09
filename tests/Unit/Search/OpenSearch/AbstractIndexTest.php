<?php

namespace Pressmind\Tests\Unit\Search\OpenSearch;

use Pressmind\Search\OpenSearch\AbstractIndex;
use Pressmind\Tests\Unit\AbstractTestCase;

/**
 * Unit tests for Search\OpenSearch\AbstractIndex: pure logic (htmlToFulltext, getIndexTemplateName,
 * getAnalyzerNameForLanguage, getStringWithLanguageSuffix, getDefaultFilterForLanguage,
 * getDefaultAnalyzerForLanguage, getConfigHash). No real OpenSearch client.
 */
class AbstractIndexTest extends AbstractTestCase
{
    private function createAbstractIndexStub(): AbstractIndex
    {
        $stub = $this->getMockBuilder(AbstractIndex::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();

        $ref = new \ReflectionClass(AbstractIndex::class);
        $config = [
            'uri' => 'https://localhost:9200',
            'index' => [
                'fulltext' => [
                    'object_type_mapping' => [
                        1 => [
                            ['language' => 'de'],
                            ['language' => 'en'],
                        ],
                    ],
                ],
            ],
        ];
        $configProp = $ref->getProperty('_config');
        $configProp->setAccessible(true);
        $configProp->setValue($stub, $config);

        $languagesProp = $ref->getProperty('_languages');
        $languagesProp->setAccessible(true);
        $languagesProp->setValue($stub, ['de', 'en']);

        return $stub;
    }

    public function testHtmlToFulltext(): void
    {
        $index = $this->createAbstractIndexStub();
        $html = '<p>Hello</p><br/><div>World</div>';
        $text = $index->htmlToFulltext($html);
        $this->assertSame('Hello World', $text);
    }

    public function testHtmlToFulltextStripsTagsAndNormalizesWhitespace(): void
    {
        $index = $this->createAbstractIndexStub();
        $html = "<p>  A  </p>\n<div>  B  </div>";
        $text = $index->htmlToFulltext($html);
        $this->assertSame('A B', $text);
    }

    public function testGetIndexTemplateNameWithLanguage(): void
    {
        $index = $this->createAbstractIndexStub();
        $name = $index->getIndexTemplateName('de');
        $this->assertStringStartsWith('index_', $name);
        $this->assertStringEndsWith('_de', $name);
    }

    public function testGetIndexTemplateNameEmptyLanguage(): void
    {
        $index = $this->createAbstractIndexStub();
        $name = $index->getIndexTemplateName(null);
        $this->assertStringStartsWith('index_', $name);
    }

    public function testGetAnalyzerNameForLanguageDe(): void
    {
        $index = $this->createAbstractIndexStub();
        $this->assertSame('german_default', $index->getAnalyzerNameForLanguage('de'));
    }

    public function testGetAnalyzerNameForLanguageEn(): void
    {
        $index = $this->createAbstractIndexStub();
        $this->assertSame('english_default', $index->getAnalyzerNameForLanguage('en'));
    }

    public function testGetAnalyzerNameForLanguageUnknownFallsBackToGerman(): void
    {
        $index = $this->createAbstractIndexStub();
        $this->assertSame('german_default', $index->getAnalyzerNameForLanguage('fr'));
    }

    public function testGetStringWithLanguageSuffix(): void
    {
        $index = $this->createAbstractIndexStub();
        $this->assertSame('autocomplete_de', $index->getStringWithLanguageSuffix('autocomplete', 'de'));
        $this->assertSame('autocomplete_en', $index->getStringWithLanguageSuffix('autocomplete', 'en'));
    }

    public function testGetStringWithLanguageSuffixEmptyLanguageUsesDe(): void
    {
        $index = $this->createAbstractIndexStub();
        $this->assertSame('field_de', $index->getStringWithLanguageSuffix('field', null));
    }

    public function testGetStringWithLanguageSuffixAlreadyHasSuffixReturnsUnchanged(): void
    {
        $index = $this->createAbstractIndexStub();
        $this->assertSame('autocomplete_de', $index->getStringWithLanguageSuffix('autocomplete_de', 'de'));
    }

    public function testGetDefaultFilterForLanguageUnknownFallsBackToDe(): void
    {
        $index = $this->createAbstractIndexStub();
        $filter = $index->getDefaultFilterForLanguage('fr');
        $this->assertArrayHasKey('german_stemmer', $filter);
    }

    public function testGetDefaultAnalyzerForLanguageUnknownFallsBackToDe(): void
    {
        $index = $this->createAbstractIndexStub();
        $analyzer = $index->getDefaultAnalyzerForLanguage('xx');
        $this->assertArrayHasKey('german_default', $analyzer);
    }

    public function testGetDefaultFilterForLanguageDe(): void
    {
        $index = $this->createAbstractIndexStub();
        $filter = $index->getDefaultFilterForLanguage('de');
        $this->assertArrayHasKey('german_stemmer', $filter);
        $this->assertArrayHasKey('german_stop', $filter);
    }

    public function testGetDefaultFilterForLanguageEn(): void
    {
        $index = $this->createAbstractIndexStub();
        $filter = $index->getDefaultFilterForLanguage('en');
        $this->assertArrayHasKey('english_stemmer', $filter);
        $this->assertArrayHasKey('english_stop', $filter);
    }

    public function testGetDefaultAnalyzerForLanguageDe(): void
    {
        $index = $this->createAbstractIndexStub();
        $analyzer = $index->getDefaultAnalyzerForLanguage('de');
        $this->assertArrayHasKey('german_default', $analyzer);
        $this->assertArrayHasKey('autocomplete_de', $analyzer);
    }

    public function testGetConfigHash(): void
    {
        $index = $this->createAbstractIndexStub();
        $hash = $index->getConfigHash();
        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $hash);
    }

    public function testGetLanguages(): void
    {
        $index = $this->createAbstractIndexStub();
        $languages = $index->getLanguages();
        $this->assertContains('de', $languages);
        $this->assertContains('en', $languages);
    }

    public function testGetAllRequiredObjectTypes(): void
    {
        $index = $this->createAbstractIndexStub();
        $types = $index->getAllRequiredObjectTypes();
        $this->assertContains(1, $types);
    }
}
