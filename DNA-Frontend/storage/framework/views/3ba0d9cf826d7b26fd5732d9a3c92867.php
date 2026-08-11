<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['budget', 'label']));

foreach ($attributes->all() as $__key => $__value) {
    if (in_array($__key, $__propNames)) {
        $$__key = $$__key ?? $__value;
    } else {
        $__newAttributes[$__key] = $__value;
    }
}

$attributes = new \Illuminate\View\ComponentAttributeBag($__newAttributes);

unset($__propNames);
unset($__newAttributes);

foreach (array_filter((['budget', 'label']), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<?php
    // Noise expressed as CV squared is additive over independent sources, which
    // is the only reason this bar can be drawn at all: each segment is a real
    // share of the total, not a proportion invented for the picture.
    //
    // The four internal sources are an ordered sequence — the irreducible floor
    // first, then what the gene's own machinery adds on top — so they get one
    // hue getting darker rather than four unrelated colours. Coupling from other
    // genes is not part of that sequence and is the thing being measured, so it
    // takes the accent colour this design reserves for "look here".
    $sources = [
        ['key' => 'floor', 'colour' => 'var(--color-brand-100)', 'ink' => 'var(--color-ink-700)'],
        ['key' => 'bursting', 'colour' => 'var(--color-brand-200)', 'ink' => 'var(--color-ink-700)'],
        ['key' => 'extrinsic', 'colour' => 'var(--color-brand-400)', 'ink' => '#fff'],
        ['key' => 'promoter', 'colour' => 'var(--color-brand-600)', 'ink' => '#fff'],
    ];

    $coupling = (float) ($budget['coupling'] ?? 0);
    $positive = [];
    foreach ($sources as $source) {
        $positive[$source['key']] = max(0.0, (float) ($budget[$source['key']] ?? 0));
    }

    // A negative coupling term means the couplings made this gene *quieter*.
    // It cannot be drawn as a slice of the bar, so the bar shows what the gene
    // would have been without it and the reduction is stated in words below.
    $drawnTotal = array_sum($positive) + max(0.0, $coupling);
    $scale = $drawnTotal > 0 ? $drawnTotal : 1;
?>

<div>
    <div class="mb-1.5 flex items-baseline justify-between gap-3">
        <span class="text-xs font-bold text-ink-900"><?php echo e($label); ?></span>
        <span class="ltr-data text-[0.6875rem] text-ink-400">
            CV² <?php echo e(number_format((float) ($budget['total'] ?? 0), 4)); ?>

        </span>
    </div>

    <div class="track flex h-6 w-full gap-0.5 overflow-hidden rounded-md">
        <?php $__currentLoopData = $sources; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $source): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <?php $value = $positive[$source['key']]; ?>
            <?php if($value > 0): ?>
                <?php $percent = $value / $scale * 100; ?>
                <div class="flex items-center justify-center overflow-hidden text-[9px] font-semibold"
                     style="width: <?php echo e(round($percent, 3)); ?>%; background: <?php echo e($source['colour']); ?>; color: <?php echo e($source['ink']); ?>;"
                     title="<?php echo e(__('simulator.budget.' . $source['key'])); ?> — <?php echo e(round($percent)); ?>%">
                    <?php if($percent > 14): ?>
                        <span class="truncate px-1"><?php echo e(round($percent)); ?>%</span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>

        <?php if($coupling > 0): ?>
            <?php $percent = $coupling / $scale * 100; ?>
            <div class="flex items-center justify-center overflow-hidden text-[9px] font-semibold text-white"
                 style="width: <?php echo e(round($percent, 3)); ?>%; background: var(--color-signal-500);"
                 title="<?php echo e(__('simulator.budget.coupling')); ?> — <?php echo e(round($percent)); ?>%">
                <?php if($percent > 14): ?>
                    <span class="truncate px-1"><?php echo e(round($percent)); ?>%</span>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <?php if($coupling < -0.0001): ?>
        <p class="mt-1.5 text-[0.6875rem] text-good-600">
            <?php echo e(__('simulator.budget.coupling_reduces', [
                'percent' => number_format(abs($coupling) / max($scale, 0.0001) * 100, 1),
            ])); ?>

        </p>
    <?php endif; ?>
</div>
<?php /**PATH /Users/macbookshop/Desktop/Projects/Developments/DNA-Web/DNA-Frontend/resources/views/components/noise-budget.blade.php ENDPATH**/ ?>