<?php

namespace Pressmind\Tests\Unit\ORM\Touristic;

use Pressmind\ORM\Object\Touristic\Startingpoint;
use Pressmind\ORM\Object\Touristic\Startingpoint\Option;
use Pressmind\Tests\Unit\AbstractTestCase;

class StartingpointQueryTest extends AbstractTestCase
{
    private function createStartingpoint(array $props = []): Startingpoint
    {
        $sp = new Startingpoint();
        $defaults = [
            'id' => 'sp-1',
            'name' => 'Berlin ZOB',
            'options' => [],
        ];
        foreach (array_merge($defaults, $props) as $k => $v) {
            $sp->$k = $v;
        }
        return $sp;
    }

    private function createOption(array $props = []): Option
    {
        $opt = new Option();
        $defaults = [
            'id' => 'opt-' . uniqid(),
            'id_startingpoint' => 'sp-1',
            'entry' => false,
            'exit' => false,
            'price' => 0.0,
        ];
        foreach (array_merge($defaults, $props) as $k => $v) {
            $opt->$k = $v;
        }
        return $opt;
    }

    // --- validate() ---

    public function testValidateReturnsErrorWhenOptionsAreEmpty(): void
    {
        $sp = $this->createStartingpoint(['options' => []]);
        $result = $sp->validate();

        $this->assertNotEmpty($result);
        $hasNoOptionsError = false;
        foreach ($result as $msg) {
            if (str_contains($msg, 'no options')) {
                $hasNoOptionsError = true;
            }
        }
        $this->assertTrue($hasNoOptionsError, 'Expected error about empty options');
    }

    public function testValidateReturnsEmptyWhenValidEntryOptionsExist(): void
    {
        $opt = $this->createOption(['entry' => true, 'exit' => false]);
        $sp = $this->createStartingpoint(['options' => [$opt]]);

        $result = $sp->validate('', 1);
        $this->assertEmpty($result, 'Expected no validation errors for valid entry options');
    }

    public function testValidateReturnsErrorWhenNoValidOptionsForExit(): void
    {
        $opt = $this->createOption(['entry' => true, 'exit' => false]);
        $sp = $this->createStartingpoint(['options' => [$opt]]);

        $result = $sp->validate('', 2);

        $this->assertNotEmpty($result);
        $hasExitError = false;
        foreach ($result as $msg) {
            if (str_contains($msg, 'for exit')) {
                $hasExitError = true;
            }
        }
        $this->assertTrue($hasExitError, 'Expected error about no valid options for exit');
    }

    public function testValidateAcceptsBidirectionalOptions(): void
    {
        $opt = $this->createOption(['entry' => false, 'exit' => false]);
        $sp = $this->createStartingpoint(['options' => [$opt]]);

        $resultEntry = $sp->validate('', 1);
        $resultExit = $sp->validate('', 2);

        $this->assertEmpty($resultEntry, 'Bidirectional option should be valid for entry');
        $this->assertEmpty($resultExit, 'Bidirectional option should be valid for exit');
    }
}
