<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * The only place in the application that knows the analysis backend exists.
 *
 * Controllers deal in arrays and BackendException; they never see HTTP status
 * codes, retry policy or endpoint paths.
 */
class DnaBackendClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly int $connectTimeout = 5,
        private readonly int $timeout = 120,
        private readonly int $retries = 2,
        private readonly int $simulationTimeout = 180,
    ) {
    }

    public static function fromConfig(): self
    {
        return new self(
            rtrim((string) config('services.backend.url'), '/'),
            (int) config('services.backend.connect_timeout', 5),
            (int) config('services.backend.timeout', 120),
            (int) config('services.backend.retries', 2),
            (int) config('services.backend.simulation_timeout', 180),
        );
    }

    public function analyze(UploadedFile $file): array
    {
        $requestId = (string) Str::uuid();

        try {
            $response = Http::withHeaders(['X-Request-ID' => $requestId])
                ->connectTimeout($this->connectTimeout)
                ->timeout($this->timeout)
                // Do not retry a 4xx: a malformed file will be malformed again. Pass `false` as 4th arg to prevent RequestException on 4xx/5xx
                ->retry($this->retries, 400, fn ($exception) => $exception instanceof ConnectionException, false)
                ->attach('file', $file->get(), $file->getClientOriginalName())
                ->post($this->baseUrl . '/api/v1/analyze');
        } catch (ConnectionException $exception) {
            Log::error('Analysis backend unreachable', [
                'request_id' => $requestId,
                'message' => $exception->getMessage(),
            ]);

            throw new BackendException('backend_unreachable', [], 503);
        }

        if ($response->successful()) {
            return $response->json();
        }

        throw $this->toException($response, $requestId);
    }

    /**
     * Compile a natural-language description into a genetic circuit.
     *
     * An unparseable sentence is not an exception: the backend answers 200 with
     * `ok: false` and diagnostics naming the clause it could not map. Only
     * transport and server faults raise.
     */
    public function compile(string $text): array
    {
        $requestId = (string) Str::uuid();

        try {
            $response = Http::withHeaders(['X-Request-ID' => $requestId])
                ->connectTimeout($this->connectTimeout)
                ->timeout($this->timeout)
                ->retry($this->retries, 400, fn ($exception) => $exception instanceof ConnectionException, false)
                ->post($this->baseUrl . '/api/v1/compile', ['text' => $text]);
        } catch (ConnectionException $exception) {
            Log::error('Compiler backend unreachable', [
                'request_id' => $requestId,
                'message' => $exception->getMessage(),
            ]);

            throw new BackendException('backend_unreachable', [], 503);
        }

        if ($response->successful()) {
            return $response->json();
        }

        throw $this->toException($response, $requestId);
    }

    /**
     * Run a stochastic simulation of gene expression noise and crosstalk.
     *
     * Given its own timeout: a simulation is seconds of CPU by design, and the
     * analysis timeout is tuned for a file upload. A run that is clamped, or
     * that stops when it exhausts its step budget, is still a 200 with
     * diagnostics — only transport and server faults raise.
     */
    public function simulate(array $parameters): array
    {
        $requestId = (string) Str::uuid();

        try {
            $response = Http::withHeaders(['X-Request-ID' => $requestId])
                ->connectTimeout($this->connectTimeout)
                ->timeout($this->simulationTimeout)
                // No retry. A simulation that timed out will time out again,
                // and a second attempt costs the backend as much as the first.
                ->post($this->baseUrl . '/api/v1/simulate', $parameters);
        } catch (ConnectionException $exception) {
            Log::error('Simulation backend unreachable', [
                'request_id' => $requestId,
                'message' => $exception->getMessage(),
            ]);

            throw new BackendException('backend_unreachable', [], 503);
        }

        if ($response->successful()) {
            return $response->json();
        }

        throw $this->toException($response, $requestId);
    }

    /**
     * Compare genetic memory architectures and get the DNA for the better one.
     *
     * Shares the simulation timeout: the work is an ODE integration and a
     * sequence scan rather than a file parse, and both are measured in seconds
     * of CPU rather than in bytes of input.
     */
    public function memory(array $parameters): array
    {
        $requestId = (string) Str::uuid();

        try {
            $response = Http::withHeaders(['X-Request-ID' => $requestId])
                ->connectTimeout($this->connectTimeout)
                ->timeout($this->simulationTimeout)
                ->post($this->baseUrl . '/api/v1/memory', $parameters);
        } catch (ConnectionException $exception) {
            Log::error('Memory design backend unreachable', [
                'request_id' => $requestId,
                'message' => $exception->getMessage(),
            ]);

            throw new BackendException('backend_unreachable', [], 503);
        }

        if ($response->successful()) {
            return $response->json();
        }

        throw $this->toException($response, $requestId);
    }

    public function health(): array
    {
        try {
            $response = Http::connectTimeout(3)->timeout(5)->get($this->baseUrl . '/health');

            return [
                'ok' => $response->successful(),
                'detail' => $response->successful() ? $response->json() : null,
            ];
        } catch (Throwable) {
            return ['ok' => false, 'detail' => null];
        }
    }

    private function toException(Response $response, string $requestId): BackendException
    {
        $body = $response->json();
        $code = data_get($body, 'error.code');
        $params = data_get($body, 'error.params', []);

        Log::warning('Analysis backend rejected a request', [
            'request_id' => $requestId,
            'status' => $response->status(),
            'code' => $code,
        ]);

        return new BackendException(
            is_string($code) && $code !== '' ? $code : 'internal_error',
            is_array($params) ? $params : [],
            $response->status(),
        );
    }
}