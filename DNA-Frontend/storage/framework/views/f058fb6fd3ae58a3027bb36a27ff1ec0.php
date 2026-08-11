<?php $__env->startSection('title', __('errors.not_found.title')); ?>

<?php $__env->startSection('content'); ?>
    <div class="mx-auto max-w-md py-16 text-center">
        <p class="eyebrow">404</p>
        <h1 class="mt-2 text-2xl font-bold"><?php echo e(__('errors.not_found.title')); ?></h1>
        <p class="mt-2 text-sm text-ink-500"><?php echo e(__('errors.not_found.body')); ?></p>
        <a href="<?php echo e(url('/' . app()->getLocale())); ?>" class="btn btn-primary mt-6">
            <?php echo e(__('errors.not_found.action')); ?>

        </a>
    </div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/macbookshop/Desktop/Projects/Developments/DNA-Web/DNA-Frontend/resources/views/errors/404.blade.php ENDPATH**/ ?>