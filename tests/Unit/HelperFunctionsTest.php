<?php

namespace Pressmind\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Pressmind\HelperFunctions;
use Pressmind\Registry;

class HelperFunctionsTest extends TestCase
{
    // ── dayNumberToLocalDayName ──────────────────────────────────────────

    public function testDayNumberToLocalDayNameFullGerman(): void
    {
        $expected = [
            1 => 'Montag',
            2 => 'Dienstag',
            3 => 'Mittwoch',
            4 => 'Donnerstag',
            5 => 'Freitag',
            6 => 'Samstag',
            7 => 'Sonntag',
        ];
        foreach ($expected as $day => $name) {
            $this->assertSame($name, HelperFunctions::dayNumberToLocalDayName($day, 'full', 'de'));
        }
    }

    public function testDayNumberToLocalDayNameShortGerman(): void
    {
        $this->assertSame('Mo', HelperFunctions::dayNumberToLocalDayName(1, 'short', 'de'));
        $this->assertSame('So', HelperFunctions::dayNumberToLocalDayName(7, 'short', 'de'));
    }

    public function testDayNumberToLocalDayNameMiddleGerman(): void
    {
        $this->assertSame('Mon', HelperFunctions::dayNumberToLocalDayName(1, 'middle', 'de'));
        $this->assertSame('Son', HelperFunctions::dayNumberToLocalDayName(7, 'middle', 'de'));
    }

    public function testDayNumberToLocalDayNameDefaultsToFullGerman(): void
    {
        $this->assertSame('Montag', HelperFunctions::dayNumberToLocalDayName(1));
    }

    // ── monthNumberToLocalMonthName ─────────────────────────────────────

    public function testMonthNumberToLocalMonthNameFullGerman(): void
    {
        $expected = [
            1 => 'Januar', 2 => 'Februar', 3 => 'März', 4 => 'April',
            5 => 'Mai', 6 => 'Juni', 7 => 'Juli', 8 => 'August',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Dezember',
        ];
        foreach ($expected as $month => $name) {
            $this->assertSame($name, HelperFunctions::monthNumberToLocalMonthName($month, 'full', 'de'));
        }
    }

    public function testMonthNumberToLocalMonthNameShortGerman(): void
    {
        $this->assertSame('Jan', HelperFunctions::monthNumberToLocalMonthName(1, 'short', 'de'));
        $this->assertSame('Mär', HelperFunctions::monthNumberToLocalMonthName(3, 'short', 'de'));
        $this->assertSame('Dez', HelperFunctions::monthNumberToLocalMonthName(12, 'short', 'de'));
    }

    public function testMonthNumberToLocalMonthNameDefaultsToFullGerman(): void
    {
        $this->assertSame('Januar', HelperFunctions::monthNumberToLocalMonthName(1));
    }

    // ── replaceLatinSpecialChars ─────────────────────────────────────────

    public function testReplaceLatinSpecialCharsGermanUmlauts(): void
    {
        $this->assertSame('ae', HelperFunctions::replaceLatinSpecialChars('ä'));
        $this->assertSame('oe', HelperFunctions::replaceLatinSpecialChars('ö'));
        $this->assertSame('ue', HelperFunctions::replaceLatinSpecialChars('ü'));
        $this->assertSame('ss', HelperFunctions::replaceLatinSpecialChars('ß'));
        $this->assertSame('AE', HelperFunctions::replaceLatinSpecialChars('Ä'));
        $this->assertSame('OE', HelperFunctions::replaceLatinSpecialChars('Ö'));
        $this->assertSame('UE', HelperFunctions::replaceLatinSpecialChars('Ü'));
    }

    public function testReplaceLatinSpecialCharsAccentedChars(): void
    {
        $this->assertSame('a', HelperFunctions::replaceLatinSpecialChars('à'));
        $this->assertSame('a', HelperFunctions::replaceLatinSpecialChars('á'));
        $this->assertSame('e', HelperFunctions::replaceLatinSpecialChars('é'));
        $this->assertSame('n', HelperFunctions::replaceLatinSpecialChars('ñ'));
        $this->assertSame('c', HelperFunctions::replaceLatinSpecialChars('ç'));
    }

    public function testReplaceLatinSpecialCharsSpecialSymbols(): void
    {
        $this->assertSame('EUR', HelperFunctions::replaceLatinSpecialChars('€'));
        $this->assertSame('AT', HelperFunctions::replaceLatinSpecialChars('@'));
    }

    public function testReplaceLatinSpecialCharsEmptyString(): void
    {
        $this->assertSame('', HelperFunctions::replaceLatinSpecialChars(''));
    }

    public function testReplaceLatinSpecialCharsNoSpecialChars(): void
    {
        $this->assertSame('hello world', HelperFunctions::replaceLatinSpecialChars('hello world'));
    }

    public function testReplaceLatinSpecialCharsMixedString(): void
    {
        $this->assertSame('Muenchen ist schoen', HelperFunctions::replaceLatinSpecialChars('München ist schön'));
    }

    // ── human_to_machine ────────────────────────────────────────────────

    public function testHumanToMachineBasicConversion(): void
    {
        $this->assertSame('this_is_a_human_sentence_', HelperFunctions::human_to_machine('This is a human sentence.'));
    }

    public function testHumanToMachineWithUmlauts(): void
    {
        $this->assertSame('muenchen', HelperFunctions::human_to_machine('München'));
    }

    public function testHumanToMachineWithSpecialChars(): void
    {
        $this->assertSame('hello_world_', HelperFunctions::human_to_machine('Hello World!'));
    }

    public function testHumanToMachineStripsLeadingNumbers(): void
    {
        $this->assertSame('test', HelperFunctions::human_to_machine('123test'));
    }

    public function testHumanToMachineReturnsIntegerUnchanged(): void
    {
        $this->assertSame(42, HelperFunctions::human_to_machine(42));
    }

    public function testHumanToMachineReturnsFloatUnchanged(): void
    {
        $this->assertSame(3.14, HelperFunctions::human_to_machine(3.14));
    }

    public function testHumanToMachineEmptyString(): void
    {
        $this->assertSame('', HelperFunctions::human_to_machine(''));
    }

    public function testHumanToMachineMultipleSpaces(): void
    {
        $result = HelperFunctions::human_to_machine('hello   world');
        $this->assertStringNotContainsString(' ', $result);
        $this->assertStringContainsString('hello', $result);
        $this->assertStringContainsString('world', $result);
    }

    // ── randomString ────────────────────────────────────────────────────

    public function testRandomStringTestTypeAlwaysReturnsTest(): void
    {
        $this->assertSame('test', HelperFunctions::randomString(8, 'test'));
    }

    public function testRandomStringDefaultLength(): void
    {
        $result = HelperFunctions::randomString();
        $this->assertIsString($result);
        $this->assertSame(8, strlen($result));
    }

    public function testRandomStringCustomLength(): void
    {
        $result = HelperFunctions::randomString(16, 'standard');
        $this->assertSame(16, strlen($result));
    }

    public function testRandomStringAlnumContainsOnlyAlphanumerics(): void
    {
        $result = HelperFunctions::randomString(100, 'alnum');
        $this->assertMatchesRegularExpression('/^[a-zA-Z0-9]+$/', $result);
    }

    public function testRandomStringStandardExcludesAmbiguousChars(): void
    {
        $ambiguous = ['l', '1', '0', 'O'];
        $found = false;
        for ($i = 0; $i < 50; $i++) {
            $result = HelperFunctions::randomString(100, 'standard');
            foreach ($ambiguous as $char) {
                if (strpos($result, $char) !== false) {
                    $found = true;
                    break 2;
                }
            }
        }
        $this->assertFalse($found, 'Standard type should exclude ambiguous characters (l, 1, 0, O)');
    }

    public function testRandomStringUnknownTypeReturnsFalse(): void
    {
        $this->assertFalse(HelperFunctions::randomString(8, 'nonexistent'));
    }

    // ── buildPathString ─────────────────────────────────────────────────

    public function testBuildPathStringJoinsWithDirectorySeparator(): void
    {
        $result = HelperFunctions::buildPathString(['usr', 'local', 'bin']);
        $expected = 'usr' . DIRECTORY_SEPARATOR . 'local' . DIRECTORY_SEPARATOR . 'bin';
        $this->assertSame($expected, $result);
    }

    public function testBuildPathStringSingleElement(): void
    {
        $this->assertSame('home', HelperFunctions::buildPathString(['home']));
    }

    public function testBuildPathStringEmptyArray(): void
    {
        $this->assertSame('', HelperFunctions::buildPathString([]));
    }

    // ── findObjectInArray ───────────────────────────────────────────────

    public function testFindObjectInArrayFindsMatch(): void
    {
        $obj1 = (object)['id' => 1, 'name' => 'Alice'];
        $obj2 = (object)['id' => 2, 'name' => 'Bob'];
        $obj3 = (object)['id' => 3, 'name' => 'Charlie'];

        $result = HelperFunctions::findObjectInArray([$obj1, $obj2, $obj3], 'id', 2);
        $this->assertSame($obj2, $result);
    }

    public function testFindObjectInArrayReturnsFirstMatch(): void
    {
        $obj1 = (object)['type' => 'a', 'val' => 1];
        $obj2 = (object)['type' => 'a', 'val' => 2];

        $result = HelperFunctions::findObjectInArray([$obj1, $obj2], 'type', 'a');
        $this->assertSame($obj1, $result);
    }

    public function testFindObjectInArrayReturnsNullWhenNotFound(): void
    {
        $obj = (object)['id' => 1];
        $result = HelperFunctions::findObjectInArray([$obj], 'id', 999);
        $this->assertNull($result);
    }

    public function testFindObjectInArrayEmptyArray(): void
    {
        $result = HelperFunctions::findObjectInArray([], 'id', 1);
        $this->assertNull($result);
    }

    public function testFindObjectInArrayUsesStrictComparison(): void
    {
        $obj = (object)['id' => 1];
        $result = HelperFunctions::findObjectInArray([$obj], 'id', '1');
        $this->assertNull($result, 'Should use strict comparison (===)');
    }

    // ── escapeString ────────────────────────────────────────────────────

    public function testEscapeStringStripsTags(): void
    {
        $result = HelperFunctions::escapeString('<b>Hello</b> <script>alert("xss")</script>');
        $this->assertStringNotContainsString('<b>', $result);
        $this->assertStringNotContainsString('<script>', $result);
        $this->assertStringContainsString('Hello', $result);
    }

    public function testEscapeStringWithoutStripTagsRemovesParagraphsOnly(): void
    {
        $result = HelperFunctions::escapeString('<p>Hello</p> <b>World</b>', false);
        $this->assertStringNotContainsString('<p>', $result);
        $this->assertStringNotContainsString('</p>', $result);
        $this->assertStringContainsString('<b>World</b>', $result);
    }

    public function testEscapeStringRemovesSqlKeywords(): void
    {
        $this->assertStringNotContainsString('SELECT', HelperFunctions::escapeString('SELECT * FROM users'));
        $this->assertStringNotContainsString('INSERT', HelperFunctions::escapeString('INSERT INTO table'));
        $this->assertStringNotContainsString('DELETE', HelperFunctions::escapeString('DELETE FROM table'));
        $this->assertStringNotContainsString('TRUNCATE', HelperFunctions::escapeString('TRUNCATE table'));
        $this->assertStringNotContainsString('GRANT', HelperFunctions::escapeString('GRANT ALL'));
        $this->assertStringNotContainsString('UPDATE', HelperFunctions::escapeString('UPDATE table SET'));
    }

    public function testEscapeStringRemovesFrom(): void
    {
        $result = HelperFunctions::escapeString('Data FROM table');
        $this->assertStringNotContainsString('FROM', $result);
    }

    public function testEscapeStringCaseInsensitiveSqlRemoval(): void
    {
        $result = HelperFunctions::escapeString('select data from table');
        $this->assertStringNotContainsString('select', $result);
        $this->assertStringNotContainsString('from', $result);
    }

    public function testEscapeStringEmptyString(): void
    {
        $this->assertSame('', HelperFunctions::escapeString(''));
    }

    // ── escapeUserInput ─────────────────────────────────────────────────

    public function testEscapeUserInputHandlesString(): void
    {
        $result = HelperFunctions::escapeUserInput('<b>Hello</b>');
        $this->assertStringNotContainsString('<b>', $result);
    }

    public function testEscapeUserInputHandlesArray(): void
    {
        $input = ['key1' => '<b>Hello</b>', 'key2' => 'SELECT * FROM'];
        $result = HelperFunctions::escapeUserInput($input);
        $this->assertIsArray($result);
        $this->assertStringNotContainsString('<b>', $result['key1']);
        $this->assertStringNotContainsString('SELECT', $result['key2']);
    }

    public function testEscapeUserInputPreservesArrayKeys(): void
    {
        $input = ['foo' => 'bar', 'baz' => 'qux'];
        $result = HelperFunctions::escapeUserInput($input);
        $this->assertArrayHasKey('foo', $result);
        $this->assertArrayHasKey('baz', $result);
    }

    // ── trimText ────────────────────────────────────────────────────────

    public function testTrimTextShorterThanLimit(): void
    {
        $this->assertSame('Hello world', HelperFunctions::trimText('Hello world', 50));
    }

    public function testTrimTextCutsAtWordBoundary(): void
    {
        $result = HelperFunctions::trimText('The quick brown fox jumps over the lazy dog', 20);
        $this->assertStringContainsString('...', $result);
        $this->assertLessThanOrEqual(25, strlen($result));
    }

    public function testTrimTextWithCustomEllipsis(): void
    {
        $result = HelperFunctions::trimText('The quick brown fox jumps over the lazy dog', 20, '…');
        $this->assertStringContainsString('…', $result);
    }

    public function testTrimTextWithoutEllipsis(): void
    {
        $result = HelperFunctions::trimText('The quick brown fox jumps', 10, false);
        $this->assertStringNotContainsString('...', $result);
    }

    public function testTrimTextStripsHtmlByDefault(): void
    {
        $result = HelperFunctions::trimText('<p>Hello <b>world</b></p>', 50);
        $this->assertStringNotContainsString('<p>', $result);
        $this->assertStringNotContainsString('<b>', $result);
    }

    public function testTrimTextPreservesHtmlWhenDisabled(): void
    {
        $input = '<p>Hello world</p>';
        $result = HelperFunctions::trimText($input, 100, '...', false);
        $this->assertStringContainsString('<p>', $result);
    }

    public function testTrimTextEmptyString(): void
    {
        $this->assertSame('', HelperFunctions::trimText('', 10));
    }

    // ── isJson ──────────────────────────────────────────────────────────

    public function testIsJsonValidJson(): void
    {
        $this->assertTrue(HelperFunctions::isJson('{"key": "value"}'));
        $this->assertTrue(HelperFunctions::isJson('[1, 2, 3]'));
        $this->assertTrue(HelperFunctions::isJson('"string"'));
        $this->assertTrue(HelperFunctions::isJson('42'));
        $this->assertTrue(HelperFunctions::isJson('null'));
        $this->assertTrue(HelperFunctions::isJson('true'));
    }

    public function testIsJsonInvalidJson(): void
    {
        $this->assertFalse(HelperFunctions::isJson('{invalid}'));
        $this->assertFalse(HelperFunctions::isJson("{'key': 'value'}"));
        $this->assertFalse(HelperFunctions::isJson('{key: value}'));
    }

    public function testIsJsonEmptyString(): void
    {
        $this->assertFalse(HelperFunctions::isJson(''));
    }

    // ── isInteger ───────────────────────────────────────────────────────

    public function testIsIntegerWithIntegers(): void
    {
        $this->assertTrue(HelperFunctions::isInteger(42));
        $this->assertTrue(HelperFunctions::isInteger(0));
        $this->assertTrue(HelperFunctions::isInteger(-5));
    }

    public function testIsIntegerWithIntegerStrings(): void
    {
        $this->assertTrue(HelperFunctions::isInteger('42'));
        $this->assertTrue(HelperFunctions::isInteger('0'));
        $this->assertTrue(HelperFunctions::isInteger('-5'));
        $this->assertTrue(HelperFunctions::isInteger('+10'));
    }

    public function testIsIntegerWithFloats(): void
    {
        $this->assertFalse(HelperFunctions::isInteger(3.14));
        $this->assertFalse(HelperFunctions::isInteger('3.14'));
    }

    public function testIsIntegerWithBooleans(): void
    {
        $this->assertFalse(HelperFunctions::isInteger(true));
        $this->assertFalse(HelperFunctions::isInteger(false));
    }

    public function testIsIntegerWithNonScalars(): void
    {
        $this->assertFalse(HelperFunctions::isInteger([]));
        $this->assertFalse(HelperFunctions::isInteger(null));
        $this->assertFalse(HelperFunctions::isInteger(new \stdClass()));
    }

    public function testIsIntegerWithNonNumericStringThrowsTypeError(): void
    {
        $this->expectException(\TypeError::class);
        HelperFunctions::isInteger('abc');
    }

    public function testIsIntegerWithEmptyStringThrowsTypeError(): void
    {
        $this->expectException(\TypeError::class);
        HelperFunctions::isInteger('');
    }

    // ── isFloat ─────────────────────────────────────────────────────────

    public function testIsFloatWithFloats(): void
    {
        $this->assertTrue(HelperFunctions::isFloat(3.14));
        $this->assertTrue(HelperFunctions::isFloat('3.14'));
        $this->assertTrue(HelperFunctions::isFloat('0.5'));
    }

    public function testIsFloatWithIntegers(): void
    {
        $this->assertFalse(HelperFunctions::isFloat(42));
        $this->assertFalse(HelperFunctions::isFloat('42'));
    }

    public function testIsFloatWithNonScalar(): void
    {
        $this->assertFalse(HelperFunctions::isFloat([]));
        $this->assertFalse(HelperFunctions::isFloat(null));
    }

    // ── strToFloat ──────────────────────────────────────────────────────

    public function testStrToFloatGermanFormat(): void
    {
        $this->assertSame(1234.56, HelperFunctions::strToFloat('1.234,56'));
    }

    public function testStrToFloatEnglishFormat(): void
    {
        $this->assertSame(1234.56, HelperFunctions::strToFloat('1,234.56'));
    }

    public function testStrToFloatSimpleDecimalComma(): void
    {
        $this->assertSame(12.23, HelperFunctions::strToFloat('12,23'));
    }

    public function testStrToFloatSimpleDecimalDot(): void
    {
        $this->assertSame(12.23, HelperFunctions::strToFloat('12.23'));
    }

    public function testStrToFloatInteger(): void
    {
        $this->assertSame(42.0, HelperFunctions::strToFloat(42));
    }

    public function testStrToFloatFloatValue(): void
    {
        $this->assertSame(3.14, HelperFunctions::strToFloat(3.14));
    }

    public function testStrToFloatNegativeNumber(): void
    {
        $this->assertSame(-12.5, HelperFunctions::strToFloat('-12,5'));
    }

    public function testStrToFloatPositiveSign(): void
    {
        $this->assertSame(12.5, HelperFunctions::strToFloat('+12,5'));
    }

    public function testStrToFloatWithLeadingSpaces(): void
    {
        $this->assertSame(42.0, HelperFunctions::strToFloat('  42'));
    }

    public function testStrToFloatThrowsOnNonString(): void
    {
        $this->expectException(\Exception::class);
        HelperFunctions::strToFloat([]);
    }

    public function testStrToFloatThrowsOnInvalidFormat(): void
    {
        $this->expectException(\Exception::class);
        HelperFunctions::strToFloat('abc');
    }

    // ── assignArrayByPath ───────────────────────────────────────────────

    public function testAssignArrayByPathCreatesNestedStructure(): void
    {
        $arr = [];
        HelperFunctions::assignArrayByPath($arr, 'foo.bar.baz', 'value');
        $this->assertSame('value', $arr['foo']['bar']['baz']);
    }

    public function testAssignArrayByPathSingleKey(): void
    {
        $arr = [];
        HelperFunctions::assignArrayByPath($arr, 'key', 'value');
        $this->assertSame('value', $arr['key']);
    }

    public function testAssignArrayByPathCustomSeparator(): void
    {
        $arr = [];
        HelperFunctions::assignArrayByPath($arr, 'foo/bar/baz', 'value', '/');
        $this->assertSame('value', $arr['foo']['bar']['baz']);
    }

    public function testAssignArrayByPathOverwritesExistingValue(): void
    {
        $arr = ['foo' => ['bar' => 'old']];
        HelperFunctions::assignArrayByPath($arr, 'foo.bar', 'new');
        $this->assertSame('new', $arr['foo']['bar']);
    }

    // ── getArrayByPath ──────────────────────────────────────────────────

    public function testGetArrayByPathRetrievesNestedValue(): void
    {
        $data = ['foo' => ['bar' => ['baz' => 'value']]];
        $this->assertSame('value', HelperFunctions::getArrayByPath($data, 'foo.bar.baz'));
    }

    public function testGetArrayByPathSingleKey(): void
    {
        $data = ['key' => 'value'];
        $this->assertSame('value', HelperFunctions::getArrayByPath($data, 'key'));
    }

    public function testGetArrayByPathCustomSeparator(): void
    {
        $data = ['a' => ['b' => 'found']];
        $this->assertSame('found', HelperFunctions::getArrayByPath($data, 'a/b', '/'));
    }

    public function testGetArrayByPathReturnsDataWhenKeyNotFound(): void
    {
        $data = ['foo' => 'bar'];
        $result = HelperFunctions::getArrayByPath($data, 'nonexistent');
        $this->assertSame(['foo' => 'bar'], $result);
    }

    // ── number_format ───────────────────────────────────────────────────

    public function testNumberFormatGermanStyle(): void
    {
        $this->assertSame('1.234,50', HelperFunctions::number_format(1234.5));
    }

    public function testNumberFormatZero(): void
    {
        $this->assertSame('0,00', HelperFunctions::number_format(0));
    }

    public function testNumberFormatNegative(): void
    {
        $this->assertSame('-99,99', HelperFunctions::number_format(-99.99));
    }

    public function testNumberFormatRounding(): void
    {
        $this->assertSame('10,13', HelperFunctions::number_format(10.126));
    }

    public function testNumberFormatLargeNumber(): void
    {
        $this->assertSame('1.000.000,00', HelperFunctions::number_format(1000000));
    }

    // ── getExtensionFromMimeType ────────────────────────────────────────

    public function testGetExtensionFromMimeTypeCommonTypes(): void
    {
        $this->assertSame('jpg', HelperFunctions::getExtensionFromMimeType('image/jpeg'));
        $this->assertSame('png', HelperFunctions::getExtensionFromMimeType('image/png'));
        $this->assertSame('gif', HelperFunctions::getExtensionFromMimeType('image/gif'));
        $this->assertSame('pdf', HelperFunctions::getExtensionFromMimeType('application/pdf'));
        $this->assertSame('html', HelperFunctions::getExtensionFromMimeType('text/html'));
        $this->assertSame('css', HelperFunctions::getExtensionFromMimeType('text/css'));
        $this->assertSame('json', HelperFunctions::getExtensionFromMimeType('application/json'));
        $this->assertSame('xml', HelperFunctions::getExtensionFromMimeType('application/xml'));
        $this->assertSame('zip', HelperFunctions::getExtensionFromMimeType('application/zip'));
        $this->assertSame('svg', HelperFunctions::getExtensionFromMimeType('image/svg+xml'));
        $this->assertSame('mp4', HelperFunctions::getExtensionFromMimeType('video/mp4'));
        $this->assertSame('mp3', HelperFunctions::getExtensionFromMimeType('audio/mpeg'));
        $this->assertSame('webp', HelperFunctions::getExtensionFromMimeType('image/webp'));
    }

    public function testGetExtensionFromMimeTypeOfficeDocuments(): void
    {
        $this->assertSame('doc', HelperFunctions::getExtensionFromMimeType('application/msword'));
        $this->assertSame('docx', HelperFunctions::getExtensionFromMimeType(
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        ));
        $this->assertSame('xlsx', HelperFunctions::getExtensionFromMimeType(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        ));
        $this->assertSame('pptx', HelperFunctions::getExtensionFromMimeType(
            'application/vnd.openxmlformats-officedocument.presentationml.presentation'
        ));
    }

    public function testGetExtensionFromMimeTypeFontTypes(): void
    {
        $this->assertSame('woff', HelperFunctions::getExtensionFromMimeType('font/woff'));
        $this->assertSame('woff2', HelperFunctions::getExtensionFromMimeType('font/woff2'));
        $this->assertSame('ttf', HelperFunctions::getExtensionFromMimeType('font/ttf'));
        $this->assertSame('otf', HelperFunctions::getExtensionFromMimeType('font/otf'));
    }

    public function testGetExtensionFromMimeTypeUnknownReturnsFalse(): void
    {
        $this->assertFalse(HelperFunctions::getExtensionFromMimeType('application/unknown'));
        $this->assertFalse(HelperFunctions::getExtensionFromMimeType(''));
    }

    // ── parseCliArguments ───────────────────────────────────────────────

    public function testParseCliArgumentsLongOptionWithValue(): void
    {
        $result = HelperFunctions::parseCliArguments(['--name', 'John']);
        $this->assertSame('John', $result['dictionary']['name']);
    }

    public function testParseCliArgumentsLongOptionWithoutValue(): void
    {
        $result = HelperFunctions::parseCliArguments(['--verbose']);
        $this->assertContains('verbose', $result['list']);
    }

    public function testParseCliArgumentsShortOptionsExpanded(): void
    {
        $result = HelperFunctions::parseCliArguments(['-abc']);
        $this->assertContains('a', $result['list']);
        $this->assertContains('b', $result['list']);
        $this->assertContains('c', $result['list']);
    }

    public function testParseCliArgumentsPositionalArgs(): void
    {
        $result = HelperFunctions::parseCliArguments(['file.txt', 'output.txt']);
        $this->assertContains('file.txt', $result['list']);
        $this->assertContains('output.txt', $result['list']);
    }

    public function testParseCliArgumentsMixed(): void
    {
        $result = HelperFunctions::parseCliArguments(['command', '--format', 'json', '-v', 'file.txt']);
        $this->assertSame('json', $result['dictionary']['format']);
        $this->assertContains('v', $result['list']);
        $this->assertContains('command', $result['list']);
        $this->assertContains('file.txt', $result['list']);
    }

    public function testParseCliArgumentsHelpWantedWithH(): void
    {
        $result = HelperFunctions::parseCliArguments(['-h']);
        $this->assertTrue($result['help_wanted']);
    }

    public function testParseCliArgumentsHelpWantedWithHelpFlag(): void
    {
        $result = HelperFunctions::parseCliArguments(['--help']);
        $this->assertTrue($result['help_wanted']);
    }

    public function testParseCliArgumentsHelpWantedWithLongHelp(): void
    {
        $result = HelperFunctions::parseCliArguments(['--help', 'something']);
        $this->assertTrue($result['help_wanted']);
    }

    public function testParseCliArgumentsHelpNotWanted(): void
    {
        $result = HelperFunctions::parseCliArguments(['--format', 'json']);
        $this->assertFalse($result['help_wanted']);
    }

    public function testParseCliArgumentsHelpWantedSingleListArg(): void
    {
        $result = HelperFunctions::parseCliArguments(['command']);
        $this->assertTrue($result['help_wanted']);
    }

    public function testParseCliArgumentsEmptyArguments(): void
    {
        $result = HelperFunctions::parseCliArguments([]);
        $this->assertSame([], $result['list']);
        $this->assertSame([], $result['dictionary']);
    }

    // ── convertDateTimeToStringRecursive ────────────────────────────────

    public function testConvertDateTimeToStringRecursiveWithDateTime(): void
    {
        $dt = new \DateTime('2024-06-15 14:30:00');
        $result = HelperFunctions::convertDateTimeToStringRecursive($dt);
        $this->assertSame('2024-06-15 14:30:00', $result);
    }

    public function testConvertDateTimeToStringRecursiveWithArray(): void
    {
        $data = [
            'name' => 'Test',
            'date' => new \DateTime('2024-01-01 00:00:00'),
            'nested' => [
                'created' => new \DateTime('2024-12-31 23:59:59'),
            ],
        ];
        $result = HelperFunctions::convertDateTimeToStringRecursive($data);
        $this->assertSame('Test', $result['name']);
        $this->assertSame('2024-01-01 00:00:00', $result['date']);
        $this->assertSame('2024-12-31 23:59:59', $result['nested']['created']);
    }

    public function testConvertDateTimeToStringRecursiveWithObject(): void
    {
        $obj = new \stdClass();
        $obj->name = 'Test';
        $obj->created = new \DateTime('2024-03-15 10:00:00');

        $result = HelperFunctions::convertDateTimeToStringRecursive($obj);
        $this->assertSame('Test', $result->name);
        $this->assertSame('2024-03-15 10:00:00', $result->created);
    }

    public function testConvertDateTimeToStringRecursiveWithScalar(): void
    {
        $this->assertSame('hello', HelperFunctions::convertDateTimeToStringRecursive('hello'));
        $this->assertSame(42, HelperFunctions::convertDateTimeToStringRecursive(42));
        $this->assertNull(HelperFunctions::convertDateTimeToStringRecursive(null));
    }

    public function testConvertDateTimeToStringRecursiveDeeplyNested(): void
    {
        $data = [
            'level1' => [
                'level2' => [
                    'date' => new \DateTime('2024-06-01 12:00:00'),
                ],
            ],
        ];
        $result = HelperFunctions::convertDateTimeToStringRecursive($data);
        $this->assertSame('2024-06-01 12:00:00', $result['level1']['level2']['date']);
    }

    public function testConvertDateTimeToStringRecursiveEmptyArray(): void
    {
        $this->assertSame([], HelperFunctions::convertDateTimeToStringRecursive([]));
    }

    // ── debug ────────────────────────────────────────────────────────────

    public function testDebugOutputWhenGetParamMatches(): void
    {
        $_GET['debug'] = 'mykey';
        ob_start();
        HelperFunctions::debug(['foo' => 'bar'], 'TestTitle', 'mykey', false);
        $output = ob_get_clean();
        unset($_GET['debug']);
        $this->assertStringContainsString('<h3>TestTitle</h3>', $output);
        $this->assertStringContainsString('<pre>', $output);
        $this->assertStringContainsString('foo', $output);
        $this->assertStringContainsString('bar', $output);
        $this->assertStringNotContainsString('position: fixed', $output);
    }

    public function testDebugNoOutputWhenGetParamDoesNotMatch(): void
    {
        $_GET['debug'] = 'other';
        ob_start();
        HelperFunctions::debug(['x' => 1], 'Title', 'mykey', false);
        $output = ob_get_clean();
        unset($_GET['debug']);
        $this->assertSame('', $output);
    }

    public function testDebugNoOutputWhenGetParamNotSet(): void
    {
        unset($_GET['debug']);
        ob_start();
        HelperFunctions::debug('data', null, 1, false);
        $output = ob_get_clean();
        $this->assertSame('', $output);
    }

    public function testDebugOutputContainsPositionFixedWhenDisplayFixedTrue(): void
    {
        $_GET['debug'] = 1;
        ob_start();
        HelperFunctions::debug('x', null, 1, true);
        $output = ob_get_clean();
        unset($_GET['debug']);
        $this->assertStringContainsString('position: fixed', $output);
    }

    public function testDebugNoH3WhenTitleNull(): void
    {
        $_GET['debug'] = 1;
        ob_start();
        HelperFunctions::debug(new \stdClass(), null, 1, false);
        $output = ob_get_clean();
        unset($_GET['debug']);
        $this->assertStringNotContainsString('<h3>', $output);
        $this->assertStringContainsString('<pre>', $output);
    }

    public function testDebugOutputContainsPrintRRepresentation(): void
    {
        $_GET['debug'] = 1;
        $obj = (object)['id' => 42, 'name' => 'Test'];
        ob_start();
        HelperFunctions::debug($obj, 'Obj', 1, false);
        $output = ob_get_clean();
        unset($_GET['debug']);
        $this->assertStringContainsString('42', $output);
        $this->assertStringContainsString('Test', $output);
    }

    // ── replaceConstantsFromConfig ───────────────────────────────────────

    public function testReplaceConstantsFromConfigReplacesBasePath(): void
    {
        Registry::clear();
        Registry::getInstance()->add('config', ['database' => ['dbname' => 'test']]);
        $input = 'Path: BASE_PATH/files';
        $result = HelperFunctions::replaceConstantsFromConfig($input);
        $this->assertStringContainsString(BASE_PATH, $result);
        $this->assertStringContainsString('/files', $result);
        $this->assertStringNotContainsString('BASE_PATH', $result);
        Registry::clear();
    }

    public function testReplaceConstantsFromConfigReplacesDatabaseName(): void
    {
        Registry::clear();
        Registry::getInstance()->add('config', ['database' => ['dbname' => 'my_test_db']]);
        $input = 'Database: DATABASE_NAME';
        $result = HelperFunctions::replaceConstantsFromConfig($input);
        $this->assertSame('Database: my_test_db', $result);
        Registry::clear();
    }

    public function testReplaceConstantsFromConfigLeavesStringWithoutPlaceholdersUnchanged(): void
    {
        Registry::clear();
        Registry::getInstance()->add('config', ['database' => ['dbname' => 'test']]);
        $input = 'no placeholders here';
        $result = HelperFunctions::replaceConstantsFromConfig($input);
        $this->assertSame($input, $result);
        Registry::clear();
    }

    public function testReplaceConstantsFromConfigReplacesMultiplePlaceholders(): void
    {
        Registry::clear();
        Registry::getInstance()->add('config', ['database' => ['dbname' => 'db1']]);
        $input = 'BASE_PATH and APPLICATION_PATH and DATABASE_NAME';
        $result = HelperFunctions::replaceConstantsFromConfig($input);
        $this->assertStringContainsString(BASE_PATH, $result);
        $this->assertStringContainsString(APPLICATION_PATH, $result);
        $this->assertStringContainsString('db1', $result);
        $this->assertStringNotContainsString('BASE_PATH', $result);
        $this->assertStringNotContainsString('APPLICATION_PATH', $result);
        $this->assertStringNotContainsString('DATABASE_NAME', $result);
        Registry::clear();
    }
}
