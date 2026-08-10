<?php

namespace App\Services;

use RuntimeException;

/**
 * Carries the backend's language-neutral error code up to the controller, which
 * turns it into text in whichever language the visitor is reading.
 */
class BackendException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        public readonly array $params = [],
        public readonly int $status = 502,
    ) {
        parent::__construct($errorCode);
    }

    /**
     * Flatten params into values that can be dropped straight into a translation
     * string. Lists become comma-separated text; everything else is cast.
     */
    public function replacements(): array
    {
        $flat = [];

        foreach ($this->params as $key => $value) {
            $flat[$key] = is_array($value)
                ? implode(', ', array_map(strval(...), $value))
                : (string) $value;
        }

        return $flat;
    }
}
