<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['unit']));

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

foreach (array_filter((['unit']), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<?php
    $length = max(1, (int) $unit['length']);

    $roleColours = [
        'promoter' => 'var(--color-brand-500)',
        'rbs' => 'var(--color-signal-500)',
        'cds' => 'var(--color-good-600)',
        'terminator' => 'var(--color-alert-600)',
        'tag' => 'var(--color-ink-500)',
        'scar' => 'var(--color-line-strong)',
        'spacer' => 'var(--color-line-strong)',
        // Added for the memory designer. The att sites take the darkest brand
        // step because they are the boundary of the register — the two points
        // the whole construct is built around — and the payload between them
        // is neutral ink, since what it contains is the user's business.
        'att' => 'var(--color-brand-700)',
        'payload' => 'var(--color-ink-700)',
    ];
?>


<div class="track">
    <div class="flex h-7 w-full overflow-hidden rounded-md border border-line">
        <?php $__currentLoopData = $unit['annotations']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $annotation): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <?php
                $span = $annotation['end'] - $annotation['start'] + 1;
                $percent = $span / $length * 100;
                $colour = $roleColours[$annotation['role']] ?? 'var(--color-ink-300)';
                $isPlaceholder = $annotation['provenance'] === 'placeholder';
            ?>
            <div class="relative flex items-center justify-center overflow-hidden text-[9px] font-semibold text-white"
                 style="width: <?php echo e(round($percent, 3)); ?>%;
                        background: <?php echo e($colour); ?>;
                        <?php echo e($isPlaceholder ? 'background-image: repeating-linear-gradient(45deg, transparent, transparent 4px, rgba(255,255,255,.35) 4px, rgba(255,255,255,.35) 8px);' : ''); ?>"
                 title="<?php echo e($annotation['part_id']); ?> — <?php echo e($annotation['name']); ?> (<?php echo e($span); ?> bp)">
                <?php if($percent > 12): ?>
                    <span class="truncate px-1"><?php echo e($annotation['part_id']); ?></span>
                <?php endif; ?>
            </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </div>

    <div class="mt-1 flex justify-between text-[0.625rem] text-ink-400">
        <span class="ltr-data">5′ 1</span>
        <span class="ltr-data"><?php echo e(number_format($length)); ?> 3′</span>
    </div>
</div>
<?php /**PATH /Users/macbookshop/Desktop/Projects/Developments/DNA-Web/DNA-Frontend/resources/views/components/part-map.blade.php ENDPATH**/ ?>