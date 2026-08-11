<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['series', 'time', 'colour', 'label']));

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

foreach (array_filter((['series', 'time', 'colour', 'label']), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<?php
    // One panel per gene rather than every gene overlaid on one plot.
    //
    // The point of this chart is that a single cell does not look like the
    // average of many cells: the average is a smooth line, and the cell under it
    // is a staircase of bursts. Stacking three genes' bands on one axis turns
    // that into overlapping washes nobody can read, and forces the reader to
    // tell three colours apart to follow it. Separate panels put each gene on
    // its own baseline, and whether the genes move together is answered
    // properly by the correlation matrix further down the page.

    $minutes = $time['grid_minutes'] ?? [];
    $mean = $series['mean'] ?? [];
    $spread = $series['sd'] ?? [];
    $examples = $series['examples'] ?? [];

    $width = 720;
    $height = 176;
    $left = 46;
    $right = 704;
    $top = 12;
    $bottom = 150;

    $span = max(0.001, (float) end($minutes));
    reset($minutes);

    // The ceiling covers the band and every example trace, so no line is ever
    // clipped by a scale chosen from the mean alone.
    $ceiling = 1.0;
    foreach ($mean as $index => $value) {
        $ceiling = max($ceiling, $value + ($spread[$index] ?? 0));
    }
    foreach ($examples as $trace) {
        $ceiling = max($ceiling, max($trace ?: [0]));
    }
    $ceiling *= 1.06;

    $x = fn ($minute) => $left + ($minute / $span) * ($right - $left);
    $y = fn ($value) => $bottom - (min($value, $ceiling) / $ceiling) * ($bottom - $top);

    $path = function (array $values) use ($minutes, $x, $y) {
        $points = [];
        foreach ($values as $index => $value) {
            if (! isset($minutes[$index])) {
                continue;
            }
            $points[] = round($x($minutes[$index]), 1) . ',' . round($y($value), 1);
        }
        return implode(' ', $points);
    };

    // The band is drawn as one closed shape: up the top edge, back along the
    // bottom. Clamped at zero because a count cannot be negative, and a band
    // dipping below the axis would suggest it can.
    $bandTop = [];
    $bandBottom = [];
    foreach ($mean as $index => $value) {
        if (! isset($minutes[$index])) {
            continue;
        }
        $deviation = $spread[$index] ?? 0;
        $bandTop[] = round($x($minutes[$index]), 1) . ',' . round($y($value + $deviation), 1);
        $bandBottom[] = round($x($minutes[$index]), 1) . ',' . round($y(max(0, $value - $deviation)), 1);
    }
    $band = implode(' ', $bandTop) . ' ' . implode(' ', array_reverse($bandBottom));

    $burnIn = (float) ($time['burn_in_minutes'] ?? 0);
    $final = (float) (end($mean) ?: 0);
    reset($mean);

    $ticks = [0, $ceiling / 2, $ceiling];
?>

<div class="track overflow-x-auto">
    <svg viewBox="0 0 <?php echo e($width); ?> <?php echo e($height); ?>" width="<?php echo e($width); ?>" height="<?php echo e($height); ?>"
         role="img" class="min-w-full"
         aria-label="<?php echo e(__('simulator.charts.trajectory_alt', ['gene' => $label])); ?>">

        
        <?php if($burnIn > 0): ?>
            <rect x="<?php echo e($left); ?>" y="<?php echo e($top); ?>"
                  width="<?php echo e(round($x($burnIn) - $left, 1)); ?>" height="<?php echo e($bottom - $top); ?>"
                  fill="var(--color-paper)"/>
            <text x="<?php echo e(round($x($burnIn) - 5, 1)); ?>" y="<?php echo e($top + 11); ?>"
                  font-size="8.5" text-anchor="end" fill="var(--color-ink-400)">
                <?php echo e(__('simulator.charts.burn_in')); ?>

            </text>
        <?php endif; ?>

        <?php $__currentLoopData = $ticks; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $tick): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <line x1="<?php echo e($left); ?>" y1="<?php echo e(round($y($tick), 1)); ?>"
                  x2="<?php echo e($right); ?>" y2="<?php echo e(round($y($tick), 1)); ?>"
                  stroke="var(--color-line)" stroke-width="1"/>
            <text x="<?php echo e($left - 7); ?>" y="<?php echo e(round($y($tick) + 3, 1)); ?>"
                  font-size="9" text-anchor="end" fill="var(--color-ink-400)">
                <?php echo e(number_format($tick, $ceiling < 20 ? 1 : 0)); ?>

            </text>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>

        
        <polygon points="<?php echo e($band); ?>" fill="<?php echo e($colour); ?>" fill-opacity="0.12"/>

        <?php $__currentLoopData = $examples; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $trace): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <polyline points="<?php echo e($path($trace)); ?>" fill="none"
                      stroke="<?php echo e($colour); ?>" stroke-opacity="0.32" stroke-width="1"
                      stroke-linejoin="round"/>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>

        <polyline points="<?php echo e($path($mean)); ?>" fill="none"
                  stroke="<?php echo e($colour); ?>" stroke-width="2"
                  stroke-linejoin="round" stroke-linecap="round"/>

        
        <circle cx="<?php echo e(round($x($span), 1)); ?>" cy="<?php echo e(round($y($final), 1)); ?>" r="4"
                fill="<?php echo e($colour); ?>" stroke="#fff" stroke-width="2"/>

        <line x1="<?php echo e($left); ?>" y1="<?php echo e($bottom); ?>" x2="<?php echo e($right); ?>" y2="<?php echo e($bottom); ?>"
              stroke="var(--color-line-strong)" stroke-width="1"/>

        <?php $__currentLoopData = [0, 0.25, 0.5, 0.75, 1]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $fraction): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <text x="<?php echo e(round($left + $fraction * ($right - $left), 1)); ?>" y="<?php echo e($bottom + 15); ?>"
                  font-size="9" text-anchor="<?php echo e($fraction === 0 ? 'start' : ($fraction === 1 ? 'end' : 'middle')); ?>"
                  fill="var(--color-ink-400)">
                <?php echo e(round($span * $fraction)); ?>

            </text>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>

        <text x="<?php echo e($right); ?>" y="<?php echo e($height - 2); ?>" font-size="8.5"
              text-anchor="end" fill="var(--color-ink-400)">
            <?php echo e(__('simulator.charts.minutes')); ?>

        </text>
    </svg>
</div>
<?php /**PATH /Users/macbookshop/Desktop/Projects/Developments/DNA-Web/DNA-Frontend/resources/views/components/trajectory-chart.blade.php ENDPATH**/ ?>