<?php $__env->startSection('title', __('compiler.hero.title')); ?>

<?php $__env->startSection('content'); ?>
    <?php if (isset($component)) { $__componentOriginalb5964ceaff5596b67291a601bad6f23f = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalb5964ceaff5596b67291a601bad6f23f = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.tabs','data' => ['active' => 'compiler']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('tabs'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['active' => 'compiler']); ?>
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
        <p class="eyebrow"><?php echo e(__('compiler.hero.eyebrow')); ?></p>
        <h1 class="mt-2 text-3xl font-bold leading-tight tracking-tight sm:text-4xl">
            <?php echo e(__('compiler.hero.title')); ?>

        </h1>
        <p class="mx-auto mt-3 max-w-xl text-sm text-ink-500">
            <?php echo e(__('compiler.hero.subtitle')); ?>

        </p>
    </section>

    <?php if($errors->any()): ?>
        <div class="mx-auto mt-6 max-w-2xl">
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

    <form method="POST" action="<?php echo e(route('compiler.store')); ?>" data-compile-form class="mx-auto mt-7 max-w-2xl">
        <?php echo csrf_field(); ?>

        <label for="description" class="mb-2 block text-sm font-semibold">
            <?php echo e(__('compiler.hero.label')); ?>

        </label>

        <textarea id="description"
                  name="description"
                  rows="4"
                  required
                  maxlength="2000"
                  data-compile-input
                  placeholder="<?php echo e(__('compiler.hero.placeholder')); ?>"
                  class="w-full rounded-xl border border-line-strong bg-white p-4 text-sm leading-relaxed
                         focus:border-brand-500 focus:outline-none"><?php echo e(old('description')); ?></textarea>

        <div class="mt-2 flex flex-wrap items-center justify-between gap-3">
            <span class="ltr-data text-xs text-ink-400" data-char-count>0 / 2000</span>
            <button type="submit" data-submit class="btn btn-primary">
                <?php echo e(__('compiler.hero.submit')); ?>

            </button>
        </div>
    </form>

    <section class="mx-auto mt-8 max-w-2xl">
        <p class="eyebrow mb-2"><?php echo e(__('compiler.hero.examples')); ?></p>
        <div class="grid gap-2">
            <?php $__currentLoopData = ['basic', 'and_gate', 'or_gate', 'inverter']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $example): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                
                <button type="button"
                        data-example
                        data-text="<?php echo e(__('compiler.examples.' . $example)); ?>"
                        class="panel px-4 py-3 text-start text-sm text-ink-700 transition hover:border-brand-500 hover:bg-brand-50">
                    <?php echo e(__('compiler.examples.' . $example)); ?>

                </button>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>
    </section>

    <?php if($recent->isNotEmpty()): ?>
        <section class="mx-auto mt-6 max-w-2xl">
            <div class="panel overflow-hidden">
                <div class="panel-head">
                    <h2 class="panel-title"><?php echo e(__('common.recent.title')); ?></h2>
                </div>
                <ul class="divide-y divide-line">
                    <?php $__currentLoopData = $recent; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <li>
                            <a href="<?php echo e(route('compiler.show', ['circuit' => $item->id])); ?>"
                               class="flex items-center justify-between gap-4 px-5 py-3 text-sm hover:bg-paper">
                                <span class="min-w-0">
                                    <span class="block truncate text-xs font-semibold text-ink-900"><?php echo e(Str::limit($item->source_text, 70)); ?></span>
                                    <span class="ltr-data mt-0.5 block truncate text-xs text-ink-400"><?php echo e($item->expression ?: '—'); ?></span>
                                </span>
                                <span class="chip shrink-0 <?php echo e($item->succeeded ? 'chip-good' : 'chip-alert'); ?>">
                                    <?php echo e($item->succeeded ? __('common.recent.open') : __('compiler.severity.error')); ?>

                                </span>
                            </a>
                        </li>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </ul>
            </div>
        </section>
    <?php endif; ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/macbookshop/Desktop/Projects/Developments/DNA-Web/DNA-Frontend/resources/views/compiler/index.blade.php ENDPATH**/ ?>