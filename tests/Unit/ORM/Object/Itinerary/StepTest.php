<?php

namespace Pressmind\Tests\Unit\ORM\Object\Itinerary;

use Pressmind\ORM\Object\Itinerary\Step;
use Pressmind\ORM\Object\Itinerary\Step\Board;
use Pressmind\ORM\Object\Itinerary\Step\Section;
use Pressmind\Registry;
use Pressmind\Tests\Unit\AbstractTestCase;
use stdClass;

class StepTest extends AbstractTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Registry::clear();
        $config = $this->createMockConfig([
            'data' => ['languages' => ['default' => 'de']],
        ]);
        $db = $this->createMockDb();
        $registry = Registry::getInstance();
        $registry->add('config', $config);
        $registry->add('db', $db);
    }

    public function testGetSectionForLanguageReturnsMatchingSection(): void
    {
        $sectionDe = $this->createSection('de', 'German content');
        $sectionEn = $this->createSection('en', 'English content');

        $step = new Step();
        $step->sections = [$sectionDe, $sectionEn];

        $result = $step->getSectionForLanguage('en');
        $this->assertSame('en', $result->language);
    }

    public function testGetSectionForLanguageUsesConfigDefaultWhenNull(): void
    {
        $sectionDe = $this->createSection('de', 'German content');
        $sectionEn = $this->createSection('en', 'English content');

        $step = new Step();
        $step->sections = [$sectionDe, $sectionEn];

        $result = $step->getSectionForLanguage(null);
        $this->assertSame('de', $result->language);
    }

    public function testGetContentForLanguageReturnsContentOfMatchedSection(): void
    {
        $sectionDe = $this->createSection('de', 'Deutscher Inhalt');

        $step = new Step();
        $step->sections = [$sectionDe];

        $result = $step->getContentForlanguage('de');
        $this->assertSame('Deutscher Inhalt', $result);
    }

    public function testBoardDefinesDistanceField(): void
    {
        $board = new Board();

        $this->assertTrue($board->hasProperty('distance'));
        $this->assertSame('string', $board->getPropertyDefinition('distance')['type']);
        $this->assertContains('distance', $board->getPropertyNames());
    }

    public function testSectionDefinesTagsField(): void
    {
        $section = new Section();

        $this->assertTrue($section->hasProperty('tags'));
        $this->assertSame('string', $section->getPropertyDefinition('tags')['type']);
        $this->assertContains('tags', $section->getPropertyNames());
    }

    private function createSection(string $language, string $content): stdClass
    {
        $section = new stdClass();
        $section->language = $language;
        $section->content = $content;
        return $section;
    }
}
