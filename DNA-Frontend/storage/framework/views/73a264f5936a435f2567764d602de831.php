<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['genes', 'matrix', 'caption', 'note']));

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

foreach (array_filter((['genes', 'matrix', 'caption', 'note']), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<?php
    // A diverging scale, because the quantity has a meaningful zero and two
    // opposite directions: genes rising together, genes rising against each
    // other, and a neutral middle that has to read as "nothing here". Warm
    // against cool, with grey at the midpoint — a rainbow or two cool hues
    // would destroy exactly that reading.
    $colourFor = function (float $value) {
        $strength = min(1.0, abs($value));
        if ($strength < 0.04) {
            return ['fill' => 'var(--color-paper)', 'ink' => 'var(--color-ink-400)'];
        }

        $token = $value > 0 ? '43, 80, 143' : '179, 53, 42';   // brand-500 / alert-600
        $opacity = 0.12 + 0.78 * $strength;

        return [
            'fill' => "rgba({$token}, {$opacity})",
            'ink' => $opacity > 0.55 ? '#fff' : 'var(--color-ink-900)',
        ];
    };
?>

<figure class="track">
    <figcaption class="mb-2 text-xs font-semibold text-ink-700"><?php echo e($caption); ?></figcaption>

    <div class="overflow-x-auto">
        <table class="w-full border-separate" style="border-spacing: 2px;">
            <caption class="sr-only"><?php echo e($caption); ?>. <?php echo e($note); ?></caption>
            <thead>
            <tr>
                <th scope="col" class="w-10"><span class="sr-only"><?php echo e(__('simulator.crosstalk.gene')); ?></span></th>
                <?php $__currentLoopData = $genes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $gene): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <th scope="col" class="px-1 pb-1 text-[0.6875rem] font-bold text-ink-500"><?php echo e($gene); ?></th>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </tr>
            </thead>
            <tbody>
            <?php $__currentLoopData = $genes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row => $gene): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <tr>
                    <th scope="row" class="pe-1 text-end text-[0.6875rem] font-bold text-ink-500"><?php echo e($gene); ?></th>
                    <?php $__currentLoopData = $genes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $column => $other): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <?php
                            // The diagonal is a gene against itself, which is 1.00
                            // by definition and tells nobody anything. Left blank
                            // rather than filled solid, so the strongest colour on
                            // the grid is always a real reading.
                            $self = $row === $column;
                            $value = (float) ($matrix[$row][$column] ?? 0);
                            $style = $self
                                ? ['fill' => 'transparent', 'ink' => 'var(--color-ink-300)']
                                : $colourFor($value);
                        ?>
                        <td class="rounded-[3px] px-1 py-2 text-center text-[0.6875rem] font-semibold tabular-nums"
                            style="background: <?php echo e($style['fill']); ?>; color: <?php echo e($style['ink']); ?>;">
                            <?php echo e($self ? '·' : number_format($value, 2)); ?>

                        </td>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </tbody>
        </table>
    </div>

    <p class="mt-2 text-[0.6875rem] leading-relaxed text-ink-400"><?php echo e($note); ?></p>
</figure>
<?php /**PATH /Users/macbookshop/Desktop/Projects/Developments/DNA-Web/DNA-Frontend/resources/views/components/crosstalk-matrix.blade.php ENDPATH**/ ?>