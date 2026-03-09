<?php

namespace Pressmind\Tests\Unit\System;

use Pressmind\System\RequirementsCheck;
use Pressmind\Tests\Unit\AbstractTestCase;

class RequirementsCheckTest extends AbstractTestCase
{
    /**
     * checkPHPCliExecution runs exec() and may echo/var_dump; we only assert it runs without throwing.
     */
    public function testCheckPHPCliExecutionRunsWithoutThrowing(): void
    {
        $check = new RequirementsCheck();
        ob_start();
        try {
            $check->checkPHPCliExecution();
            $this->assertTrue(true);
        } finally {
            ob_end_clean();
        }
    }
}
