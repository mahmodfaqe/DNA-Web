<?php
    $genes = $analysis->genes();
    $maxLength = max(array_column($genes, 'length') ?: [1]);

    // Variants are indexed by the record they were called against, so each track
    // can draw its own marks without re-scanning the whole comparison set.
    $variantsById = [];
    foreach ($analysis->comparisons() as $comparison) {
        $variantsById[$comparison['alternative_id']] = $comparison['variants'] ?? [];
    }
    $referenceId = $genes[0]['id'] ?? null;
?>

<section class="panel overflow-hidden">
    <div class="panel-head">
        <div>
            <h2 class="panel-title"><?php echo e(__('analysis.track.title')); ?></h2>
            <p class="panel-note"><?php echo e(__('analysis.track.subtitle')); ?></p>
        </div>

        <div class="track flex flex-wrap items-center gap-x-3 gap-y-1 text-[0.6875rem] text-ink-500">
            <?php $__currentLoopData = ['A' => 'a', 'T' => 't', 'C' => 'c', 'G' => 'g', 'N' => 'n']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $base => $token): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <span class="inline-flex items-center gap-1">
                    <span class="inline-block h-2.5 w-2.5 rounded-[2px]"
                          style="background: var(--color-base-<?php echo e($token); ?>);" aria-hidden="true"></span>
                    <span class="ltr-data"><?php echo e($base); ?></span>
                </span>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>
    </div>

    <div class="space-y-4 p-5">
        <?php $__currentLoopData = $genes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $gene): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <?php if (isset($component)) { $__componentOriginal66bd4ee25edfe9d5a41441ca812c2bc5 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal66bd4ee25edfe9d5a41441ca812c2bc5 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.composition-track','data' => ['gene' => $gene,'variants' => $variantsById[$gene['id']] ?? [],'maxLength' => $maxLength,'isReference' => $gene['id'] === $referenceId]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('composition-track'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['gene' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($gene),'variants' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($variantsById[$gene['id']] ?? []),'max-length' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($maxLength),'is-reference' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($gene['id'] === $referenceId)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal66bd4ee25edfe9d5a41441ca812c2bc5)): ?>
<?php $attributes = $__attributesOriginal66bd4ee25edfe9d5a41441ca812c2bc5; ?>
<?php unset($__attributesOriginal66bd4ee25edfe9d5a41441ca812c2bc5); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal66bd4ee25edfe9d5a41441ca812c2bc5)): ?>
<?php $component = $__componentOriginal66bd4ee25edfe9d5a41441ca812c2bc5; ?>
<?php unset($__componentOriginal66bd4ee25edfe9d5a41441ca812c2bc5); ?>
<?php endif; ?>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </div>

    <p class="border-t border-line px-5 py-3 text-[0.6875rem] text-ink-400">
        <?php echo e(__('analysis.track.orientation_note')); ?>

    </p>
</section>
<?php /**PATH /Users/macbookshop/Desktop/Projects/Developments/DNA-Web/DNA-Frontend/resources/views/partials/tracks.blade.php ENDPATH**/ ?>