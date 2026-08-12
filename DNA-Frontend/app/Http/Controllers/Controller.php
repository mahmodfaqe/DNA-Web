<?php

namespace App\Http\Controllers;

use App\Services\BackendException;
use App\Support\ErrorTranslator;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * What the four tools share.
 *
 * Each tool posts a form to a backend that can refuse it, stores what came
 * back, and offers the result as a file. Those three shapes were written out
 * four times over; they live here instead, so a fix to the BOM or to the
 * Content-Disposition header lands on every tab at once.
 */
abstract class Controller
{
    /**
     * The backend refused the request: put its reason on the field the reader
     * was filling in, in their own language, and send them back to it.
     *
     * The backend answers with language-neutral codes precisely so that this
     * layer can do the translating — see ErrorTranslator.
     */
    protected function backendFailed(BackendException $exception, string $field): RedirectResponse
    {
        return back()
            ->withInput()
            ->withErrors([$field => ErrorTranslator::translate($exception)]);
    }

    /**
     * A JSON export the browser saves rather than renders.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function jsonDownload(array $payload, string $filename): JsonResponse
    {
        return response()->json($payload)
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }

    /**
     * A CSV export, streamed rather than assembled in memory and prefixed with
     * a UTF-8 BOM.
     *
     * The BOM is not decoration: without it Excel reads a Kurdish or Arabic
     * header as mojibake, and a spreadsheet nobody can read is the one thing
     * this export exists to avoid.
     *
     * @param  Closure(resource): void  $rows
     */
    protected function csvDownload(string $filename, Closure $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'wb');
            fwrite($handle, "\xEF\xBB\xBF");

            $rows($handle);

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Plain text — a FASTA construct — as a file the browser saves.
     *
     * Not streamed: a construct is a few kilobases, so assembling it in memory
     * costs nothing and a plain response is the simpler thing to reason about.
     */
    protected function textDownload(string $filename, string $body): Response
    {
        return response($body, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}
