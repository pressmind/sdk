<?php


namespace Pressmind\System;


class RequirementsCheck
{
    public function checkPHPCliExecution()
    {
        $teststring = md5('php is fine!');
        if(exec('php ' . __DIR__ . '/cli_execution.php ' . '"' . $teststring . '"') != $teststring) {
            echo 'broken';
        }

        var_dump(exec('php ' . __DIR__ . '/cli_execution.php ' . '"' . $teststring . '"'));
    }
}
