<?php
/**
 * Example: Load an insurance with its group, price tables, attributes, additional and alternate insurances.
 * Requires SDK bootstrap (database connection, autoload).
 *
 * Usage: run from CLI or include in a bootstrapped application.
 */

use Pressmind\ORM\Object\Touristic\Insurance;
use Pressmind\ORM\Object\Touristic\Insurance\Group;
use Pressmind\ORM\Object\Touristic\Insurance\InsuranceToGroup;

$insurance = new Insurance(15127);

// Insurance Group(s)
echo "=== Insurance Group(s) ===\n";
$groupRelations = InsuranceToGroup::listAll(['id_insurance' => $insurance->id]);
foreach ($groupRelations as $rel) {
    $group = new Group($rel->id_insurance_group);
    echo "Group ID: " . $group->id . "\n";
    echo "Group Name: " . $group->name . "\n";
    echo "Group Description: " . $group->description . "\n";
    echo "Group Active: " . ($group->active ? 'yes' : 'no') . "\n";
    echo "Group Selection Mode: " . ($group->mode ?: '(not set)') . "\n\n";
}

// Main insurance
echo "=== Main Insurance ===\n";
print_r($insurance->toStdClass());
echo "\n";

// Price tables
echo "=== Price Tables ===\n";
foreach ($insurance->price_tables as $pt) {
    print_r($pt->toStdClass());
}
echo "\n";

// Attributes / Zuschläge
echo "=== Attributes / Zuschläge ===\n";
foreach ($insurance->attributes as $attribute) {
    print_r($attribute->toStdClass());
}
echo "\n";

// Additional insurances (Zusatzversicherungen)
echo "=== Additional Insurances ===\n";
foreach ($insurance->additional_insurances as $additional) {
    print_r($additional->toStdClass());
    foreach ($additional->price_tables as $pt) {
        print_r($pt->toStdClass());
    }
    foreach ($additional->attributes as $attribute) {
        print_r($attribute->toStdClass());
    }
}
echo "\n";

// Alternate insurances (Alternativversicherungen)
echo "=== Alternate Insurances ===\n";
foreach ($insurance->alternate_insurances as $alternate) {
    print_r($alternate->toStdClass());
    foreach ($alternate->price_tables as $pt) {
        print_r($pt->toStdClass());
    }
    foreach ($alternate->attributes as $attribute) {
        print_r($attribute->toStdClass());
    }
}
