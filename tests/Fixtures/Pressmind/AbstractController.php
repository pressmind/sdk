<?php

namespace Pressmind;

/**
 * Test stub for Pressmind\AbstractController.
 * Provides the minimal interface used by REST controllers (Error, Pressmind)
 * when the application layer is not available (SDK standalone testing).
 */
class AbstractController
{
    /** @var array */
    public $parameters;

    public function __construct($parameters = [])
    {
        $this->parameters = $parameters;
    }
}
