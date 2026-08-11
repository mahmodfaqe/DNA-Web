<?php $__env->startSection('title', __('memory.hero.title')); ?>

<?php $__env->startSection('content'); ?>
    <?php if (isset($component)) { $__componentOriginalb5964ceaff5596b67291a601bad6f23f = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalb5964ceaff5596b67291a601bad6f23f = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.tabs','data' => ['active' => 'memory']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('tabs'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['active' => 'memory']); ?>
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
        <p class="eyebrow"><?php echo e(__('memory.hero.eyebrow')); ?></p>
        <h1 class="mt-2 text-3xl font-bold leading-tight tracking-tight sm:text-4xl">
            <?php echo e(__('memory.hero.title')); ?>

        </h1>
        <p class="mx-auto mt-3 max-w-xl text-sm text-ink-500">
            <?php echo e(__('memory.hero.subtitle')); ?>

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

    <form method="POST" action="<?php echo e(route('memory.store')); ?>" data-memory-form class="mx-auto mt-7 max-w-3xl">
        <?php echo csrf_field(); ?>

        
        <div class="panel p-5">
            <p class="eyebrow mb-4"><?php echo e(__('memory.form.recording')); ?></p>

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label for="signal" class="mb-1.5 block text-sm font-semibold"><?php echo e(__('memory.form.signal')); ?></label>
                    <select id="signal" name="signal"
                            class="w-full rounded-lg border border-line-strong bg-white px-3 py-2 text-sm focus:border-brand-500 focus:outline-none">
                        <?php $__currentLoopData = \App\Models\MemoryDesign::SIGNALS; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $signal): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($signal); ?>" <?php if(old('signal', 'lactose') === $signal): echo 'selected'; endif; ?>>
                                <?php echo e(__('memory.signals.' . $signal)); ?>

                            </option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                    <p class="mt-1 text-xs text-ink-400"><?php echo e(__('memory.form.signal_hint')); ?></p>
                </div>

                <div>
                    <label for="chassis" class="mb-1.5 block text-sm font-semibold"><?php echo e(__('memory.form.chassis')); ?></label>
                    <select id="chassis" name="chassis"
                            class="w-full rounded-lg border border-line-strong bg-white px-3 py-2 text-sm focus:border-brand-500 focus:outline-none">
                        <?php $__currentLoopData = \App\Models\MemoryDesign::CHASSIS; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $host): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($host); ?>" <?php if(old('chassis', 'ecoli') === $host): echo 'selected'; endif; ?>>
                                <?php echo e(__('memory.chassis.' . $host)); ?>

                            </option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                    <p class="mt-1 text-xs text-ink-400"><?php echo e(__('memory.form.chassis_hint')); ?></p>
                </div>
            </div>
        </div>

        <div class="panel mt-4 p-5">
            <p class="eyebrow mb-4"><?php echo e(__('memory.form.demands')); ?></p>

            <div class="grid gap-5 sm:grid-cols-3">
                <div>
                    <label for="hold_hours" class="text-sm font-semibold"><?php echo e(__('memory.form.hold')); ?></label>
                    <input type="number" id="hold_hours" name="hold_hours" min="0.5" max="168" step="0.5"
                           value="<?php echo e(old('hold_hours', 24)); ?>"
                           class="ltr-data mt-1.5 w-full rounded-lg border border-line-strong bg-white px-3 py-2 text-sm focus:border-brand-500 focus:outline-none">
                    <p class="mt-1 text-xs text-ink-400"><?php echo e(__('memory.form.hold_hint')); ?></p>
                </div>

                <div>
                    <label for="signal_minutes" class="text-sm font-semibold"><?php echo e(__('memory.form.exposure')); ?></label>
                    <input type="number" id="signal_minutes" name="signal_minutes" min="1" max="720" step="1"
                           value="<?php echo e(old('signal_minutes', 60)); ?>"
                           class="ltr-data mt-1.5 w-full rounded-lg border border-line-strong bg-white px-3 py-2 text-sm focus:border-brand-500 focus:outline-none">
                    <p class="mt-1 text-xs text-ink-400"><?php echo e(__('memory.form.exposure_hint')); ?></p>
                </div>

                <div>
                    <?php $strength = (float) old('strength', 0.7); ?>
                    <div class="flex items-baseline justify-between gap-2">
                        <label for="strength" class="text-sm font-semibold"><?php echo e(__('memory.form.strength')); ?></label>
                        <output for="strength" data-slider-output="strength" class="ltr-data text-xs font-bold text-brand-600">
                            <?php echo e(round($strength * 100)); ?>%
                        </output>
                    </div>
                    <input type="range" id="strength" name="strength" min="0.1" max="1" step="0.05"
                           value="<?php echo e($strength); ?>" data-slider data-scale="100" data-suffix="%"
                           class="mt-2 w-full accent-brand-600">
                    <p class="mt-1 text-xs text-ink-400"><?php echo e(__('memory.form.strength_hint')); ?></p>
                </div>
            </div>

            <div class="mt-5 grid gap-4 border-t border-line pt-4 sm:grid-cols-2">
                <label class="flex cursor-pointer items-start gap-2.5">
                    <input type="hidden" name="must_be_reversible" value="0">
                    <input type="checkbox" name="must_be_reversible" value="1"
                           <?php if(old('must_be_reversible') === '1'): echo 'checked'; endif; ?>
                           class="mt-0.5 h-4 w-4 shrink-0 accent-brand-600">
                    <span>
                        <span class="block text-sm font-semibold"><?php echo e(__('memory.form.reversible')); ?></span>
                        <span class="mt-0.5 block text-xs leading-relaxed text-ink-500"><?php echo e(__('memory.form.reversible_hint')); ?></span>
                    </span>
                </label>

                <label class="flex cursor-pointer items-start gap-2.5">
                    <input type="hidden" name="on_plasmid" value="0">
                    <input type="checkbox" name="on_plasmid" value="1"
                           <?php if(old('on_plasmid', '1') === '1'): echo 'checked'; endif; ?>
                           class="mt-0.5 h-4 w-4 shrink-0 accent-brand-600">
                    <span>
                        <span class="block text-sm font-semibold"><?php echo e(__('memory.form.plasmid')); ?></span>
                        <span class="mt-0.5 block text-xs leading-relaxed text-ink-500"><?php echo e(__('memory.form.plasmid_hint')); ?></span>
                    </span>
                </label>
            </div>
        </div>

        
        <details class="panel mt-4 p-5" <?php if(old('payload')): ?> open <?php endif; ?>>
            <summary class="cursor-pointer text-sm font-semibold"><?php echo e(__('memory.form.payload')); ?></summary>
            <p class="mt-2 text-xs leading-relaxed text-ink-500"><?php echo e(__('memory.form.payload_hint')); ?></p>

            <textarea name="payload" rows="4" maxlength="60000"
                      placeholder="<?php echo e(__('memory.form.payload_placeholder')); ?>"
                      class="ltr-data mt-3 w-full rounded-xl border border-line-strong bg-white p-3 text-xs leading-relaxed focus:border-brand-500 focus:outline-none"><?php echo e(old('payload')); ?></textarea>

            <div class="mt-3">
                <label for="recombinase" class="text-sm font-semibold"><?php echo e(__('memory.form.recombinase')); ?></label>
                <select id="recombinase" name="recombinase"
                        class="mt-1.5 w-full rounded-lg border border-line-strong bg-white px-3 py-2 text-sm focus:border-brand-500 focus:outline-none sm:w-64">
                    <?php $__currentLoopData = \App\Models\MemoryDesign::RECOMBINASES; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $enzyme): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <option value="<?php echo e($enzyme); ?>" <?php if(old('recombinase', 'bxb1') === $enzyme): echo 'selected'; endif; ?>>
                            <?php echo e(__('memory.recombinases.' . $enzyme)); ?>

                        </option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </select>
            </div>
        </details>

        <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
            <p class="max-w-md text-xs text-ink-400"><?php echo e(__('memory.form.note')); ?></p>
            <button type="submit" data-submit class="btn btn-primary">
                <?php echo e(__('memory.form.submit')); ?>

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
                            <a href="<?php echo e(route('memory.show', ['design' => $item->id])); ?>"
                               class="flex items-center justify-between gap-4 px-5 py-3 text-sm hover:bg-paper">
                                <span class="min-w-0">
                                    <span class="block truncate text-xs font-semibold text-ink-900">
                                        <?php echo e(__('memory.signals.' . $item->signal)); ?> · <?php echo e(__('memory.chassis.' . $item->chassis)); ?>

                                    </span>
                                    <span class="mt-0.5 block truncate text-xs text-ink-400">
                                        <?php echo e($item->architecture ? __('memory.architectures.' . $item->architecture . '.name') : '—'); ?>

                                        · <span class="ltr-data"><?php echo e($item->hold_hours); ?></span> <?php echo e(__('memory.units.hours')); ?>

                                    </span>
                                </span>
                                <span class="chip shrink-0 <?php echo e($item->succeeded ? 'chip-good' : 'chip-alert'); ?>">
                                    <?php echo e($item->succeeded ? __('common.recent.open') : __('memory.severity.error')); ?>

                                </span>
                            </a>
                        </li>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </ul>
            </div>
        </section>
    <?php endif; ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/macbookshop/Desktop/Projects/Developments/DNA-Web/DNA-Frontend/resources/views/memory/index.blade.php ENDPATH**/ ?>