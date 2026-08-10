<?php

namespace App\Support;

use App\Services\BackendException;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Log;

final class ErrorTranslator
{
    /**
     * Turn a language-neutral backend code into text the reader understands.
     *
     * An unrecognised code must never surface as a raw identifier, so anything
     * without a translation falls back to the generic message and is logged —
     * that log line is how a missing translation gets noticed after a backend
     * release adds a new code.
     */
    public static function translate(BackendException $exception): string
    {
        $key = 'errors.backend.' . $exception->errorCode;

        if (! Lang::has($key)) {
            Log::warning('Untranslated backend error code', ['code' => $exception->errorCode]);
            $key = 'errors.backend.internal_error';
        }

        return __($key, $exception->replacements());
    }
}
