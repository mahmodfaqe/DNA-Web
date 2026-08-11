<?php $__env->startSection('title', __('common.hero.title')); ?>

<?php $__env->startSection('content'); ?>
    <?php if (isset($component)) { $__componentOriginalb5964ceaff5596b67291a601bad6f23f = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalb5964ceaff5596b67291a601bad6f23f = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.tabs','data' => ['active' => 'analysis']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('tabs'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['active' => 'analysis']); ?>
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
        <p class="eyebrow"><?php echo e(__('common.hero.eyebrow')); ?></p>
        <h1 class="mt-2 text-3xl font-bold leading-tight tracking-tight sm:text-4xl">
            <?php echo e(__('common.hero.title')); ?>

        </h1>
        <p class="mx-auto mt-3 max-w-xl text-sm text-ink-500">
            <?php echo e(__('common.hero.subtitle')); ?>

        </p>
    </section>

    <?php if($errors->any()): ?>
        <div class="mx-auto mt-6 max-w-2xl">
            <div class="alert" role="alert">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="mt-0.5 h-5 w-5 shrink-0" aria-hidden="true">
                    <circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01" stroke-linecap="round"/>
                </svg>
                <div>
                    <p class="font-semibold"><?php echo e(__('errors.heading')); ?></p>
                    <?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $message): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <p class="mt-1"><?php echo e($message); ?></p>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <form method="POST"
          action="<?php echo e(route('analysis.store')); ?>"
          enctype="multipart/form-data"
          data-upload-form
          class="mx-auto mt-7 max-w-2xl space-y-4">
        <?php echo csrf_field(); ?>

        <label class="dropzone" data-dropzone for="fasta_file">
            <input type="file"
                   name="fasta_file"
                   id="fasta_file"
                   accept=".fasta,.fa,.fna,.txt"
                   required
                   data-file-input
                   class="sr-only">

            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"
                 class="mx-auto h-8 w-8 text-ink-300" aria-hidden="true">
                <path d="M12 16V4m0 0L8 8m4-4 4 4" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M4 16v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2" stroke-linecap="round"/>
            </svg>

            <span data-file-label class="mt-2 block text-sm font-semibold">
                <?php echo e(__('common.hero.dropzone')); ?>

            </span>
            <span class="mt-1 block text-xs text-ink-400">
                <?php echo e(__('common.hero.dropzone_hint', ['megabytes' => round(config('services.backend.max_upload_kb', 10240) / 1024)])); ?>

            </span>
        </label>

        <div class="text-center">
            <button type="submit" data-submit class="btn btn-primary w-full sm:w-auto">
                <?php echo e(__('common.hero.submit')); ?>

            </button>
        </div>
    </form>

    <section class="mx-auto mt-10 max-w-2xl">
        <div class="panel overflow-hidden">
            <div class="panel-head">
                <h2 class="panel-title"><?php echo e(__('common.hero.example_title')); ?></h2>
            </div>
            <div class="p-4">
                <pre class="code-block"><span style="color:#7dd3a0">&gt;Human_HBA1 Human haemoglobin subunit alpha</span>
ACTCTTCTGGTCCCCACAGACTCAGAGAGAACCCACCATG
GTGCTGTCTCCTGCCGACAAGACCAACGTC
<span style="color:#7dd3a0">&gt;Bat_HBA1 Bat haemoglobin subunit alpha</span>
ACTCTTCTGGTCCCCACAGACTCAGAGAGAACCCACCATG
GTGCTGTCTCCTGCAGATAAGACCAACGTC</pre>
                <p class="mt-3 text-xs text-ink-500"><?php echo e(__('common.hero.example_note')); ?></p>
            </div>
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
                            <a href="<?php echo e(route('analysis.show', ['analysis' => $item->id])); ?>"
                               class="flex items-center justify-between gap-4 px-5 py-3 text-sm hover:bg-paper">
                                <span class="min-w-0">
                                    <span class="ltr-data block truncate text-xs font-semibold text-ink-900"><?php echo e($item->filename); ?></span>
                                    <span class="mt-0.5 block text-xs text-ink-400">
                                        <?php echo e(__('common.recent.records', ['count' => $item->gene_count])); ?>

                                        · <?php echo e($item->created_at->diffForHumans()); ?>

                                    </span>
                                </span>
                                <span class="chip chip-muted shrink-0"><?php echo e(__('common.recent.open')); ?></span>
                            </a>
                        </li>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </ul>
            </div>
        </section>
    <?php endif; ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/macbookshop/Desktop/Projects/Developments/DNA-Web/DNA-Frontend/resources/views/analysis/index.blade.php ENDPATH**/ ?>