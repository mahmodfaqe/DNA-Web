<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['gene', 'variants' => [], 'maxLength' => null, 'isReference' => false]));

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

foreach (array_filter((['gene', 'variants' => [], 'maxLength' => null, 'isReference' => false]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<?php
    $composition = $gene['base_composition'] ?? [];
    $length = max(1, (int) ($gene['length'] ?? 1));
    $scale = max(1, (int) ($maxLength ?: $length));

    // Bar width is proportional to the longest record in the file, so two
    // sequences of different length are visibly different lengths rather than
    // both being stretched to the full width.
    $relative = round($length / $scale * 100, 3);

    $order = [
        'A' => 'var(--color-base-a)',
        'T' => 'var(--color-base-t)',
        'C' => 'var(--color-base-c)',
        'G' => 'var(--color-base-g)',
    ];

    $unknown = (int) ($composition['N'] ?? 0) + (int) ($composition['ambiguous'] ?? 0);
    $segments = [];
    foreach ($order as $base => $colour) {
        $count = (int) ($composition[$base] ?? 0);
        if ($count > 0) {
            $segments[] = ['label' => $base, 'colour' => $colour, 'percent' => $count / $length * 100];
        }
    }
    if ($unknown > 0) {
        $segments[] = ['label' => 'N', 'colour' => 'var(--color-base-n)', 'percent' => $unknown / $length * 100];
    }
?>

<div class="track">
    <div class="flex items-baseline justify-between gap-3 pb-1.5">
        <span class="ltr-data text-xs font-bold text-ink-900"><?php echo e($gene['id'] ?? ''); ?></span>
        <span class="ltr-data text-[0.6875rem] text-ink-400">
            <?php echo e(number_format($length)); ?> <?php echo e(__('analysis.units.bp')); ?> · GC <?php echo e($gene['gc_content'] ?? 0); ?>%
        </span>
    </div>

    
    <div class="h-3.5 overflow-hidden rounded-[3px] bg-paper" style="width: <?php echo e($relative); ?>%; min-width: 12px;">
        <div class="flex h-full w-full">
            <?php $__currentLoopData = $segments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $segment): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <div style="width: <?php echo e(round($segment['percent'], 3)); ?>%; background: <?php echo e($segment['colour']); ?>;"
                     title="<?php echo e($segment['label']); ?> — <?php echo e(round($segment['percent'], 1)); ?>%"></div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>
    </div>

    
    <?php if($isReference): ?>
        <div class="mt-1 h-4 text-[0.625rem] leading-4 text-ink-300">
            <?php echo e(__('analysis.compare.reference')); ?>

        </div>
    <?php elseif(count($variants) > 0): ?>
        <div class="relative mt-1 h-4" style="width: <?php echo e($relative); ?>%; min-width: 12px;"
             role="img"
             aria-label="<?php echo e(__('analysis.track.variants_marked', ['reference' => $variants[0]['reference_id'] ?? ''])); ?>">
            <?php $__currentLoopData = $variants; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $variant): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <?php
                    $position = min(100, max(0, ($variant['position'] ?? 1) / $length * 100));
                    $isIndel = in_array($variant['type'] ?? '', ['insertion', 'deletion'], true);
                ?>
                <span class="absolute top-0 block h-2.5 w-px"
                      style="inset-inline-start: <?php echo e(round($position, 3)); ?>%;
                             background: <?php echo e($isIndel ? 'var(--color-alert-600)' : 'var(--color-signal-500)'); ?>;"
                      title="<?php echo e(__('analysis.variant_types.' . ($variant['type'] ?? 'substitution'))); ?> @ <?php echo e($variant['position'] ?? ''); ?>"></span>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>
    <?php else: ?>
        <div class="mt-1 h-4 text-[0.625rem] leading-4 text-ink-300">
            <?php echo e(__('analysis.track.no_variants')); ?>

        </div>
    <?php endif; ?>
</div>
<?php /**PATH /Users/macbookshop/Desktop/Projects/Developments/DNA-Web/DNA-Frontend/resources/views/components/composition-track.blade.php ENDPATH**/ ?>