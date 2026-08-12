<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Analysis backend
    |--------------------------------------------------------------------------
    |
    | The base URL of the FastAPI service. It is stored as a base URL, not as a
    | full endpoint path, so that adding a second endpoint does not require
    | string-surgery on the configured value.
    |
    */

    'backend' => [
        'url' => env('BACKEND_API_URL', 'http://backend:8000'),
        'connect_timeout' => env('BACKEND_CONNECT_TIMEOUT', 5),
        'timeout' => env('BACKEND_TIMEOUT', 120),
        // A stochastic simulation is CPU-bound by design and can legitimately
        // run for a minute or more; the upload timeout is far too short for it.
        'simulation_timeout' => env('BACKEND_SIMULATION_TIMEOUT', 180),
        'retries' => env('BACKEND_RETRIES', 2),
        'max_upload_kb' => env('MAX_UPLOAD_KB', 10240),
    ],

    /*
    |--------------------------------------------------------------------------
    | Data retention
    |--------------------------------------------------------------------------
    |
    | How many days a stored result survives before `analyses:prune` deletes it.
    | Read by both the scheduled job and the footer, so the number a visitor is
    | shown is the number the job actually enforces — a promise about someone
    | else's sequence data is worth only as much as the code behind it.
    |
    */

    'retention_days' => (int) env('RETENTION_DAYS', 30),

];
