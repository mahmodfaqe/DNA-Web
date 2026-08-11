<?php

return [
    'nav' => [
        'analysis' => 'Sequence analysis',
        'compiler' => 'Circuit compiler',
    ],

    'hero' => [
        'eyebrow' => 'BioCompiler',
        'title' => 'Describe a circuit in a sentence, get the DNA',
        'subtitle' => 'Write the behaviour you want in Kurdish, Arabic or English. The compiler works out the logic gates, picks the genetic parts, assembles the sequence — and tells you which parts of your sentence biology cannot deliver.',
        'label' => 'Describe the behaviour',
        'placeholder' => "If temperature exceeds 37 and lactose is present, produce green protein for 24 hours then self destruct.",
        'submit' => 'Compile',
        'submitting' => 'Compiling…',
        'examples' => 'Try an example',
        'characters' => ':count / :max characters',
    ],

    'examples' => [
        'basic' => 'If lactose is present, produce green protein.',
        'and_gate' => 'If temperature exceeds 37 and lactose is present, produce green protein for 24 hours then self destruct.',
        'or_gate' => 'If lactose or arabinose is present, produce red protein.',
        'inverter' => 'If lactose is absent, produce red protein.',
    ],

    'result' => [
        'title' => 'Compiled circuit',
        'source' => 'Your description',
        'expression' => 'Understood as',
        'failed' => 'This description could not be compiled',
        'failed_hint' => 'The diagnostics below name the part that did not map onto biology. Adjust the sentence and compile again.',
        'created' => 'Compiled',
        'language' => 'Language detected',
    ],

    'metrics' => [
        'units' => 'Transcriptional units',
        'length' => 'Total length',
        'parts' => 'Genetic parts',
        'resolved' => 'Sequence resolved',
        'warnings' => 'Design warnings',
    ],

    'logic' => [
        'title' => 'Logic',
        'subtitle' => 'How the compiler turned your sentence into gates.',
        'inputs' => 'Inputs',
        'gate' => 'Gate',
        'outputs' => 'Outputs',
        'no_gates' => 'No gates were synthesised.',
    ],

    'gates' => [
        'SENSOR' => 'Sensor',
        'AND' => 'AND gate',
        'OR' => 'OR gate',
        'NOT' => 'NOT gate',
        'OUTPUT' => 'Output',
        'TERMINAL' => 'Biocontainment',
    ],

    'sensors' => [
        'temperature' => 'Temperature',
        'lactose' => 'Lactose / IPTG',
        'arabinose' => 'Arabinose',
        'tetracycline' => 'Tetracycline / aTc',
        'oxygen' => 'Oxygen',
        'quorum' => 'Cell density',
        'ph_acid' => 'Acidity (pH)',
    ],

    'actuators' => [
        'gfp' => 'Green fluorescent protein',
        'rfp' => 'Red fluorescent protein',
        'yfp' => 'Yellow fluorescent protein',
        'lacz' => 'Beta-galactosidase',
        'self_destruct' => 'Self destruct',
    ],

    'construct' => [
        'title' => 'Assembled construct',
        'subtitle' => 'Each unit is a promoter, ribosome binding site, coding sequence and terminator, joined with standard assembly scars.',
        'unit' => 'Unit',
        'purpose' => 'Purpose',
        'length' => 'Length',
        'resolved' => 'Resolved',
        'map' => 'Part map',
        'download' => 'Download FASTA',
        'legend' => 'Part role',
    ],

    'roles' => [
        'promoter' => 'Promoter',
        'rbs' => 'RBS',
        'cds' => 'Coding sequence',
        'terminator' => 'Terminator',
        'tag' => 'Degradation tag',
        'scar' => 'Scar',
        'spacer' => 'Spacer',
    ],

    'provenance' => [
        'literal' => 'Real sequence',
        'placeholder' => 'Registry reference',
        'designed' => 'Designed by compiler',
        'literal_note' => 'Regulatory sequence included in full.',
        'placeholder_note' => 'Coding sequence not included — fetch it from the registry.',
        'designed_note' => 'Built by fusing two promoters; needs characterisation.',
    ],

    'parts' => [
        'title' => 'Parts list',
        'subtitle' => 'Every part used, with its registry entry.',
        'id' => 'Part',
        'name' => 'Description',
        'role' => 'Role',
        'provenance' => 'Sequence',
        'length' => 'Length',
        'registry' => 'Registry',
    ],

    'diagnostics' => [
        'title' => 'Diagnostics',
        'subtitle' => 'What the compiler could and could not do with your sentence.',
        'none' => 'No issues found.',
        'error' => 'Errors',
        'warning' => 'Warnings',
        'info' => 'Notes',
        'span' => 'From',
    ],

    /*
    |--------------------------------------------------------------------------
    | Diagnostic messages, keyed by the backend's diagnostic codes
    |--------------------------------------------------------------------------
    */

    'messages' => [
        'empty_input' => 'Nothing was written. Describe the behaviour you want.',
        'no_condition_found' => 'No condition was recognised. Start with a trigger, for example "if lactose is present" or "if temperature exceeds 37".',
        'no_output_found' => 'No output was recognised. Say what the cell should produce, for example "produce green protein".',
        'unknown_sensor' => 'There is no genetic part for the input ":sensor" in this library.',
        'unknown_actuator' => 'There is no coding sequence for the output ":actuator" in this library.',
        'missing_threshold' => 'A temperature condition needs a number, for example "above 37".',
        'too_many_conditions' => 'You gave :found conditions and the compiler handles :maximum. Split the design into two circuits.',

        'mixed_connectives' => 'Your sentence mixes ":connectives". Without brackets the order is ambiguous, so all conditions were combined with the first connective. Write two sentences to be certain.',
        'hybrid_promoter_uncharacterised' => 'The AND gate is built by fusing :inputs promoters into one hybrid. This produces a real sequence, but the response curve of a hybrid promoter cannot be predicted — it has to be measured.',
        'duration_not_encodable' => 'No DNA sequence means "for :requested_hours hours". Degradation tags such as :tag act over minutes, not hours. Timing on this scale has to come from the experiment: inducer dose, a delay cascade, or simply removing the inducer.',
        'temperature_threshold_fixed' => 'You asked for :requested °C, but the switch used here flips at around :actual °C. The threshold is a property of the :part protein, not a number the compiler can set. Choose a different thermosensitive variant if you need another temperature.',
        'kill_switch_placeholder' => 'The biocontainment effector was deliberately left unselected. Which gene kills the cell is a biosafety decision for you and your institution, not a default for a compiler to pick.',
        'inverter_adds_delay' => 'The NOT gate for ":sensor" works through a repressor, which adds an extra transcription and translation step. Expect the response to lag behind the input.',
        'metabolic_burden' => 'This design has :units transcriptional units. Expressing several proteins at once slows growth and can select for cells that lose the construct.',

        'cds_placeholder' => ':bases bases (:percent%) are coding sequence that this compiler does not include. Fetch each one from its registry entry, or paste your own.',
        'sequence_provenance' => 'Regulatory sequences come from a bundled snapshot of the iGEM Registry. Check every part against parts.igem.org before you rely on it.',
        'language_detected' => 'Read as :language.',
        'not_for_synthesis' => 'This is a teaching draft, not an order-ready construct. Any real build needs part verification, a compatible host and chassis, and institutional biosafety review.',
    ],

    'severity' => [
        'error' => 'Error',
        'warning' => 'Warning',
        'info' => 'Note',
    ],

    'languages' => [
        'ku' => 'Kurdish',
        'ar' => 'Arabic',
        'en' => 'English',
        'unknown' => 'Undetermined',
    ],
];
