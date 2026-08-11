<?php

return [
    'hero' => [
        'eyebrow' => 'BioNoise-Sim',
        'title' => 'Two identical cells, two different answers',
        'subtitle' => 'A promoter is one molecule and an mRNA is present in single copies, so gene expression is not a rate — it is a sequence of random events. This simulates that chemistry event by event, and measures how much of what a gene does was driven by a signal meant for something else.',
    ],

    'form' => [
        'network' => 'Network',
        'conditions' => 'Conditions',

        'induction' => 'Induction',
        'induction_hint' => 'How strongly the inducer drives the promoters it was chosen for.',

        'crosstalk' => 'Crosstalk',
        'crosstalk_hint' => 'How much the same signal reaches promoters it was never meant to touch.',

        'variability' => 'Cell-to-cell variability',
        'variability_hint' => 'How much cells differ from one another in ribosome content and size. Drawn once per cell and held for the whole run.',

        'resources' => 'Shared ribosomes',
        'resources_hint' => 'Let every gene translate from one pool. Genes with no regulatory connection still compete for it.',

        'cells' => 'Cells',
        'cells_hint' => '4 to 200. More cells, tighter numbers.',

        'duration' => 'Duration',
        'duration_hint' => '5 to 240 minutes of cell time.',

        'seed' => 'Seed',
        'seed_placeholder' => 'random',
        'seed_hint' => 'Leave blank for a fresh run. Enter a previous seed to reproduce it exactly.',

        'wait_warning' => 'A run simulates every reaction in every cell, so it takes a few seconds. Larger populations and longer runs take proportionally longer.',
        'submit' => 'Run simulation',
        'submitting' => 'Simulating…',
    ],

    /*
    |--------------------------------------------------------------------------
    | Networks
    |
    | Each preset is a question, not just a set of parameters, so each one says
    | what it answers. A user who picks by name alone has learnt nothing.
    |--------------------------------------------------------------------------
    */

    'presets' => [
        'independent' => [
            'name' => 'Two independent genes',
            'description' => 'Two identical reporters, wired to nothing and to each other. Plenty of ribosomes for both.',
            'question' => 'What does noise look like when nothing is wrong?',
        ],
        'crosstalk_pair' => [
            'name' => 'Signal and bystander',
            'description' => 'One gene is induced on purpose. The second is not meant to respond to anything — but the inducer partly reaches its promoter, and the first gene’s protein binds there too.',
            'question' => 'How much of the wrong gene’s output is your fault?',
        ],
        'dual_reporter' => [
            'name' => 'Two copies of one reporter',
            'description' => 'The Elowitz-Swain experiment: two identical reporters in the same cell, sharing every cell-wide fluctuation.',
            'question' => 'Is this gene noisy, or is this cell noisy?',
        ],
        'resource_competition' => [
            'name' => 'Competition for ribosomes',
            'description' => 'Two reporters and one heavily expressed protein. Nothing regulates anything; the only connection is the ribosome pool.',
            'question' => 'Can genes interfere with no wire between them?',
        ],
        'toggle_switch' => [
            'name' => 'Toggle switch',
            'description' => 'Two genes repressing each other: a one-bit memory made of DNA, holding its state with a few dozen molecules.',
            'question' => 'How long does a memory made of noise last?',
        ],
    ],

    'genes' => [
        'reporter_a' => 'Reporter A',
        'reporter_b' => 'Reporter B',
        'reporter_cfp' => 'Reporter CFP',
        'reporter_yfp' => 'Reporter YFP',
        'burden_protein' => 'High-expression protein',
        'repressor_laci' => 'Repressor LacI',
        'repressor_tetr' => 'Repressor TetR',
    ],

    'units' => [
        'cells' => 'cells',
        'min' => 'min',
        'copies' => 'copies',
    ],

    'result' => [
        'title' => 'Simulation',
        'seed' => 'seed',
        'run_another' => 'Run another',
        'failed' => 'This simulation could not be run',
        'failed_hint' => 'The diagnostics below say which setting the simulator would not accept.',
    ],

    'metrics' => [
        'noisiest' => 'Noisiest gene',
        'fano' => 'Fano factor',
        'fano_sub' => '1 would be pure chance',
        'crosstalk' => 'Wrongly driven',
        'crosstalk_sub' => 'share of transcripts',
        'availability' => 'Ribosomes free',
        'availability_sub' => 'after the load',
        'events' => 'Reactions simulated',
    ],

    'trajectories' => [
        'title' => 'What each cell did',
        'subtitle' => 'The heavy line is the population average. The faint lines under it are three individual cells.',
        'mean' => 'mean',
        'burst' => 'burst',
        'legend' => 'The average is smooth because averages are smooth. The individual cells are not: each one wanders well clear of the population and stays there for tens of minutes, because a protein that takes half an hour to turn over keeps the memory of the burst that made it for about that long. Two of these cells are genetically identical and sitting in the same medium. A deterministic model replaces all of this with one line.',
    ],

    'charts' => [
        'trajectory_alt' => 'Protein copies over time for :gene, showing the population average, its spread, and three individual cells.',
        'distribution_alt' => 'Distribution of protein copies per cell for :gene.',
        'burn_in' => 'settling',
        'minutes' => 'minutes',
        'mean' => 'mean',
        'copies_per_cell' => 'protein copies per cell',
    ],

    'distributions' => [
        'title' => 'The population, not the average',
        'subtitle' => 'Every cell counted after the settling window. A deterministic model reports one number for this gene; the width of these shapes is what that number leaves out.',
    ],

    'crosstalk' => [
        'title' => 'Crosstalk',
        'subtitle' => 'Where each gene’s transcripts came from, and how far that shows up in what the genes do.',
        'attribution_title' => 'Which signal opened the promoter',
        'transcripts' => 'transcripts',
        'cognate' => 'Its own signal',
        'crosstalk' => 'Someone else’s signal',
        'leak' => 'Leak, no signal at all',
        'gene' => 'Gene',

        'measured' => 'Correlation as measured',
        'measured_note' => 'What a microscope would report. Two genes with no connection at all still correlate here, because a cell rich in ribosomes makes more of both.',
        'partial' => 'After removing cell-to-cell variability',
        'partial_note' => 'The same data with the shared cellular factor divided out. What is left is what the wiring and the competition actually did — reading “:measured” as evidence of a regulatory link is one of the easiest mistakes to make with single-cell data.',

        'opposed' => 'move apart',
        'independent' => 'unrelated',
        'together' => 'move together',
    ],

    'budget' => [
        'title' => 'Where the noise came from',
        'subtitle' => 'Noise measured as CV squared adds up across independent sources, which is the only reason it can be split like this.',
        'floor' => 'Counting molecules',
        'bursting' => 'Burst size',
        'extrinsic' => 'Cell-to-cell',
        'promoter' => 'Promoter switching',
        'coupling' => 'Other genes',
        'coupling_reduces' => 'Coupling to the other genes made this one :percent% quieter rather than noisier — the interaction is acting as negative feedback.',
        'no_control' => 'Nothing in this network couples the genes, so no control run was needed and the "other genes" share is zero by construction.',
    ],

    'decomposition' => [
        'title' => 'Is it the gene or the cell?',
        'subtitle' => 'Two identical reporters in one cell can answer this, and only two identical reporters can.',
        'intrinsic' => 'Intrinsic',
        'intrinsic_note' => 'Randomness in this gene’s own reactions. It makes the two reporters differ from each other inside the same cell, which is how it can be seen at all.',
        'extrinsic' => 'Extrinsic',
        'extrinsic_note' => 'Everything the two reporters share: ribosome content, cell size, growth rate. It moves them together, so it cannot be reduced by fixing the gene.',
        'method' => 'Measured from :first and :second, which share every parameter. The split is Elowitz, Levine, Siggia and Swain (2002).',
    ],

    'switching' => [
        'title' => 'The memory failing',
        'subtitle' => 'In the rate equations this circuit holds its state for ever. In a cell it does not.',
        'flips' => 'Spontaneous flips',
        'cells' => 'Cells that flipped',
        'dwell' => 'Mean time before flipping (min)',
        'note' => 'Nothing was done to these cells. A large enough burst of the repressed protein is enough to overcome the repressor holding it down, and the state inverts. This is why a memory circuit built from low copy numbers has a half-life, and why the number of molecules holding the state is a design parameter and not an implementation detail.',
    ],

    'table' => [
        'title' => 'The numbers',
        'subtitle' => 'Noise figures carry about ±:percent% from this run’s sample size.',
        'gene' => 'Gene',
        'protein' => 'Protein per cell',
        'mrna' => 'mRNA per cell',
        'fano' => 'Fano',
        'predicted' => 'Predicted',
        'burst' => 'Burst size',
        'independent' => 'Independent samples',
        'note' => '"Predicted" is the Fano factor theory gives for this gene on its own, assuming its mRNA arrives at random: 1 + b/(1 + d_p/d_m). The gap between it and the measured value is everything that model leaves out — the promoter switching on and off, and every coupling in the network. "Independent samples" is not the number of readings taken: a protein that takes half an hour to turn over is barely changed a second later, so densely sampling one cell produces many numbers and little extra information.',
    ],

    'conditions' => [
        'title' => 'What was run',
        'subtitle' => 'Kept with the result. Every run is random, so a figure without its seed points at nothing.',
        'on' => 'Shared',
        'off' => 'Unlimited',
    ],

    'diagnostics' => [
        'title' => 'Reading this run',
        'subtitle' => 'Which numbers to trust, and what the model does not include.',
        'none' => 'Nothing worth flagging.',
        'error' => 'Errors',
        'warning' => 'Warnings',
        'info' => 'Notes',
        'span' => 'Gene',
    ],

    'severity' => [
        'error' => 'Error',
        'warning' => 'Warning',
        'info' => 'Note',
    ],

    /*
    |--------------------------------------------------------------------------
    | Diagnostic messages, keyed by the backend's diagnostic codes
    |--------------------------------------------------------------------------
    */

    'messages' => [
        'unknown_preset' => 'There is no network called ":preset". Available networks: :available.',

        'cells_clamped' => 'You asked for :requested cells and the simulator runs between :minimum and :maximum, so it ran :used. Every reaction in every cell is simulated individually, and the ceiling is what keeps one request from occupying the service.',
        'duration_clamped' => 'You asked for :requested minutes and the range is :minimum to :maximum, so it ran :used.',
        'run_truncated' => ':cells of :total cells reached the reaction budget before the end of the run and stopped early. Their last state was held for the remaining time, so the late part of the average is thinner than it looks. Shorten the run or reduce the number of cells.',

        'not_at_steady_state' => 'Gene :gene drifted :drift% between the first and second half of the measured window, and sampling alone would explain only :expected%. The average is therefore describing a trend rather than a steady state. Run for longer.',
        'imprecise' => 'The noise figures carry roughly ±:percent%, because this run produced about :independent genuinely independent observations. That is not the number of samples: a protein takes tens of minutes to turn over, so a cell measured a second later is nearly the same cell. More cells help most; a longer run helps once it exceeds a protein lifetime. You have :cells cells over :minutes minutes.',
        'precision' => 'The noise figures carry roughly ±:percent%, from about :independent independent observations. Two runs differing by less than that are not different.',

        'low_copy_number' => 'Gene :gene averages :mean copies per cell. Below about thirty, the discreteness matters: the difference between four molecules and five is 25%, the distribution is visibly stepped, and any intuition built on smooth averages stops applying.',
        'crosstalk_dominates' => ':percent% of gene :gene’s transcripts were produced by a promoter that a foreign signal opened. This is not leakage and not noise — it is the circuit responding to an input that was never addressed to it.',
        'leak_dominates' => ':percent% of gene :gene’s transcripts came from a promoter that was switched off. A promoter that is off is not silent, and at low induction the leak can be most of the output.',
        'resources_limiting' => 'Only :percent% of translation capacity was free on average, so genes lost about :lost% of their output to competition for ribosomes. At this level the genes are coupled through the shared pool whether or not anything connects them.',

        'no_switching_observed' => 'No cell flipped state in :minutes minutes. That is a result rather than a failure — it means the barrier between the two states is high enough that flipping is rare on this timescale. Run for longer to estimate how rare.',
        'switching_observed' => ':switches spontaneous flips across :cells cells, giving a mean of about :dwell minutes in a state before the memory inverts. Nothing was done to these cells; the noise did it.',

        'noise_exceeds_theory' => 'Gene :gene measured a Fano factor of :measured against the :predicted that theory gives for the gene on its own — :ratio times higher. The two-stage prediction assumes mRNA arrives at random; the excess is the promoter switching on and off, plus whatever the rest of the network is doing to this gene.',
        'control_ensemble' => 'The same :cells cells were simulated a second time with the crosstalk and the shared ribosome pool removed, and nothing else changed. Subtracting one run from the other is how the "other genes" share of the noise was measured rather than guessed.',
        'seed_recorded' => 'This run used seed :seed. Entering it again reproduces these numbers exactly.',

        'well_mixed_assumption' => 'The cell is modelled as one well-mixed compartment. Molecules have no position, nothing diffuses, nothing is bound to the membrane or localised at a pole, and every reaction can happen anywhere with equal probability.',
        'no_cell_division' => 'Cells do not grow or divide. Dilution is folded into the protein decay rate, so there is no cell cycle and no partitioning of molecules between daughters — which is itself a real source of noise this model does not have.',
        'parameters_illustrative' => 'The rate constants are order-of-magnitude values for E. coli in exponential growth, not measurements of your construct. Use this to understand which effects matter and how they behave, not to predict what your plasmid will do.',
    ],

    'export' => [
        'minutes' => 'minutes',
        'protein_mean' => 'protein mean',
        'protein_sd' => 'protein SD',
        'mrna_mean' => 'mRNA mean',
    ],
];
