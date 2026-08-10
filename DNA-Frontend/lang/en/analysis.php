<?php

return [
    'result' => [
        'title' => 'Analysis result',
        'file' => 'File',
        'checksum' => 'Checksum',
        'created' => 'Analysed',
        'records' => 'records',
    ],

    'metrics' => [
        'records' => 'Records',
        'total_bases' => 'Total bases',
        'avg_gc' => 'Mean GC',
        'avg_length' => 'Mean length',
        'variants' => 'Variants found',
        'unknown' => 'Uncalled bases',
        'gc_range' => 'GC range',
        'length_range' => 'Length range',
    ],

    'track' => [
        'title' => 'Composition tracks',
        'subtitle' => 'Each bar is one record, drawn to scale. Marks below a bar show where a variant sits.',
        'legend' => 'Base',
        'orientation_note' => 'Sequences always read 5′ → 3′ from left to right, in every language.',
        'variants_marked' => 'Variant positions relative to :reference',
        'no_variants' => 'No differences from the first record.',
    ],

    'table' => [
        'title' => 'Records',
        'subtitle' => 'One row per FASTA record.',
        'id' => 'ID',
        'description' => 'Description',
        'length' => 'Length',
        'gc' => 'GC',
        'tm' => 'Tm',
        'tm_method' => 'Tm method',
        'protein' => 'Longest ORF',
        'composition' => 'A / T / C / G / N',
        'ambiguous' => 'Ambiguous',
        'quality' => 'Quality',
        'view_protein' => 'View protein',
    ],

    'tm_methods' => [
        'wallace' => 'Wallace rule',
        'nearest_neighbour' => 'Nearest neighbour',
        'gc_empirical' => 'GC estimate',
        'none' => 'Not available',
    ],

    'tm' => [
        'estimate' => 'Estimate',
        'estimate_note' => 'Nearest-neighbour thermodynamics only describe short duplexes. Above :length bp the value shown is an empirical GC estimate, not a measurement.',
    ],

    'orf' => [
        'title' => 'Open reading frames',
        'none' => 'No open reading frame found.',
        'longest' => 'Longest ORF',
        'strand' => 'Strand',
        'frame' => 'Frame',
        'position' => 'Position',
        'length_aa' => 'Length',
        'count' => ':count found across six frames',
        'truncated' => 'Only the first :length bp of each record were scanned.',
        'forward' => 'Forward',
        'reverse' => 'Reverse',
        'protein_title' => 'Protein sequence',
        'protein_empty' => 'This record has no translatable open reading frame.',
    ],

    'codon' => [
        'title' => 'Most frequent codons',
        'codon' => 'Codon',
        'amino_acid' => 'Amino acid',
        'count' => 'Count',
        'frequency' => 'Frequency',
        'empty' => 'Too short to count codons.',
    ],

    'compare' => [
        'title' => 'Comparison',
        'subtitle' => 'Every record is aligned against the first one in the file.',
        'reference' => 'Reference',
        'against' => 'compared with',
        'identity' => 'Identity',
        'method' => 'Method',
        'aligned_length' => 'Aligned length',
        'total' => 'Variants',
        'none' => 'Upload two or more records to compare them.',
        'identical' => 'Identical to the reference.',
        'counts_title' => 'By type',
        'effects_title' => 'By effect on protein',
        'frameshift' => 'Frameshift events',
        'truncated' => 'Showing the first 500 variants of :total.',
    ],

    'methods' => [
        'global_alignment' => 'Global alignment',
        'positional_diff' => 'Positional difference',
        'positional_note' => 'These sequences exceed :length bp, so they were compared position by position without alignment. Insertions and deletions are not resolved in this mode.',
    ],

    'variant' => [
        'type' => 'Type',
        'position' => 'Position',
        'codon' => 'Codon',
        'change' => 'Change',
        'effect' => 'Effect',
        'length' => 'Length',
        'frameshift' => 'Frameshift',
        'transition' => 'Transition',
        'transversion' => 'Transversion',
        'inserted' => 'Inserted',
        'deleted' => 'Deleted',
    ],

    'variant_types' => [
        'substitution' => 'Substitution',
        'insertion' => 'Insertion',
        'deletion' => 'Deletion',
        'length_difference' => 'Length difference',
    ],

    'effects' => [
        'synonymous' => 'Synonymous',
        'missense' => 'Missense',
        'nonsense' => 'Nonsense',
        'stop_lost' => 'Stop lost',
        'unknown' => 'Undetermined',
    ],

    'quality' => [
        'clean' => 'Fully called',
        'has_ambiguity' => 'Contains ambiguous bases',
        'ambiguity_codes' => 'IUPAC codes present: :codes',
        'unknown_fraction' => ':percent% uncalled',
    ],

    'units' => [
        'bp' => 'bp',
        'aa' => 'aa',
        'celsius' => '°C',
        'dalton' => 'Da',
    ],

    'print' => [
        'generated' => 'Generated on :date',
        'source' => 'Source file',
        'note' => 'Produced by DNA Analytics.',
    ],
];
