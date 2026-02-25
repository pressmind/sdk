<?php
/**
 * Example: Load an insurance and iterate over its additional (sub) insurances and alternate insurances.
 * Requires SDK bootstrap (database connection, autoload).
 *
 * Usage: run from CLI or include in a bootstrapped application.
 */

$insurance = new \Pressmind\ORM\Object\Touristic\Insurance(15127);

echo "Insurance: " . $insurance->name . "\n";
echo "is_recommendation: " . ($insurance->is_recommendation ? 'yes' : 'no') . "\n";
echo "priority: " . $insurance->priority . "\n\n";

// Additional insurances (Zusatzversicherungen)
foreach ($insurance->additional_insurances as $additional_insurance) {
    $data = $additional_insurance->toStdClass();
    print_r($data);
    foreach ($additional_insurance->attributes as $attribute) {
        $attr = $attribute->toStdClass();
        print_r($attr);
    }
    echo "\n";
}

// Alternate insurances (Alternativversicherungen)
foreach ($insurance->alternate_insurances as $alternate_insurance) {
    $data = $alternate_insurance->toStdClass();
    print_r($data);
    foreach ($alternate_insurance->attributes as $attribute) {
        $attr = $attribute->toStdClass();
        print_r($attr);
    }
    echo "\n";
}
