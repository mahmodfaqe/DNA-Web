<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['items', 'counts', 'namespace']));

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

foreach (array_filter((['items', 'counts', 'namespace']), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<?php
    // Shared by the compiler and the simulator. Both tools emit the same shape —
    // a code, a severity, parameters, and optionally the thing that triggered it
    // — and both need it rendered in the reader's language, so the rendering
    // lives in one place and the namespace says which translation file to look
    // the codes up in.
    $style = [
        'error' => ['chip' => 'chip-alert', 'bg' => 'bg-alert-50'],
        'warning' => ['chip' => 'chip-signal', 'bg' => 'bg-signal-50'],
        'info' => ['chip' => 'chip-muted', 'bg' => ''],
    ];
?>

<section class="panel overflow-hidden">
    <div class="panel-head">
        <div>
            <h2 class="panel-title"><?php echo e(__($namespace . '.diagnostics.title')); ?></h2>
            <p class="panel-note"><?php echo e(__($namespace . '.diagnostics.subtitle')); ?></p>
        </div>
        <div class="flex items-center gap-1.5">
            <?php $__currentLoopData = ['error', 'warning', 'info']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $severity): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <?php if(($counts[$severity] ?? 0) > 0): ?>
                    <span class="chip <?php echo e($style[$severity]['chip']); ?>">
                        <?php echo e(__($namespace . '.diagnostics.' . $severity)); ?>

                        <span class="ltr-data"><?php echo e($counts[$severity]); ?></span>
                    </span>
                <?php endif; ?>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>
    </div>

    <?php if(empty($items)): ?>
        <p class="px-5 py-6 text-center text-sm text-good-600"><?php echo e(__($namespace . '.diagnostics.none')); ?></p>
    <?php else: ?>
        <ul class="divide-y divide-line">
            <?php $__currentLoopData = $items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $diagnostic): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <?php
                    $key = $namespace . '.messages.' . $diagnostic['code'];
                    $params = collect($diagnostic['params'] ?? [])
                        ->map(fn ($value) => is_array($value) ? implode(', ', $value) : $value)
                        ->all();

                    // A language name is itself translatable, so the parameter is
                    // resolved through the same files rather than shown as a code.
                    if (isset($params['language'])) {
                        $params['language'] = __('compiler.languages.' . $params['language']);
                    }

                    $severity = $diagnostic['severity'];
                ?>
                <li class="flex gap-3 px-5 py-3.5 <?php echo e($style[$severity]['bg']); ?>">
                    <span class="chip <?php echo e($style[$severity]['chip']); ?> mt-0.5 h-fit shrink-0">
                        <?php echo e(__($namespace . '.severity.' . $severity)); ?>

                    </span>
                    <div class="min-w-0 text-sm">
                        <p class="text-ink-900">
                            <?php echo e(Lang::has($key) ? __($key, $params) : $diagnostic['code']); ?>

                        </p>
                        <?php if(!empty($diagnostic['span'])): ?>
                            <p class="ltr-data mt-1 text-[0.6875rem] text-ink-400">
                                <?php echo e(__($namespace . '.diagnostics.span')); ?>: “<?php echo e($diagnostic['span']); ?>”
                            </p>
                        <?php endif; ?>
                    </div>
                </li>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </ul>
    <?php endif; ?>
</section>
<?php /**PATH /Users/macbookshop/Desktop/Projects/Developments/DNA-Web/DNA-Frontend/resources/views/components/diagnostics.blade.php ENDPATH**/ ?>