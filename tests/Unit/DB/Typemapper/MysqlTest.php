<?php

namespace Pressmind\Tests\Unit\DB\Typemapper;

use Exception;
use Pressmind\DB\Typemapper\Mysql;
use Pressmind\Tests\Unit\AbstractTestCase;

/**
 * Unit tests for Pressmind\DB\Typemapper\Mysql.
 */
class MysqlTest extends AbstractTestCase
{
    private Mysql $mapper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mapper = new Mysql();
    }

    public function testMapTypeFromPressmindToORMKnownTypes(): void
    {
        $this->assertSame('text', $this->mapper->mapTypeFromPressmindToORM('text'));
        $this->assertSame('int', $this->mapper->mapTypeFromPressmindToORM('integer'));
        $this->assertSame('int', $this->mapper->mapTypeFromPressmindToORM('int'));
        $this->assertSame('datetime', $this->mapper->mapTypeFromPressmindToORM('date'));
        $this->assertSame('relation', $this->mapper->mapTypeFromPressmindToORM('picture'));
        $this->assertSame('relation', $this->mapper->mapTypeFromPressmindToORM('objectlink'));
        $this->assertSame('text', $this->mapper->mapTypeFromPressmindToORM('qrcode'));
    }

    public function testMapTypeFromPressmindToORMThrowsForUnknownType(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('does not exist in $_pressmind_mapping_table');
        $this->mapper->mapTypeFromPressmindToORM('unknown_type');
    }

    public function testMapTypeFromORMToMysqlKnownTypes(): void
    {
        $this->assertSame('INT', $this->mapper->mapTypeFromORMToMysql('int'));
        $this->assertSame('INT', $this->mapper->mapTypeFromORMToMysql('integer'));
        $this->assertSame('VARCHAR', $this->mapper->mapTypeFromORMToMysql('varchar'));
        $this->assertSame('TEXT', $this->mapper->mapTypeFromORMToMysql('text'));
        $this->assertSame('LONGTEXT', $this->mapper->mapTypeFromORMToMysql('longtext'));
        $this->assertSame('DATETIME', $this->mapper->mapTypeFromORMToMysql('datetime'));
        $this->assertSame('TINYINT(1)', $this->mapper->mapTypeFromORMToMysql('boolean'));
    }

    public function testMapTypeFromORMToMysqlThrowsForUnknownType(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('does not exist in $_orm_mapping_table');
        $this->mapper->mapTypeFromORMToMysql('unknown_orm_type');
    }

    public function testMapTypeFromPressMindToMysql(): void
    {
        $this->assertSame('INT', $this->mapper->mapTypeFromPressMindToMysql('integer'));
        $this->assertSame('TEXT', $this->mapper->mapTypeFromPressMindToMysql('plaintext'));
        // wysiwyg maps to 'text' in _pressmind_mapping_table, then to TEXT in ORM
        $this->assertSame('TEXT', $this->mapper->mapTypeFromPressMindToMysql('wysiwyg'));
    }

    public function testMapTypeFromORMToMysqlWithPropertyDefinitionSimple(): void
    {
        $prop = ['type' => 'integer', 'name' => 'id'];
        $this->assertSame('int', $this->mapper->mapTypeFromORMToMysqlWithPropertyDefinition($prop));
    }

    public function testMapTypeFromORMToMysqlWithPropertyDefinitionStringMaxlength(): void
    {
        $prop = [
            'type' => 'string',
            'name' => 'code',
            'validators' => [['name' => 'maxlength', 'params' => 50]],
        ];
        $this->assertSame('varchar(50)', $this->mapper->mapTypeFromORMToMysqlWithPropertyDefinition($prop));
    }

    public function testMapTypeFromORMToMysqlWithPropertyDefinitionIntegerMaxlengthBigint(): void
    {
        $prop = [
            'type' => 'integer',
            'name' => 'big_id',
            'validators' => [['name' => 'maxlength', 'params' => 20]],
        ];
        // Typemapper returns 'bigint' without length suffix when params > 11
        $this->assertSame('bigint', $this->mapper->mapTypeFromORMToMysqlWithPropertyDefinition($prop));
    }

    public function testMapTypeFromORMToMysqlWithPropertyDefinitionUnsigned(): void
    {
        $prop = [
            'type' => 'integer',
            'name' => 'id',
            'validators' => [['name' => 'unsigned']],
        ];
        $this->assertSame('int unsigned', $this->mapper->mapTypeFromORMToMysqlWithPropertyDefinition($prop));
    }

    public function testMapTypeFromORMToMysqlWithPropertyDefinitionInarray(): void
    {
        $prop = [
            'type' => 'string',
            'name' => 'status',
            'validators' => [['name' => 'inarray', 'params' => ['a', 'b', 'c']]],
        ];
        $this->assertSame("enum('a','b','c')", $this->mapper->mapTypeFromORMToMysqlWithPropertyDefinition($prop));
    }

    public function testMapTypeFromORMToMysqlWithPropertyDefinitionBooleanNoMaxlengthSuffix(): void
    {
        $prop = ['type' => 'boolean', 'name' => 'active', 'validators' => []];
        $this->assertSame('tinyint(1)', $this->mapper->mapTypeFromORMToMysqlWithPropertyDefinition($prop));
    }

    public function testMapTypeFromORMToMysqlWithPropertyDefinitionRelationReturnsEmpty(): void
    {
        $prop = ['type' => 'relation', 'name' => 'link'];
        $result = $this->mapper->mapTypeFromORMToMysqlWithPropertyDefinition($prop);
        // relation maps to null in _orm_mapping_table; strtolower(null) yields ''
        $this->assertSame('', $result);
    }

    public function testMapTypeFromPressmindToORMAllRemainingTypes(): void
    {
        $expectations = [
            'float' => 'float',
            'double' => 'double',
            'decimal' => 'decimal',
            'table' => 'relation',
            'plaintext' => 'text',
            'wysiwyg' => 'text',
            'file' => 'relation',
            'categorytree' => 'relation',
            'location' => 'relation',
            'link' => 'relation',
            'key_value' => 'relation',
        ];
        foreach ($expectations as $input => $expected) {
            $this->assertSame($expected, $this->mapper->mapTypeFromPressmindToORM($input), "Failed for pressmind type: {$input}");
        }
    }

    public function testMapTypeFromORMToMysqlAllRemainingTypes(): void
    {
        $expectations = [
            'float' => 'FLOAT',
            'double' => 'double',
            'decimal' => 'decimal',
            'date' => 'DATE',
            'DateTime' => 'DATETIME',
            'time' => 'TIME',
            'string' => 'TEXT',
            'blob' => 'BLOB',
            'longblob' => 'LONGBLOB',
            'encrypted' => 'BLOB',
            'enum' => 'ENUM',
            'relation' => null,
        ];
        foreach ($expectations as $input => $expected) {
            $this->assertSame($expected, $this->mapper->mapTypeFromORMToMysql($input), "Failed for ORM type: {$input}");
        }
    }

    public function testMapTypeFromPressMindToMysqlThrowsForUnknown(): void
    {
        $this->expectException(Exception::class);
        $this->mapper->mapTypeFromPressMindToMysql('nonexistent');
    }

    public function testMapTypeFromPressMindToMysqlRelationReturnsNull(): void
    {
        $this->assertNull($this->mapper->mapTypeFromPressMindToMysql('table'));
    }

    public function testMapTypeFromPressMindToMysqlNumericTypes(): void
    {
        $this->assertSame('FLOAT', $this->mapper->mapTypeFromPressMindToMysql('float'));
        $this->assertSame('double', $this->mapper->mapTypeFromPressMindToMysql('double'));
        $this->assertSame('decimal', $this->mapper->mapTypeFromPressMindToMysql('decimal'));
    }

    public function testMapTypeFromORMToMysqlWithPropertyDefinitionPrecisionValidator(): void
    {
        $prop = [
            'type' => 'decimal',
            'name' => 'price',
            'validators' => [['name' => 'precision', 'params' => [10, 2]]],
        ];
        $this->assertSame('decimal(10,2)', $this->mapper->mapTypeFromORMToMysqlWithPropertyDefinition($prop));
    }

    public function testMapTypeFromORMToMysqlWithPropertyDefinitionVarcharMaxlength(): void
    {
        $prop = [
            'type' => 'varchar',
            'name' => 'code',
            'validators' => [['name' => 'maxlength', 'params' => 100]],
        ];
        $this->assertSame('varchar(100)', $this->mapper->mapTypeFromORMToMysqlWithPropertyDefinition($prop));
    }

    public function testMapTypeFromORMToMysqlWithPropertyDefinitionIntegerSmallMaxlength(): void
    {
        $prop = [
            'type' => 'integer',
            'name' => 'small_id',
            'validators' => [['name' => 'maxlength', 'params' => 10]],
        ];
        $this->assertSame('int', $this->mapper->mapTypeFromORMToMysqlWithPropertyDefinition($prop));
    }

    public function testMapTypeFromORMToMysqlWithPropertyDefinitionCombinedMaxlengthUnsigned(): void
    {
        $prop = [
            'type' => 'integer',
            'name' => 'positive_id',
            'validators' => [
                ['name' => 'maxlength', 'params' => 10],
                ['name' => 'unsigned'],
            ],
        ];
        $this->assertSame('int unsigned', $this->mapper->mapTypeFromORMToMysqlWithPropertyDefinition($prop));
    }

    public function testMapTypeFromORMToMysqlWithPropertyDefinitionFloatNoValidators(): void
    {
        $prop = ['type' => 'float', 'name' => 'amount'];
        $this->assertSame('float', $this->mapper->mapTypeFromORMToMysqlWithPropertyDefinition($prop));
    }

    public function testMapTypeFromORMToMysqlWithPropertyDefinitionDoubleNoValidators(): void
    {
        $prop = ['type' => 'double', 'name' => 'amount'];
        $this->assertSame('double', $this->mapper->mapTypeFromORMToMysqlWithPropertyDefinition($prop));
    }

    public function testMapTypeFromORMToMysqlWithPropertyDefinitionDatetimeNoValidators(): void
    {
        $prop = ['type' => 'datetime', 'name' => 'created'];
        $this->assertSame('datetime', $this->mapper->mapTypeFromORMToMysqlWithPropertyDefinition($prop));
    }

    public function testMapTypeFromORMToMysqlWithPropertyDefinitionBooleanWithValidatorsIgnored(): void
    {
        $prop = [
            'type' => 'boolean',
            'name' => 'active',
            'validators' => [['name' => 'maxlength', 'params' => 1]],
        ];
        $this->assertSame('tinyint(1)', $this->mapper->mapTypeFromORMToMysqlWithPropertyDefinition($prop));
    }

    public function testMapTypeFromORMToMysqlWithPropertyDefinitionIntegerEmptyValidators(): void
    {
        $prop = [
            'type' => 'integer',
            'name' => 'count',
            'validators' => [],
        ];
        $this->assertSame('int', $this->mapper->mapTypeFromORMToMysqlWithPropertyDefinition($prop));
    }
}
