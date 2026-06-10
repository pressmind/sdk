<?php

namespace Pressmind\Import;

class MediaObjectDepublishResult
{
    /**
     * @var array<int, array<string, bool>>
     */
    private array $successes = [];

    /**
     * @var array<int, array<string, string>>
     */
    private array $errors = [];

    public function addSuccess(int $idMediaObject, string $target): void
    {
        $this->successes[$idMediaObject][$target] = true;
    }

    public function addError(int $idMediaObject, string $target, string $message): void
    {
        $this->errors[$idMediaObject][$target] = $message;
    }

    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * @return array<int, array<string, bool>>
     */
    public function getSuccesses(): array
    {
        return $this->successes;
    }

    public function isSuccessfulFor(int $idMediaObject, string $target): bool
    {
        return !empty($this->successes[$idMediaObject][$target]);
    }
}
