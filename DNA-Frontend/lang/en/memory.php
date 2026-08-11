<?php

return [
    'hero' => [
        'eyebrow' => 'DeepBio-Memory Architect',
        'title' => 'Where should a cell keep a memory?',
        'subtitle' => 'A bit can live in protein concentrations or in the DNA sequence itself, and everything else follows from that one choice. Describe what you need to record and for how long, and this compares both, says which one your conditions favour, and builds it.',
    ],

    'form' => [
        'recording' => 'What to record',
        'signal' => 'Signal',
        'signal_hint' => 'The chemical input to be remembered. Each brings its promoter’s leak with it, and the leak decides most of the answer.',
        'chassis' => 'Host cell',
        'chassis_hint' => 'Sets the division time, and therefore how fast a protein-held memory is diluted.',

        'demands' => 'What it has to do',
        'hold' => 'Hold for (hours)',
        'hold_hint' => 'How long the memory must survive after the signal is gone.',
        'exposure' => 'Signal present (minutes)',
        'exposure_hint' => 'How long the input is available to write with.',
        'strength' => 'Promoter strength',
        'strength_hint' => 'Stronger writes faster and holds harder, and leaks more of everything.',

        'reversible' => 'Must be erasable',
        'reversible_hint' => 'A one-way integrase cannot be reversed without a second enzyme. Ticking this excludes it and costs a third transcriptional unit.',
        'plasmid' => 'On a plasmid',
        'plasmid_hint' => 'Convenient, and lost from a fraction of daughters at every division. Untick for a genomic integration.',

        'payload' => 'Cargo and enzyme (optional)',
        'payload_hint' => 'The DNA between the att sites — the piece the recombinase inverts. Leave it empty and a constitutive promoter is used, which makes the stored bit directly readable. Paste raw sequence or FASTA; it is analysed in both orientations.',
        'payload_placeholder' => 'ATGCGT… or a FASTA record',
        'recombinase' => 'Recombinase',

        'note' => 'Both architectures are modelled every time, whichever one wins.',
        'submit' => 'Compare and build',
        'submitting' => 'Modelling…',
    ],

    'signals' => [
        'lactose' => 'Lactose / IPTG',
        'arabinose' => 'Arabinose',
        'tetracycline' => 'Tetracycline / aTc',
        'temperature' => 'Temperature',
        'oxygen' => 'Oxygen',
        'quorum' => 'Cell density',
        'ph_acid' => 'Acidity (pH)',
    ],

    'chassis' => [
        'ecoli' => 'E. coli',
        'bsubtilis' => 'B. subtilis',
        'yeast' => 'S. cerevisiae (yeast)',
    ],

    'recombinases' => [
        'bxb1' => 'Bxb1 integrase',
        'phic31' => 'phiC31 integrase',
    ],

    'units' => [
        'hours' => 'h',
        'min' => 'min',
    ],

    /*
    |--------------------------------------------------------------------------
    | The architectures
    |
    | Each carries the reason it would be chosen, because a verdict the reader
    | cannot interrogate is a verdict they have to take on trust.
    |--------------------------------------------------------------------------
    */

    'architectures' => [
        'recombinase' => [
            'name' => 'Recombinase register',
            'why' => 'The bit is a physical inversion of DNA between two att sites. Nothing has to be expressed to hold it, so it is copied by the replisome along with the rest of the chromosome and costs nothing to keep — it survives division because it is not being remembered, it is being carried. The price is that writing it is a one-way chemical reaction: it cannot be erased without a second enzyme, and any leaky integrase writes it when nobody asked.',
        ],
        'recombinase_reversible' => [
            'name' => 'Recombinase register, erasable',
            'why' => 'The same DNA inversion, plus a directionality factor on its own inducible promoter that turns the integrase around and flips the register back. It keeps the retention of a DNA-encoded bit and buys back reversibility — at the cost of a third transcriptional unit and a second promoter that can also leak.',
        ],
        'toggle' => [
            'name' => 'Toggle switch',
            'why' => 'The bit is which of two mutually repressing proteins is winning. It can be written and erased with equal ease, and it can be read continuously without disturbing it. But it is held only for as long as both genes keep being expressed: growth dilutes it, it costs energy for every minute it is remembered, and a large enough expression burst can flip it with nothing acting on the cell at all.',
        ],
    ],

    'result' => [
        'title' => 'Memory design',
        'recommended' => 'Recommended',
        'hold' => 'hold',
        'exposure' => 'signal',
        'design_another' => 'Design another',
        'close_call' => 'This is close. :other scored within :gap points, so read this as a preference rather than a conclusion — a parameter you know better than the model does could reverse it.',
        'refused' => 'This design could not be built',
        'refused_hint' => 'The diagnostics below say what the tool would not do, and why.',
    ],

    'metrics' => [
        'retention' => 'Retention',
        'false_writes' => 'Writes itself',
        'write_time' => 'Write time (min)',
        'stored_in' => 'Bit lives in',
        'in_dna' => 'DNA',
        'in_protein' => 'Protein',
        'length' => 'Total length',
    ],

    'compare' => [
        'title' => 'Both architectures, on the conditions you gave',
        'subtitle' => 'The one that lost is shown in full. A recommendation is only checkable if what it beat is visible.',
        'criterion' => 'Criterion',
        'chosen' => 'chosen',

        'retention' => 'Retention',
        'retention_note' => 'Is the bit still there at the end of the holding window?',
        'fidelity' => 'Fidelity',
        'fidelity_note' => 'Share of an uninduced population that does not write itself.',
        'speed' => 'Write speed',
        'speed_note' => 'Did it finish writing while the signal was still present?',
        'cost' => 'Cheapness',
        'cost_note' => 'Fewer transcriptional units running is less burden carried.',

        'survives_division' => 'Survives division',
        'survives_division_note' => 'Whether holding the bit requires continuous expression.',
        'yes_dna' => 'Copied with the DNA',
        'needs_expression' => 'Needs re-expression',

        'erasable' => 'Erasable',
        'erasable_note' => 'Can the memory be returned to its unwritten state?',
        'yes' => 'Yes',
        'no' => 'No',

        'total' => 'Overall',
        'excluded' => [
            'not_reversible' => 'Cannot be erased',
            'never_written' => 'Never wrote',
        ],
        'weights' => 'Weighted retention 45%, fidelity 30%, write speed 15%, burden 10%. Retention carries the most because a memory that does not remember has failed at its only job, however cheap or fast it is. An architecture that cannot meet a stated requirement is excluded outright rather than ranked low — a circuit that cannot be erased is not a worse answer to "it must be erasable", it is the wrong one.',
    ],

    'dynamics' => [
        'title' => 'What the model did',
        'subtitle' => 'The signal goes on, then it goes away. Most circuit descriptions only show the first half.',
        'write' => 'While the signal is present',
        'hold' => 'After the signal is gone',
        'legend' => 'Both curves are the share of the memory that is set, so they can share an axis. The dashed line is half — below it the memory is not readable as written.',
    ],

    'orientation' => [
        'title' => 'Which way round to build the register',
        'subtitle' => 'Inverting DNA does not preserve what it means. The −35 and −10 elements a polymerase looks for are not palindromic, so a stretch that is silent on one strand can read as a promoter on the other.',
        'forward' => 'Forward',
        'reverse' => 'Reverse',
        'either' => 'Either',
        'risk' => 'risk',
        'promoters_outward' => 'Cryptic promoters firing outward',
        'promoters_inward' => 'Cryptic promoters firing inward',
        'terminators' => 'Unintended terminators',
        'repeats' => 'Repeats',
        'explanation' => 'A cryptic promoter firing outward — past the att site into whatever the register controls — is the worst thing on this list: it makes the construct read as written when it is not, which downstream is indistinguishable from the memory having been set. One firing inward is milder, but runs antisense to the integrase. Repeats and GC are the same either way round and cannot decide between the two.',
        'default_payload' => 'No cargo was supplied, so the register carries a constitutive promoter. It is found by the scan, which is the point: the stored bit is that promoter pointing at the output or away from it.',
    ],

    'construct' => [
        'title' => 'The construct',
        'subtitle' => 'Regulatory parts and recombination sites in full; coding sequences referenced by ID and drawn to scale as placeholders.',
        'resolved' => 'resolved',
        'download' => 'Download FASTA',
    ],

    'roles' => [
        'promoter' => 'Promoter',
        'rbs' => 'RBS',
        'cds' => 'Coding sequence',
        'terminator' => 'Terminator',
        'att' => 'att site',
        'payload' => 'Cargo',
        'spacer' => 'Spacer',
        'scar' => 'Scar',
    ],

    'purposes' => [
        'WRITE' => 'Writes the memory',
        'STORE' => 'Holds the memory',
        'READ' => 'Reads the memory',
        'ERASE' => 'Erases the memory',
    ],

    'parts' => [
        'title' => 'Parts list',
        'subtitle' => 'Every part used, with its registry entry where it has one.',
        'difficult' => 'Difficult to synthesise',
    ],

    'diagnostics' => [
        'title' => 'Reading this design',
        'subtitle' => 'What the model assumed, what it would not do, and what to check before ordering.',
        'none' => 'Nothing worth flagging.',
        'error' => 'Errors',
        'warning' => 'Warnings',
        'info' => 'Notes',
        'span' => 'Architecture',
    ],

    'severity' => [
        'error' => 'Error',
        'warning' => 'Warning',
        'info' => 'Note',
    ],

    'messages' => [
        'unknown_signal' => 'There is no sensor for ":signal" in this library. Available: :available.',
        'unknown_chassis' => 'There is no host called ":chassis". Available: :available.',
        'chassis_parts_unavailable' => 'Every promoter, ribosome binding site and terminator in this library is bacterial. None of them function in :chassis — its polymerase does not read a sigma-70 promoter and its ribosomes do not use a Shine-Dalgarno sequence — and the recombinase would additionally need a nuclear localisation signal it does not carry here. Emitting a sequence anyway would produce something that looks buildable and cannot work.',
        'signal_not_in_chassis' => 'The :signal sensor is not characterised in :chassis. It is characterised in: :available. A promoter that has not been measured in your host is a parameter you do not have, not a parameter equal to the one you do.',
        'no_architecture_meets_requirements' => 'Neither architecture met the requirements as stated (:reason). Relax the holding time, lengthen the signal, or raise the promoter strength.',

        'recommendation_is_close' => ':first and :second finished :gap apart. That is inside the noise of the weighting, so treat this as a preference rather than a result — the tie-breaker is a fact about your experiment that the model does not have.',
        'reversibility_costs_retention' => 'Erasability was required, so the design carries a directionality factor on its own inducible promoter. That is a third transcriptional unit and a second promoter that can leak — and a leaky eraser un-writes the memory as surely as a leaky writer writes it.',
        'toggle_not_bistable' => 'At this promoter strength the toggle has only :states steady state. Two states that have merged into one are not a memory: the circuit relaxes back the moment the signal stops, however convincing the write phase looks.',
        'memory_lost_to_dilution' => 'The recommended design holds the bit in protein, so it has to be re-expressed continuously for :generations generations at :doubling minutes each. Anything that stops expression — stationary phase, a carbon shift, a plasmid lost — erases it.',
        'leak_writes_without_signal' => 'The :architecture design writes itself in :percent% of an uninduced population over :hours hours, from a promoter that is :leak% active with no inducer at all. Because the flip is written into DNA, that false write is permanent: those cells will read as though they were signalled for the rest of the experiment. A tighter sensor is worth more here than any change to the register.',
        'plasmid_segregation_loss' => 'On a :copies-copy plasmid, random segregation loses the construct from about :percent% of the population over :hours hours. Unlike everything else in this model, that does not corrupt the memory — it takes the memory away entirely, along with the cell’s ability to be measured.',
        'write_too_slow' => 'The :architecture design needs about :needed minutes to write and the signal is present for :available. It will be caught mid-write, which for a population means a mixture of written and unwritten cells rather than a clean answer.',
        'integrase_burden' => 'This design runs :units transcriptional units. Expressing a recombinase and its directionality factor alongside everything else slows growth, and a slower-growing cell is one that gets outcompeted by any cell that has lost the construct.',

        'cryptic_promoter_in_register' => 'The scan found :count promoter-like sequences reading outward from the register, the strongest matching consensus at :score%. In the wrong orientation these transcribe past the att site into whatever the register controls, which downstream is indistinguishable from the memory having been written.',
        'terminator_in_register' => ':count terminator-like structures were found inside the register. One of these in the path of the intended transcript truncates it, and the construct reads as failed rather than as unwritten.',
        'orientation_asymmetry' => 'The two orientations scored within :difference of each other, so the sequence does not decide between them. Choose on the biology instead — usually by pointing the cargo away from anything you do not want transcribed.',
        'homopolymer_run' => 'A homopolymer run (:run) at position :position. Runs like this are hard to synthesise accurately and slip during replication, which over enough generations changes the register without anything having acted on it.',
        'synthesis_difficult' => 'A synthesis provider is likely to flag this construct (:reasons). GC is :gc% and the longest homopolymer is :longest bases. Expect it to be slower, dearer, or declined.',

        'orientation_chosen' => 'The register is built in the :orientation orientation, which scored :difference lower on transcriptional hazards than the alternative.',
        'retention_estimate' => 'Retention for :architecture: :hours hours, about :generations generations at this growth rate.',
        'noise_estimate_is_analytic' => 'The retention figure for a toggle is an order-of-magnitude estimate, not a measurement. It comes from asking how large an expression burst would have to be to cross the barrier between the two states — :barrier copies, against a typical burst of :burst — and how often a burst that large arrives.',
        'simulate_this' => 'The third tab measures that flipping rate directly instead of estimating it, by simulating the chemistry and counting the flips. Run the toggle switch network there to check this number against a population.',

        'att_sites_must_be_verified' => 'The :recombinase att sites are included as literal sequence, and their central dinucleotide (:core) is the entire mechanism of directionality. A single wrong base there does not break the construct loudly — it builds a recombinase that writes in both directions, which is not a memory at all. Check both sites against the registry before ordering.',
        'recombinase_cds_placeholder' => 'The integrase coding sequence (:length bp) is referenced by ID, not included. Transcribing a sequence that long from memory risks a silent single-base error that produces a construct which looks right and does not work.',
        'deterministic_model' => 'These are rate equations, so they describe an average cell rather than any real one. They cannot tell you what fraction of a population ends up in each state, and for a bistable circuit they answer "how long does it hold?" with "for ever" — which is the specific thing they get wrong.',
        'parameters_illustrative' => 'Rate constants and promoter leakiness are order-of-magnitude literature values, not measurements of your parts. Use this to understand which effect dominates, not to predict what your construct will do.',
        'not_for_synthesis' => 'This is a teaching draft. Any real build needs part verification, a compatible host, and institutional biosafety review.',
    ],
];
