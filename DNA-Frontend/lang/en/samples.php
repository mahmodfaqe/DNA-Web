<?php

return [

    'heading' => 'Try it on something',
    'subtitle' => 'Six sequences, each built so that one thing becomes visible. Open one and the question comes with it.',
    'load' => 'Load this',
    'download' => 'Download',
    'question' => 'The question',
    'looking_for' => 'What to look for',

    'gc_skew' => [
        'title' => 'A statistic that means nothing base by base',
        'question' => 'GC skew is (G − C) ÷ (G + C). Read it at any single position and it tells you nothing. Read it across this sequence and it changes sign halfway through. Why would a real chromosome do that, and what is at the place where it flips?',
        'looking_for' => 'The skew value, and the fact that the two halves have almost identical GC content while being completely different sequences.',
    ],

    'variants' => [
        'title' => 'Three changes, three different disasters',
        'question' => 'Two records: a gene and the same gene with three single-base substitutions. All three are one base. One does nothing at all, one changes a single amino acid, and one truncates the protein. Which is which, and what makes the third so much worse than the second?',
        'looking_for' => 'The consequence column, and the codon each change lands in. Notice that the harmless one changed a base too.',
    ],

    'reverse_orf' => [
        'title' => 'The gene nobody was looking at',
        'question' => 'The longest reading frame in this sequence runs right to left. If the tool only read the strand you were given, it would report the second-longest and you would never know. Why does a reading-frame search have to look at six frames rather than three?',
        'looking_for' => 'The strand and frame of the longest ORF, and how much shorter the best forward-strand frame is.',
    ],

    'ambiguity' => [
        'title' => 'What the sequencer would not commit to',
        'question' => 'Five positions in this read are ambiguity codes rather than bases — the machine saw two peaks and refused to choose. What can this tool still tell you about the sequence, and what has it quietly stopped being able to say?',
        'looking_for' => 'The warning about ambiguous bases, and which statistics change when a position is N rather than a real base.',
    ],

    'cloning_trap' => [
        'title' => 'A week of cloning that produces nothing',
        'question' => 'You want to amplify this gene and clone it into a vector using EcoRI and XhoI. The form is already filled in. Run it, read the warnings, and answer this: if you had not read them, what exactly would you have seen on the gel after the digest, and how long would it have taken to work out why?',
        'looking_for' => 'One warning among several. It is not the loudest one on the page, and it is the only one that would have cost you the experiment.',
    ],

    'plasmid_topology' => [
        'title' => 'One molecule, two answers',
        'question' => 'Analyse this sequence as written, then tick "circular molecule" and analyse it again. The sequence has not changed and the enzyme cuts it once either way. Count the bands each time. Why are the answers different, and which one describes a plasmid?',
        'looking_for' => 'The fragment list in both runs. A circle cut once is linearised; a line cut once is halved.',
    ],

];
