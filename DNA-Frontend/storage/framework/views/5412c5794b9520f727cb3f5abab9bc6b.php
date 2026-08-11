<?php $meta = \App\Support\Locales::meta(); ?>
<!DOCTYPE html>
<html lang="<?php echo e($meta['tag']); ?>" dir="<?php echo e($meta['dir']); ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title><?php echo $__env->yieldContent('title', __('common.app.name')); ?> — <?php echo e(__('common.app.name')); ?></title>
    <meta name="description" content="<?php echo e(__('common.app.description')); ?>">

    
    <?php $__currentLoopData = \App\Support\Locales::codes(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $code): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <link rel="alternate" hreflang="<?php echo e(\App\Support\Locales::tag($code)); ?>" href="<?php echo e(\App\Support\Locales::urlFor($code)); ?>">
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    <link rel="alternate" hreflang="x-default" href="<?php echo e(\App\Support\Locales::urlFor('en')); ?>">

    <link rel="icon" href="<?php echo e(asset('favicon.ico')); ?>" sizes="any">
    <?php echo app('Illuminate\Foundation\Vite')(['resources/css/app.css', 'resources/js/app.js']); ?>
</head>
<body
    data-label-submitting="<?php echo e(__('common.hero.submitting')); ?>"
    data-label-copied="<?php echo e(__('common.actions.copied')); ?>"
    data-label-no-protein="<?php echo e(__('analysis.orf.protein_empty')); ?>"
    data-label-compiling="<?php echo e(__('compiler.hero.submitting')); ?>"
    data-label-simulating="<?php echo e(__('simulator.form.submitting')); ?>"
>
<a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:z-50 focus:m-3 focus:rounded-lg focus:bg-white focus:px-4 focus:py-2 focus:shadow">
    <?php echo e(__('common.nav.skip_to_content')); ?>

</a>

<header class="no-print sticky top-0 z-30 border-b border-line bg-white/90 backdrop-blur">
    <div class="mx-auto flex h-16 max-w-6xl items-center justify-between gap-4 px-4 sm:px-6">
        <a href="<?php echo e(route('analysis.index')); ?>" class="flex items-center gap-3">
            <span aria-hidden="true" class="grid h-9 w-9 place-items-center rounded-lg bg-brand-600 text-white">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" class="h-5 w-5">
                    <path d="M7 3c0 5 10 5 10 9s-10 4-10 9" stroke-linecap="round"/>
                    <path d="M17 3c0 5-10 5-10 9s10 4 10 9" stroke-linecap="round"/>
                    <path d="M9 7h6M8.5 12h7M9 17h6" stroke-linecap="round"/>
                </svg>
            </span>
            <span>
                <span class="block text-sm font-bold leading-tight"><?php echo e(__('common.app.name')); ?></span>
                <span class="block text-xs text-ink-400"><?php echo e(__('common.app.tagline')); ?></span>
            </span>
        </a>

        <div class="flex items-center gap-2">
            <?php echo $__env->yieldContent('header-actions'); ?>
            <?php if (isset($component)) { $__componentOriginal8d3bff7d7383a45350f7495fc470d934 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal8d3bff7d7383a45350f7495fc470d934 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.language-switcher','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('language-switcher'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal8d3bff7d7383a45350f7495fc470d934)): ?>
<?php $attributes = $__attributesOriginal8d3bff7d7383a45350f7495fc470d934; ?>
<?php unset($__attributesOriginal8d3bff7d7383a45350f7495fc470d934); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal8d3bff7d7383a45350f7495fc470d934)): ?>
<?php $component = $__componentOriginal8d3bff7d7383a45350f7495fc470d934; ?>
<?php unset($__componentOriginal8d3bff7d7383a45350f7495fc470d934); ?>
<?php endif; ?>
        </div>
    </div>
</header>

<main id="main" class="mx-auto max-w-6xl px-4 py-8 sm:px-6">
    <?php echo $__env->yieldContent('content'); ?>
</main>

<footer class="no-print mx-auto max-w-6xl px-4 pb-10 sm:px-6">
    <p class="border-t border-line pt-5 text-xs text-ink-400">
        <?php echo e(__('common.footer.retention', ['days' => 30])); ?>

    </p>
</footer>
</body>
</html>
<?php /**PATH /Users/macbookshop/Desktop/Projects/Developments/DNA-Web/DNA-Frontend/resources/views/layouts/app.blade.php ENDPATH**/ ?>