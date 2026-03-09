<?php

namespace Pressmind\Tests\Unit\ORM\Validator;

use Pressmind\ORM\Validator\Datetime;
use Pressmind\ORM\Validator\Factory;
use Pressmind\ORM\Validator\Inarray;
use Pressmind\ORM\Validator\Maxlength;
use Pressmind\ORM\Validator\Precision;
use Pressmind\ORM\Validator\StringValidator;
use Pressmind\ORM\Validator\Unsigned;
use Pressmind\Tests\Unit\AbstractTestCase;

/**
 * Tests ORM validator classes directly (Inarray, Unsigned, Datetime, StringValidator, Precision).
 */
class ValidatorTest extends AbstractTestCase
{
    public function testInarrayValidValue(): void
    {
        $validator = Factory::create(['name' => 'inarray', 'params' => ['P', 'F', 'E']]);
        $this->assertTrue($validator->isValid('P'));
        $this->assertTrue($validator->isValid('F'));
        $this->assertTrue($validator->isValid('E'));
    }

    public function testInarrayInvalidValue(): void
    {
        $validator = Factory::create(['name' => 'inarray', 'params' => ['P', 'F', 'E']]);
        $this->assertNotTrue($validator->isValid('X'), 'Inarray returns null when invalid');
        $this->assertStringContainsString('not in scope', $validator->getError());
    }

    public function testInarrayEmptyStringNotInList(): void
    {
        $validator = Factory::create(['name' => 'inarray', 'params' => ['P', 'F', 'E', '']]);
        $this->assertTrue($validator->isValid(''));
    }

    public function testUnsignedZeroValid(): void
    {
        $validator = new Unsigned(null);
        $this->assertTrue($validator->isValid(0));
    }

    public function testUnsignedPositiveValid(): void
    {
        $validator = new Unsigned(null);
        $this->assertTrue($validator->isValid(100));
    }

    public function testUnsignedNegativeInvalid(): void
    {
        $validator = new Unsigned(null);
        $this->assertFalse($validator->isValid(-1));
    }

    public function testDatetimeValidString(): void
    {
        $validator = new Datetime(null);
        $this->assertTrue($validator->isValid('2026-01-01 00:00:00'));
    }

    public function testDatetimeInvalidString(): void
    {
        $validator = new Datetime(null);
        $this->assertFalse($validator->isValid('invalid'));
        $this->assertStringContainsString('not a valid DateTime', $validator->getError());
    }

    public function testDatetimeDateTimeObject(): void
    {
        $validator = new Datetime(null);
        $this->assertTrue($validator->isValid(new \DateTime()));
    }

    public function testStringValidatorValid(): void
    {
        $validator = new StringValidator();
        $this->assertTrue($validator->isValid('hello'));
    }

    public function testStringValidatorInvalid(): void
    {
        $validator = new StringValidator();
        $this->assertFalse($validator->isValid(123));
        $this->assertSame('String validation failed', $validator->getError());
    }

    public function testPrecisionValid(): void
    {
        $validator = new Precision();
        $this->assertTrue($validator->isValid(1.5));
    }

    public function testPrecisionInvalid(): void
    {
        $validator = new Precision();
        $this->assertFalse($validator->isValid('text'));
        $this->assertSame('Float validation failed', $validator->getError());
    }

    public function testPrecisionIntegerFails(): void
    {
        $validator = new Precision();
        $this->assertFalse($validator->isValid(1));
    }

    /**
     * Maxlength validator has no logic and always returns true (known limitation).
     */
    public function testMaxlengthAlwaysReturnsTrue(): void
    {
        $validator = Factory::create(['name' => 'maxlength', 'params' => [32]]);
        $this->assertTrue($validator->isValid('short'));
        $this->assertTrue($validator->isValid(str_repeat('x', 500)));
    }

    public function testMaxlengthGetError(): void
    {
        $validator = new Maxlength();
        $this->assertSame('Error Message', $validator->getError());
    }

    public function testUnsignedGetError(): void
    {
        $validator = new Unsigned();
        $validator->isValid(-5);
        $this->assertSame('Given value is not unsigned', $validator->getError());
    }
}
