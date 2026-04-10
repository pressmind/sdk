<?php

namespace Pressmind\System;

use RuntimeException;

/**
 * Thrown after a successful auto schema migration to signal that the
 * PHP process must restart so the regenerated ORM class gets loaded.
 */
class SchemaMigratedException extends RuntimeException
{
    private array $_migratedFields;
    private int $_objectTypeId;

    public function __construct(int $objectTypeId, array $migratedFields)
    {
        $this->_objectTypeId = $objectTypeId;
        $this->_migratedFields = $migratedFields;
        parent::__construct(
            'Schema migrated for ObjectType ' . $objectTypeId .
            ' (' . implode(', ', array_keys($migratedFields)) . ').' .
            ' The import must be restarted so the updated ORM class is loaded.'
        );
    }

    public function getObjectTypeId(): int
    {
        return $this->_objectTypeId;
    }

    public function getMigratedFields(): array
    {
        return $this->_migratedFields;
    }
}
