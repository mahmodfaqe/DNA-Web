<?php

return [
    'heading' => 'This file could not be analysed',

    /*
    |--------------------------------------------------------------------------
    | Backend error codes
    |--------------------------------------------------------------------------
    |
    | Keys mirror the codes emitted by the analysis service. Placeholders map
    | directly onto the `params` object it returns, so a new code needs a new
    | line here and nothing else.
    |
    */

    'backend' => [
        'file_missing' => 'No file was received. Choose a FASTA file and try again.',
        'file_too_large' => 'The file is larger than :max_megabytes MB. Split it into smaller files or remove records you do not need.',
        'file_encoding' => 'The file is not valid UTF-8 text. Re-save it as UTF-8 and upload it again.',
        'fasta_unparsable' => 'This is not a FASTA file. Every record must start with a header line beginning with “>”.',
        'fasta_empty' => 'The file contains no sequence records.',
        'too_many_records' => 'The file holds :found records, and the limit is :maximum. Upload it in smaller batches.',
        'sequence_empty' => 'Record “:record_id” has a header but no sequence.',
        'sequence_invalid_chars' => 'Record “:record_id” contains characters that are not nucleotides: :characters. Allowed are A, T, C, G, N and the IUPAC ambiguity codes.',
        'job_not_found' => 'That analysis is no longer available. Results expire after a while — run the analysis again.',
        'rate_limited' => 'Too many analyses in a short time. Wait :retry_after seconds and try again.',
        'internal_error' => 'The analysis service hit an unexpected problem. Try again, and if it keeps happening report the time it occurred.',
        'backend_unreachable' => 'The analysis service is not responding. It may still be starting up — wait a moment and try again.',
    ],

    'validation' => [
        'attribute' => 'FASTA file',
        'required' => 'Choose a FASTA file first.',
        'max' => 'The file must be smaller than :megabytes MB.',
        'extensions' => 'Only .fasta, .fa, .fna and .txt files are accepted.',
    ],

    'compiler' => [
        'attribute' => 'description',
        'required' => 'Write a description of the behaviour you want.',
        'too_short' => 'That is too short to compile. Describe a trigger and an output.',
        'too_long' => 'Keep the description under :characters characters.',
    ],

    'simulator' => [
        'preset_required' => 'Choose a network to simulate.',
        'preset_unknown' => 'That network is not one the simulator knows.',
        'cells_range' => 'The number of cells must be between :minimum and :maximum. Every reaction in every cell is simulated individually, so the ceiling is what keeps one request from occupying the service.',
        'minutes_range' => 'The duration must be between :minimum and :maximum minutes.',
        'seed_invalid' => 'A seed must be a whole number. Leave it blank to have one chosen for you.',
    ],

    'memory' => [
        'signal_required' => 'Choose a signal to record.',
        'signal_unknown' => 'That signal is not one this library has a sensor for.',
        'chassis_unknown' => 'That host is not one this tool designs for.',
        'hold_range' => 'The holding time must be between :minimum and :maximum hours.',
        'payload_too_long' => 'The cargo sequence is too long. Keep it under 60,000 characters.',
    ],

    'not_found' => [
        'title' => 'Result not found',
        'body' => 'This result has expired or the link is wrong. Uploading the file again will produce a new one.',
        'action' => 'Start a new analysis',
    ],
];
