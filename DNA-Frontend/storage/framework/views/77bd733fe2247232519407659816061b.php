<?php $__env->startSection('title', __('simulator.hero.title')); ?>

<?php $__env->startSection('content'); ?>
    <?php if (isset($component)) { $__componentOriginalb5964ceaff5596b67291a601bad6f23f = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalb5964ceaff5596b67291a601bad6f23f = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.tabs','data' => ['active' => 'simulator']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('tabs'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['active' => 'simulator']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalb5964ceaff5596b67291a601bad6f23f)): ?>
<?php $attributes = $__attributesOriginalb5964ceaff5596b67291a601bad6f23f; ?>
<?php unset($__attributesOriginalb5964ceaff5596b67291a601bad6f23f); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalb5964ceaff5596b67291a601bad6f23f)): ?>
<?php $component = $__componentOriginalb5964ceaff5596b67291a601bad6f23f; ?>
<?php unset($__componentOriginalb5964ceaff5596b67291a601bad6f23f); ?>
<?php endif; ?>

    <section class="mx-auto max-w-2xl text-center">
        <p class="eyebrow"><?php echo e(__('simulator.hero.eyebrow')); ?></p>
        <h1 class="mt-2 text-3xl font-bold leading-tight tracking-tight sm:text-4xl">
            <?php echo e(__('simulator.hero.title')); ?>

        </h1>
        <p class="mx-auto mt-3 max-w-xl text-sm text-ink-500">
            <?php echo e(__('simulator.hero.subtitle')); ?>

        </p>
    </section>

    <?php if($errors->any()): ?>
        <div class="mx-auto mt-6 max-w-3xl">
            <div class="alert" role="alert">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="mt-0.5 h-5 w-5 shrink-0" aria-hidden="true">
                    <circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01" stroke-linecap="round"/>
                </svg>
                <div>
                    <?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $message): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <p><?php echo e($message); ?></p>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <form method="POST" action="<?php echo e(route('simulator.store')); ?>" data-simulate-form class="mx-auto mt-7 max-w-3xl">
        <?php echo csrf_field(); ?>

        
        <fieldset>
            <legend class="mb-2 block text-sm font-semibold"><?php echo e(__('simulator.form.network')); ?></legend>

            <div class="grid gap-2">
                <?php $__currentLoopData = \App\Models\Simulation::PRESETS; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $preset): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <?php $checked = old('preset', 'crosstalk_pair') === $preset; ?>
                    <label class="panel flex cursor-pointer gap-3 p-4 transition hover:border-brand-500 hover:bg-brand-50
                                  has-[:checked]:border-brand-600 has-[:checked]:bg-brand-50">
                        <input type="radio" name="preset" value="<?php echo e($preset); ?>" <?php if($checked): echo 'checked'; endif; ?>
                               class="mt-1 h-4 w-4 shrink-0 accent-brand-600">
                        <span class="min-w-0">
                            <span class="block text-sm font-bold text-ink-900">
                                <?php echo e(__('simulator.presets.' . $preset . '.name')); ?>

                            </span>
                            <span class="mt-0.5 block text-xs leading-relaxed text-ink-500">
                                <?php echo e(__('simulator.presets.' . $preset . '.description')); ?>

                            </span>
                            <span class="mt-1.5 block text-xs font-semibold text-brand-600">
                                <?php echo e(__('simulator.presets.' . $preset . '.question')); ?>

                            </span>
                        </span>
                    </label>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </div>
        </fieldset>

        <div class="panel mt-4 p-5">
            <p class="eyebrow mb-4"><?php echo e(__('simulator.form.conditions')); ?></p>

            <div class="grid gap-5 sm:grid-cols-2">
                <?php
                    $sliders = [
                        ['name' => 'induction', 'min' => 0, 'max' => 1, 'step' => 0.05, 'default' => 1, 'suffix' => '%', 'scale' => 100],
                        ['name' => 'crosstalk', 'min' => 0, 'max' => 1, 'step' => 0.05, 'default' => 0.4, 'suffix' => '%', 'scale' => 100],
                        ['name' => 'variability', 'min' => 0, 'max' => 0.6, 'step' => 0.05, 'default' => 0.2, 'suffix' => '%', 'scale' => 100],
                    ];
                ?>

                <?php $__currentLoopData = $sliders; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $slider): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <?php $value = (float) old($slider['name'], $slider['default']); ?>
                    <div>
                        <div class="flex items-baseline justify-between gap-2">
                            <label for="<?php echo e($slider['name']); ?>" class="text-sm font-semibold">
                                <?php echo e(__('simulator.form.' . $slider['name'])); ?>

                            </label>
                            <output for="<?php echo e($slider['name']); ?>" data-slider-output="<?php echo e($slider['name']); ?>"
                                    class="ltr-data text-xs font-bold text-brand-600">
                                <?php echo e(round($value * $slider['scale'])); ?><?php echo e($slider['suffix']); ?>

                            </output>
                        </div>
                        <input type="range" id="<?php echo e($slider['name']); ?>" name="<?php echo e($slider['name']); ?>"
                               min="<?php echo e($slider['min']); ?>" max="<?php echo e($slider['max']); ?>" step="<?php echo e($slider['step']); ?>"
                               value="<?php echo e($value); ?>"
                               data-slider data-scale="<?php echo e($slider['scale']); ?>" data-suffix="<?php echo e($slider['suffix']); ?>"
                               class="mt-2 w-full accent-brand-600">
                        <p class="mt-1 text-xs leading-relaxed text-ink-400">
                            <?php echo e(__('simulator.form.' . $slider['name'] . '_hint')); ?>

                        </p>
                    </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>

                <div>
                    <span class="text-sm font-semibold"><?php echo e(__('simulator.form.resources')); ?></span>
                    <label class="mt-2 flex cursor-pointer items-start gap-2.5">
                        <input type="hidden" name="resource_coupling" value="0">
                        <input type="checkbox" name="resource_coupling" value="1"
                               <?php if(old('resource_coupling', '1') === '1'): echo 'checked'; endif; ?>
                               class="mt-0.5 h-4 w-4 shrink-0 accent-brand-600">
                        <span class="text-xs leading-relaxed text-ink-500">
                            <?php echo e(__('simulator.form.resources_hint')); ?>

                        </span>
                    </label>
                </div>
            </div>

            <div class="mt-5 grid gap-4 border-t border-line pt-4 sm:grid-cols-3">
                <div>
                    <label for="cells" class="text-sm font-semibold"><?php echo e(__('simulator.form.cells')); ?></label>
                    <input type="number" id="cells" name="cells" min="4" max="200" step="1"
                           value="<?php echo e(old('cells', 60)); ?>"
                           class="ltr-data mt-1.5 w-full rounded-lg border border-line-strong bg-white px-3 py-2 text-sm
                                  focus:border-brand-500 focus:outline-none">
                    <p class="mt-1 text-xs text-ink-400"><?php echo e(__('simulator.form.cells_hint')); ?></p>
                </div>

                <div>
                    <label for="minutes" class="text-sm font-semibold"><?php echo e(__('simulator.form.duration')); ?></label>
                    <input type="number" id="minutes" name="minutes" min="5" max="240" step="5"
                           value="<?php echo e(old('minutes', 60)); ?>"
                           class="ltr-data mt-1.5 w-full rounded-lg border border-line-strong bg-white px-3 py-2 text-sm
                                  focus:border-brand-500 focus:outline-none">
                    <p class="mt-1 text-xs text-ink-400"><?php echo e(__('simulator.form.duration_hint')); ?></p>
                </div>

                <div>
                    <label for="seed" class="text-sm font-semibold"><?php echo e(__('simulator.form.seed')); ?></label>
                    <input type="number" id="seed" name="seed" min="0" max="2147483646" step="1"
                           value="<?php echo e(old('seed')); ?>" placeholder="<?php echo e(__('simulator.form.seed_placeholder')); ?>"
                           class="ltr-data mt-1.5 w-full rounded-lg border border-line-strong bg-white px-3 py-2 text-sm
                                  focus:border-brand-500 focus:outline-none">
                    <p class="mt-1 text-xs text-ink-400"><?php echo e(__('simulator.form.seed_hint')); ?></p>
                </div>
            </div>
        </div>

        <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
            <p class="max-w-md text-xs text-ink-400"><?php echo e(__('simulator.form.wait_warning')); ?></p>
            <button type="submit" data-submit class="btn btn-primary">
                <?php echo e(__('simulator.form.submit')); ?>

            </button>
        </div>
    </form>

    <?php if($recent->isNotEmpty()): ?>
        <section class="mx-auto mt-8 max-w-3xl">
            <div class="panel overflow-hidden">
                <div class="panel-head">
                    <h2 class="panel-title"><?php echo e(__('common.recent.title')); ?></h2>
                </div>
                <ul class="divide-y divide-line">
                    <?php $__currentLoopData = $recent; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <li>
                            <a href="<?php echo e(route('simulator.show', ['simulation' => $item->id])); ?>"
                               class="flex items-center justify-between gap-4 px-5 py-3 text-sm hover:bg-paper">
                                <span class="min-w-0">
                                    <span class="block truncate text-xs font-semibold text-ink-900">
                                        <?php echo e(__('simulator.presets.' . $item->preset . '.name')); ?>

                                    </span>
                                    <span class="ltr-data mt-0.5 block truncate text-xs text-ink-400">
                                        <?php echo e($item->cells); ?> <?php echo e(__('simulator.units.cells')); ?>

                                        · <?php echo e($item->minutes); ?> <?php echo e(__('simulator.units.min')); ?>

                                        · seed <?php echo e($item->seed); ?>

                                    </span>
                                </span>
                                <span class="chip shrink-0 <?php echo e($item->succeeded ? 'chip-good' : 'chip-alert'); ?>">
                                    <?php echo e($item->succeeded ? __('common.recent.open') : __('simulator.severity.error')); ?>

                                </span>
                            </a>
                        </li>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </ul>
            </div>
        </section>
    <?php endif; ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/macbookshop/Desktop/Projects/Developments/DNA-Web/DNA-Frontend/resources/views/simulator/index.blade.php ENDPATH**/ ?>