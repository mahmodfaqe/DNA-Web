<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['active']));

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

foreach (array_filter((['active']), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>


<nav aria-label="<?php echo e(__('common.app.name')); ?>" class="no-print mb-7 border-b border-line">
    <ul class="-mb-px flex gap-1 overflow-x-auto">
        <?php
            $tabs = [
                ['route' => 'analysis.index', 'key' => 'analysis', 'label' => __('compiler.nav.analysis')],
                ['route' => 'compiler.index', 'key' => 'compiler', 'label' => __('compiler.nav.compiler')],
                ['route' => 'simulator.index', 'key' => 'simulator', 'label' => __('compiler.nav.simulator')],
            ];
        ?>

        <?php $__currentLoopData = $tabs; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $tab): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <li>
                <a href="<?php echo e(route($tab['route'])); ?>"
                   <?php if($tab['key'] === $active): ?> aria-current="page" <?php endif; ?>
                   class="inline-block whitespace-nowrap border-b-2 px-4 py-2.5 text-sm font-semibold transition
                          <?php echo e($tab['key'] === $active
                                ? 'border-brand-600 text-brand-600'
                                : 'border-transparent text-ink-400 hover:border-line-strong hover:text-ink-700'); ?>">
                    <?php echo e($tab['label']); ?>

                </a>
            </li>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </ul>
</nav>
<?php /**PATH /Users/macbookshop/Desktop/Projects/Developments/DNA-Web/DNA-Frontend/resources/views/components/tabs.blade.php ENDPATH**/ ?>