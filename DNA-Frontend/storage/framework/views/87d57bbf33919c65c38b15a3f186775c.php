<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['shape', 'statistics', 'colour', 'label']));

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

foreach (array_filter((['shape', 'statistics', 'colour', 'label']), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<?php
    // The histogram is the point of a stochastic simulation, not a nicety.
    //
    // A deterministic model would report one number for this gene. The width of
    // this shape is everything that number leaves out: cells at twice the mean
    // and cells at half of it, in the same culture, with the same DNA.

    $counts = $shape['counts'] ?? [];
    $edges = $shape['edges'] ?? [];
    $total = array_sum($counts) ?: 1;
    $tallest = max($counts ?: [1]);

    $width = 340;
    $height = 150;
    $left = 6;
    $right = 334;
    $top = 10;
    $bottom = 122;

    $slots = max(1, count($counts));
    $slot = ($right - $left) / $slots;
    $barWidth = max(1.5, $slot - 2);  // the 2px surface gap between neighbours

    $mean = (float) ($statistics['mean_protein'] ?? 0);
    $low = (float) ($edges[0] ?? 0);
    $high = (float) (end($edges) ?: 1);
    $range = max(0.001, $high - $low);
    $meanX = $left + (($mean - $low) / $range) * ($right - $left);
?>

<div class="track overflow-x-auto">
    <svg viewBox="0 0 <?php echo e($width); ?> <?php echo e($height); ?>" width="<?php echo e($width); ?>" height="<?php echo e($height); ?>"
         role="img" class="min-w-full"
         aria-label="<?php echo e(__('simulator.charts.distribution_alt', ['gene' => $label])); ?>">

        <?php $__currentLoopData = $counts; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $count): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <?php
                $barHeight = $tallest > 0 ? ($count / $tallest) * ($bottom - $top) : 0;
                $barX = $left + $index * $slot;
                $share = round($count / $total * 100, 1);
                $from = round((float) ($edges[$index] ?? 0));
                $to = round((float) ($edges[$index + 1] ?? 0));
            ?>
            <?php if($count > 0): ?>
                
                <rect x="<?php echo e(round($barX, 1)); ?>" y="<?php echo e(round($bottom - $barHeight, 1)); ?>"
                      width="<?php echo e(round($barWidth, 1)); ?>" height="<?php echo e(round($barHeight, 1)); ?>"
                      rx="<?php echo e(min(2, $barWidth / 2)); ?>" fill="<?php echo e($colour); ?>" fill-opacity="0.85">
                    <title><?php echo e($from); ?>–<?php echo e($to); ?>: <?php echo e($share); ?>%</title>
                </rect>
            <?php endif; ?>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>

        <line x1="<?php echo e($left); ?>" y1="<?php echo e($bottom); ?>" x2="<?php echo e($right); ?>" y2="<?php echo e($bottom); ?>"
              stroke="var(--color-line-strong)" stroke-width="1"/>

        
        <line x1="<?php echo e(round($meanX, 1)); ?>" y1="<?php echo e($top - 4); ?>" x2="<?php echo e(round($meanX, 1)); ?>" y2="<?php echo e($bottom); ?>"
              stroke="var(--color-ink-700)" stroke-width="1" stroke-dasharray="3 2"/>
        <text x="<?php echo e(round($meanX, 1)); ?>" y="<?php echo e($top - 6); ?>" font-size="8.5"
              text-anchor="middle" fill="var(--color-ink-700)">
            <?php echo e(__('simulator.charts.mean')); ?> <?php echo e(number_format($mean, $mean < 20 ? 1 : 0)); ?>

        </text>

        <text x="<?php echo e($left); ?>" y="<?php echo e($bottom + 13); ?>" font-size="9" fill="var(--color-ink-400)">
            <?php echo e(number_format($low)); ?>

        </text>
        <text x="<?php echo e($right); ?>" y="<?php echo e($bottom + 13); ?>" font-size="9"
              text-anchor="end" fill="var(--color-ink-400)">
            <?php echo e(number_format($high)); ?>

        </text>
        <text x="<?php echo e(($left + $right) / 2); ?>" y="<?php echo e($height - 3); ?>" font-size="8.5"
              text-anchor="middle" fill="var(--color-ink-400)">
            <?php echo e(__('simulator.charts.copies_per_cell')); ?>

        </text>
    </svg>
</div>
<?php /**PATH /Users/macbookshop/Desktop/Projects/Developments/DNA-Web/DNA-Frontend/resources/views/components/distribution-chart.blade.php ENDPATH**/ ?>