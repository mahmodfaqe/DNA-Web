<?php $__env->startSection('title', __('analysis.result.title')); ?>

<?php $__env->startSection('header-actions'); ?>
    <div class="hidden items-center gap-2 sm:flex">
        <a href="<?php echo e(route('analysis.csv', ['analysis' => $analysis->id])); ?>" class="btn btn-quiet btn-sm">
            <?php echo e(__('common.actions.csv')); ?>

        </a>
        <a href="<?php echo e(route('analysis.json', ['analysis' => $analysis->id])); ?>" class="btn btn-quiet btn-sm">
            <?php echo e(__('common.actions.json')); ?>

        </a>
        <a href="<?php echo e(route('analysis.print', ['analysis' => $analysis->id])); ?>" class="btn btn-quiet btn-sm">
            <?php echo e(__('common.actions.print')); ?>

        </a>
    </div>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div class="min-w-0">
            <p class="eyebrow"><?php echo e(__('analysis.result.title')); ?></p>
            <h1 class="ltr-data mt-1 truncate text-2xl font-bold tracking-tight"><?php echo e($analysis->filename); ?></h1>
            <p class="mt-1 text-xs text-ink-400">
                <?php echo e(__('analysis.result.created')); ?> <?php echo e($analysis->created_at->diffForHumans()); ?>

                · <?php echo e(__('analysis.result.checksum')); ?>

                <span class="ltr-data"><?php echo e($analysis->checksum); ?></span>
            </p>
        </div>

        <a href="<?php echo e(route('analysis.index')); ?>" class="btn btn-quiet no-print">
            <?php echo e(__('common.actions.new_analysis')); ?>

        </a>
    </div>

    <div class="space-y-6">
        <?php echo $__env->make('partials.metrics', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        <?php echo $__env->make('partials.tracks', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        <?php echo $__env->make('partials.records-table', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        <?php echo $__env->make('partials.comparison', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    </div>

    <div class="mt-6 flex gap-2 sm:hidden">
        <a href="<?php echo e(route('analysis.csv', ['analysis' => $analysis->id])); ?>" class="btn btn-quiet flex-1"><?php echo e(__('common.actions.csv')); ?></a>
        <a href="<?php echo e(route('analysis.print', ['analysis' => $analysis->id])); ?>" class="btn btn-quiet flex-1"><?php echo e(__('common.actions.print')); ?></a>
    </div>

    
    <dialog data-protein-dialog
            class="w-[min(46rem,92vw)] rounded-xl border border-line p-0 backdrop:bg-ink-900/50">
        <div class="flex items-center justify-between gap-4 border-b border-line px-5 py-3">
            <div>
                <h2 class="ltr-data text-sm font-bold" data-protein-title></h2>
                <p class="text-xs text-ink-400"><?php echo e(__('analysis.orf.protein_title')); ?></p>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" data-copy class="btn btn-quiet btn-sm"><?php echo e(__('common.actions.copy')); ?></button>
                <button type="button" data-close class="btn btn-quiet btn-sm"><?php echo e(__('common.actions.close')); ?></button>
            </div>
        </div>
        <pre data-protein-sequence class="code-block max-h-[60vh] overflow-auto rounded-none whitespace-pre-wrap break-all"></pre>
    </dialog>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/macbookshop/Desktop/Projects/Developments/DNA-Web/DNA-Frontend/resources/views/analysis/show.blade.php ENDPATH**/ ?>