<?php $current = app()->getLocale(); ?>


<nav aria-label="<?php echo e(__('common.nav.language')); ?>"
     class="flex items-center gap-0.5 rounded-lg border border-line bg-paper p-0.5">
    <?php $__currentLoopData = \App\Support\Locales::SUPPORTED; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $code => $meta): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <a href="<?php echo e(\App\Support\Locales::urlFor($code)); ?>"
           hreflang="<?php echo e($meta['tag']); ?>"
           lang="<?php echo e($meta['tag']); ?>"
           <?php if($code === $current): ?> aria-current="true" <?php endif; ?>
           class="rounded-md px-2.5 py-1 text-xs font-semibold transition
                  <?php echo e($code === $current
                        ? 'bg-white text-brand-600 shadow-sm'
                        : 'text-ink-500 hover:text-ink-900'); ?>">
            <?php echo e($meta['native']); ?>

        </a>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
</nav>
<?php /**PATH /Users/macbookshop/Desktop/Projects/Developments/DNA-Web/DNA-Frontend/resources/views/components/language-switcher.blade.php ENDPATH**/ ?>