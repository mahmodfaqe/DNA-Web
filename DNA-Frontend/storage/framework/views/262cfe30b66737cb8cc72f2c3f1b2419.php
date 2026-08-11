<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['gates']));

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

foreach (array_filter((['gates']), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<?php
    // Lay the netlist out in columns by gate type: inputs on the left, logic in
    // the middle, outputs on the right. Signal flows left to right in every
    // language, for the same reason a sequence does - a circuit diagram is not
    // prose, and mirroring it would mean inputs on the right feeding backwards.
    $columns = [
        'inputs' => array_values(array_filter($gates, fn ($g) => in_array($g['type'], ['SENSOR', 'NOT'], true))),
        'logic' => array_values(array_filter($gates, fn ($g) => in_array($g['type'], ['AND', 'OR'], true))),
        'outputs' => array_values(array_filter($gates, fn ($g) => in_array($g['type'], ['OUTPUT', 'TERMINAL'], true))),
    ];

    $rows = max(1, max(count($columns['inputs']), count($columns['outputs'])));
    $rowHeight = 58;
    $boxWidth = 168;
    $boxHeight = 40;
    $gap = 86;

    $height = $rows * $rowHeight + 24;
    $width = $boxWidth * 3 + $gap * 2;

    $x = ['inputs' => 0, 'logic' => $boxWidth + $gap, 'outputs' => ($boxWidth + $gap) * 2];

    $centre = function (int $index, int $count) use ($rows, $rowHeight) {
        $offset = ($rows - $count) * $rowHeight / 2;
        return (int) ($offset + $index * $rowHeight + 12);
    };

    $colours = [
        'SENSOR' => 'var(--color-brand-600)',
        'NOT' => 'var(--color-alert-600)',
        'AND' => 'var(--color-ink-700)',
        'OR' => 'var(--color-ink-700)',
        'OUTPUT' => 'var(--color-good-600)',
        'TERMINAL' => 'var(--color-signal-600)',
    ];

    $labelFor = function (array $gate) {
        $key = match ($gate['type']) {
            'SENSOR', 'NOT' => 'compiler.sensors.' . $gate['label'],
            'OUTPUT', 'TERMINAL' => 'compiler.actuators.' . $gate['label'],
            default => null,
        };

        return $key && Lang::has($key) ? __($key) : $gate['label'];
    };

    // Position lookup so wires can be drawn from real box coordinates.
    $positions = [];
    foreach ($columns as $column => $items) {
        foreach ($items as $index => $gate) {
            $positions[$gate['id']] = [
                'x' => $x[$column],
                'y' => $centre($index, count($items)),
            ];
        }
    }
?>

<div class="track overflow-x-auto">
    <svg viewBox="0 0 <?php echo e($width); ?> <?php echo e($height); ?>"
         width="<?php echo e($width); ?>" height="<?php echo e($height); ?>"
         role="img" aria-label="<?php echo e(__('compiler.logic.title')); ?>"
         class="min-w-full">

        
        <?php $__currentLoopData = $gates; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $gate): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <?php $__currentLoopData = $gate['inputs']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $inputId): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <?php if(isset($positions[$inputId], $positions[$gate['id']])): ?>
                    <?php
                        $from = $positions[$inputId];
                        $to = $positions[$gate['id']];
                        $x1 = $from['x'] + $boxWidth;
                        $y1 = $from['y'] + $boxHeight / 2;
                        $x2 = $to['x'];
                        $y2 = $to['y'] + $boxHeight / 2;
                        $mid = ($x1 + $x2) / 2;
                    ?>
                    <path d="M <?php echo e($x1); ?> <?php echo e($y1); ?> C <?php echo e($mid); ?> <?php echo e($y1); ?>, <?php echo e($mid); ?> <?php echo e($y2); ?>, <?php echo e($x2); ?> <?php echo e($y2); ?>"
                          fill="none" stroke="var(--color-line-strong)" stroke-width="1.5"/>
                    <circle cx="<?php echo e($x2); ?>" cy="<?php echo e($y2); ?>" r="2.5" fill="var(--color-line-strong)"/>
                <?php endif; ?>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>

        <?php $__currentLoopData = $columns; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $column => $items): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <?php $__currentLoopData = $items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $gate): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <?php
                    $gy = $centre($index, count($items));
                    $colour = $colours[$gate['type']] ?? 'var(--color-ink-500)';
                ?>
                <g>
                    <rect x="<?php echo e($x[$column]); ?>" y="<?php echo e($gy); ?>"
                          width="<?php echo e($boxWidth); ?>" height="<?php echo e($boxHeight); ?>"
                          rx="8" fill="#fff" stroke="<?php echo e($colour); ?>" stroke-width="1.5"/>
                    <rect x="<?php echo e($x[$column]); ?>" y="<?php echo e($gy); ?>"
                          width="4" height="<?php echo e($boxHeight); ?>" rx="2" fill="<?php echo e($colour); ?>"/>

                    <text x="<?php echo e($x[$column] + 14); ?>" y="<?php echo e($gy + 16); ?>"
                          font-size="9" font-weight="700" fill="<?php echo e($colour); ?>"
                          style="letter-spacing:.08em; text-transform:uppercase;">
                        <?php echo e(__('compiler.gates.' . $gate['type'])); ?>

                    </text>
                    <text x="<?php echo e($x[$column] + 14); ?>" y="<?php echo e($gy + 30); ?>"
                          font-size="11" fill="var(--color-ink-900)">
                        <?php echo e(Str::limit($labelFor($gate), 22)); ?>

                    </text>
                </g>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </svg>
</div>
<?php /**PATH /Users/macbookshop/Desktop/Projects/Developments/DNA-Web/DNA-Frontend/resources/views/components/circuit-diagram.blade.php ENDPATH**/ ?>