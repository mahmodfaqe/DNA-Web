<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['series', 'span', 'caption', 'unit']));

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

foreach (array_filter((['series', 'span', 'caption', 'unit']), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<?php
    // Both architectures are plotted on one axis, and that is only legitimate
    // because both are shown as the same quantity: the share of the memory that
    // is set, from nothing to everything. A recombinase reports the fraction of
    // the population whose register has inverted; a toggle reports how far the
    // set repressor has won over the reset one. Plotting copies of integrase
    // against a fraction would need two y-scales, which is the one thing a
    // chart must never do — the alignment between the scales would be arbitrary
    // and the chart would invent a relationship that is not in the data.
    //
    // The write and hold phases are separate charts rather than one long axis:
    // an hour of signal followed by a day of holding puts the entire write
    // phase into three pixels, and the write phase is half the question.

    $width = 460;
    $height = 190;
    $left = 40;
    $right = 448;
    $top = 14;
    $bottom = 150;

    $x = fn ($minute) => $left + ($span > 0 ? $minute / $span : 0) * ($right - $left);
    $y = fn ($value) => $bottom - max(0, min(1, $value)) * ($bottom - $top);

    $path = function (array $points) use ($x, $y) {
        return implode(' ', array_map(
            fn ($point) => round($x($point[0]), 1) . ',' . round($y($point[1]), 1),
            $points
        ));
    };
?>

<figure class="track">
    <figcaption class="mb-1.5 text-xs font-semibold text-ink-700"><?php echo e($caption); ?></figcaption>

    <div class="overflow-x-auto">
        <svg viewBox="0 0 <?php echo e($width); ?> <?php echo e($height); ?>" width="<?php echo e($width); ?>" height="<?php echo e($height); ?>"
             role="img" class="min-w-full" aria-label="<?php echo e($caption); ?>">

            <?php $__currentLoopData = [0, 0.5, 1]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $tick): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <line x1="<?php echo e($left); ?>" y1="<?php echo e(round($y($tick), 1)); ?>"
                      x2="<?php echo e($right); ?>" y2="<?php echo e(round($y($tick), 1)); ?>"
                      stroke="var(--color-line)" stroke-width="1"/>
                <text x="<?php echo e($left - 6); ?>" y="<?php echo e(round($y($tick) + 3, 1)); ?>"
                      font-size="9" text-anchor="end" fill="var(--color-ink-400)">
                    <?php echo e(round($tick * 100)); ?>%
                </text>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>

            
            <line x1="<?php echo e($left); ?>" y1="<?php echo e(round($y(0.5), 1)); ?>" x2="<?php echo e($right); ?>" y2="<?php echo e(round($y(0.5), 1)); ?>"
                  stroke="var(--color-line-strong)" stroke-width="1" stroke-dasharray="3 3"/>

            <?php $__currentLoopData = $series; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $line): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <?php if(count($line['points']) > 1): ?>
                    <polyline points="<?php echo e($path($line['points'])); ?>" fill="none"
                              stroke="<?php echo e($line['colour']); ?>" stroke-width="2"
                              stroke-linejoin="round" stroke-linecap="round"/>
                    <?php $last = end($line['points']); ?>
                    <circle cx="<?php echo e(round($x($last[0]), 1)); ?>" cy="<?php echo e(round($y($last[1]), 1)); ?>" r="4"
                            fill="<?php echo e($line['colour']); ?>" stroke="#fff" stroke-width="2"/>
                <?php endif; ?>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>

            <line x1="<?php echo e($left); ?>" y1="<?php echo e($bottom); ?>" x2="<?php echo e($right); ?>" y2="<?php echo e($bottom); ?>"
                  stroke="var(--color-line-strong)" stroke-width="1"/>

            <?php $__currentLoopData = [0, 0.5, 1]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $fraction): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <text x="<?php echo e(round($left + $fraction * ($right - $left), 1)); ?>" y="<?php echo e($bottom + 15); ?>"
                      font-size="9" fill="var(--color-ink-400)"
                      text-anchor="<?php echo e($fraction === 0 ? 'start' : ($fraction === 1 ? 'end' : 'middle')); ?>">
                    <?php echo e(round($span * $fraction, $span < 10 ? 1 : 0)); ?>

                </text>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>

            <text x="<?php echo e($right); ?>" y="<?php echo e($height - 3); ?>" font-size="8.5"
                  text-anchor="end" fill="var(--color-ink-400)"><?php echo e($unit); ?></text>
        </svg>
    </div>
</figure>
<?php /**PATH /Users/macbookshop/Desktop/Projects/Developments/DNA-Web/DNA-Frontend/resources/views/components/memory-chart.blade.php ENDPATH**/ ?>