<?php

namespace Pressmind\Image;

class DerivativeCompletenessResult
{
    public array $missingKeys = [];
    public array $duplicateDerivativeNames = [];

    public function isComplete(): bool
    {
        return empty($this->missingKeys) && empty($this->duplicateDerivativeNames);
    }
}
