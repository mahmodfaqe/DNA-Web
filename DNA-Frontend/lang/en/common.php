<?php

return [
    'app' => [
        'name' => 'DNA Analytics',
        'tagline' => 'FASTA sequence analysis',
        'description' => 'Upload a FASTA file to measure base composition, GC content, melting temperature and open reading frames, and to compare sequences variant by variant.',
    ],

    'nav' => [
        'new_analysis' => 'New analysis',
        'language' => 'Language',
        'skip_to_content' => 'Skip to content',
    ],

    'hero' => [
        'eyebrow' => 'Sequence analysis',
        'title' => 'Read what is actually in your FASTA file',
        'subtitle' => 'Composition, thermodynamics and reading frames for every record, plus an aligned comparison that tells substitutions apart from insertions and deletions.',
        'dropzone' => 'Choose a file or drop it here',
        'dropzone_hint' => 'FASTA, FA, FNA or TXT — up to :megabytes MB',
        'selected' => 'Selected',
        'submit' => 'Run analysis',
        'submitting' => 'Analysing…',
        'example_title' => 'What a FASTA file looks like',
        'example_note' => 'Each record starts with a header line beginning with “>”, followed by the sequence.',
    ],

    'recent' => [
        'title' => 'Recent analyses',
        'empty' => 'Nothing analysed yet.',
        'records' => ':count records',
        'open' => 'Open',
    ],

    'actions' => [
        'csv' => 'CSV',
        'json' => 'JSON',
        'print' => 'Print',
        'copy' => 'Copy',
        'copied' => 'Copied',
        'close' => 'Close',
        'back' => 'Back',
        'new_analysis' => 'Analyse another file',
    ],

    'footer' => [
        'retention' => 'Uploaded results are deleted automatically after :days days.',
        'api' => 'API documentation',
    ],
];
