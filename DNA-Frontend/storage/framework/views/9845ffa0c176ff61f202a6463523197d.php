<?php $__env->startSection('title', __('compiler.result.title')); ?>

<?php $__env->startSection('header-actions'); ?>
    <?php if($circuit->succeeded): ?>
        <div class="hidden items-center gap-2 sm:flex">
            <a href="<?php echo e(route('compiler.fasta', ['circuit' => $circuit->id])); ?>" class="btn btn-quiet btn-sm">FASTA</a>
            <a href="<?php echo e(route('compiler.json', ['circuit' => $circuit->id])); ?>" class="btn btn-quiet btn-sm"><?php echo e(__('common.actions.json')); ?></a>
        </div>
    <?php endif; ?>
<?php $__env->stopSection(); ?>

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

    <?php
        $totals = $circuit->totals();
        $counts = $circuit->diagnosticCounts();
    ?>

    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div class="min-w-0">
            <p class="eyebrow"><?php echo e(__('compiler.result.title')); ?></p>
            <p class="mt-1 max-w-2xl text-lg font-semibold leading-snug"><?php echo e($circuit->source_text); ?></p>
            <p class="mt-1 text-xs text-ink-400">
                <?php echo e(__('compiler.result.created')); ?> <?php echo e($circuit->created_at->diffForHumans()); ?>

                · <?php echo e(__('compiler.result.language')); ?>:
                <?php echo e(__('compiler.languages.' . ($circuit->language ?: 'unknown'))); ?>

            </p>
        </div>
        <a href="<?php echo e(route('compiler.index')); ?>" class="btn btn-quiet no-print"><?php echo e(__('common.actions.new_analysis')); ?></a>
    </div>

    <?php if($circuit->expression): ?>
        <div class="panel mb-6 p-4">
            <p class="eyebrow"><?php echo e(__('compiler.result.expression')); ?></p>
            <p class="ltr-data mt-1.5 text-sm font-bold text-brand-600"><?php echo e($circuit->expression); ?></p>
        </div>
    <?php endif; ?>

    <?php if (! ($circuit->succeeded)): ?>
        <div class="alert mb-6" role="alert">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="mt-0.5 h-5 w-5 shrink-0" aria-hidden="true">
                <circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01" stroke-linecap="round"/>
            </svg>
            <div>
                <p class="font-semibold"><?php echo e(__('compiler.result.failed')); ?></p>
                <p class="mt-1"><?php echo e(__('compiler.result.failed_hint')); ?></p>
            </div>
        </div>
    <?php endif; ?>

    <div class="space-y-6">
        <?php if($circuit->succeeded): ?>
            <div class="panel metric-strip overflow-hidden">
                <div class="metric">
                    <p class="metric-value"><?php echo e($totals['units'] ?? 0); ?></p>
                    <p class="metric-label"><?php echo e(__('compiler.metrics.units')); ?></p>
                </div>
                <div class="metric">
                    <p class="metric-value"><?php echo e(number_format($totals['length'] ?? 0)); ?></p>
                    <p class="metric-label"><?php echo e(__('compiler.metrics.length')); ?> (<?php echo e(__('analysis.units.bp')); ?>)</p>
                </div>
                <div class="metric">
                    <p class="metric-value"><?php echo e(count($circuit->parts())); ?></p>
                    <p class="metric-label"><?php echo e(__('compiler.metrics.parts')); ?></p>
                </div>
                <div class="metric">
                    <p class="metric-value"><?php echo e($totals['resolved_percent'] ?? 0); ?>%</p>
                    <p class="metric-label"><?php echo e(__('compiler.metrics.resolved')); ?></p>
                </div>
                <div class="metric">
                    <p class="metric-value <?php echo e(($counts['warning'] ?? 0) > 0 ? 'text-signal-600' : ''); ?>">
                        <?php echo e($counts['warning'] ?? 0); ?>

                    </p>
                    <p class="metric-label"><?php echo e(__('compiler.metrics.warnings')); ?></p>
                </div>
            </div>

            <?php if($circuit->gates()): ?>
                <section class="panel overflow-hidden">
                    <div class="panel-head">
                        <div>
                            <h2 class="panel-title"><?php echo e(__('compiler.logic.title')); ?></h2>
                            <p class="panel-note"><?php echo e(__('compiler.logic.subtitle')); ?></p>
                        </div>
                    </div>
                    <div class="p-5">
                        <?php if (isset($component)) { $__componentOriginaldce73f0a0db6bd772400557490f7d92f = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginaldce73f0a0db6bd772400557490f7d92f = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.circuit-diagram','data' => ['gates' => $circuit->gates()]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('circuit-diagram'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['gates' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($circuit->gates())]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginaldce73f0a0db6bd772400557490f7d92f)): ?>
<?php $attributes = $__attributesOriginaldce73f0a0db6bd772400557490f7d92f; ?>
<?php unset($__attributesOriginaldce73f0a0db6bd772400557490f7d92f); ?>
<?php endif; ?>
<?php if (isset($__componentOriginaldce73f0a0db6bd772400557490f7d92f)): ?>
<?php $component = $__componentOriginaldce73f0a0db6bd772400557490f7d92f; ?>
<?php unset($__componentOriginaldce73f0a0db6bd772400557490f7d92f); ?>
<?php endif; ?>
                    </div>
                </section>
            <?php endif; ?>

            <section class="panel overflow-hidden">
                <div class="panel-head">
                    <div>
                        <h2 class="panel-title"><?php echo e(__('compiler.construct.title')); ?></h2>
                        <p class="panel-note"><?php echo e(__('compiler.construct.subtitle')); ?></p>
                    </div>
                    <a href="<?php echo e(route('compiler.fasta', ['circuit' => $circuit->id])); ?>" class="btn btn-quiet btn-sm no-print">
                        <?php echo e(__('compiler.construct.download')); ?>

                    </a>
                </div>

                <div class="space-y-6 p-5">
                    <?php $__currentLoopData = $circuit->units(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $unit): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <div>
                            <div class="mb-2 flex flex-wrap items-baseline justify-between gap-2">
                                <span class="ltr-data text-sm font-bold"><?php echo e($unit['name']); ?></span>
                                <span class="flex items-center gap-2 text-xs text-ink-400">
                                    <span class="chip chip-muted"><?php echo e(__('compiler.gates.' . $unit['purpose'])); ?></span>
                                    <span class="ltr-data"><?php echo e(number_format($unit['length'])); ?> <?php echo e(__('analysis.units.bp')); ?></span>
                                    <span class="ltr-data"><?php echo e($unit['known_fraction']); ?>% <?php echo e(__('compiler.construct.resolved')); ?></span>
                                </span>
                            </div>
                            <?php if (isset($component)) { $__componentOriginala8d4629dfa8bb827a069fa9da3f1e091 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginala8d4629dfa8bb827a069fa9da3f1e091 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.part-map','data' => ['unit' => $unit]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('part-map'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['unit' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($unit)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginala8d4629dfa8bb827a069fa9da3f1e091)): ?>
<?php $attributes = $__attributesOriginala8d4629dfa8bb827a069fa9da3f1e091; ?>
<?php unset($__attributesOriginala8d4629dfa8bb827a069fa9da3f1e091); ?>
<?php endif; ?>
<?php if (isset($__componentOriginala8d4629dfa8bb827a069fa9da3f1e091)): ?>
<?php $component = $__componentOriginala8d4629dfa8bb827a069fa9da3f1e091; ?>
<?php unset($__componentOriginala8d4629dfa8bb827a069fa9da3f1e091); ?>
<?php endif; ?>
                        </div>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>

                    <div class="track flex flex-wrap items-center gap-x-4 gap-y-1 border-t border-line pt-4 text-[0.6875rem] text-ink-500">
                        
                        <?php $__currentLoopData = [
                            'promoter' => 'var(--color-brand-500)',
                            'rbs' => 'var(--color-signal-500)',
                            'cds' => 'var(--color-good-600)',
                            'terminator' => 'var(--color-alert-600)',
                            'tag' => 'var(--color-ink-500)',
                        ]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $role => $colour): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <span class="inline-flex items-center gap-1.5">
                                <span class="inline-block h-2.5 w-4 rounded-sm" style="background: <?php echo e($colour); ?>;" aria-hidden="true"></span>
                                <?php echo e(__('compiler.roles.' . $role)); ?>

                            </span>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        <span class="text-ink-400"><?php echo e(__('compiler.provenance.placeholder_note')); ?></span>
                    </div>
                </div>
            </section>

            <section class="panel overflow-hidden">
                <div class="panel-head">
                    <div>
                        <h2 class="panel-title"><?php echo e(__('compiler.parts.title')); ?></h2>
                        <p class="panel-note"><?php echo e(__('compiler.parts.subtitle')); ?></p>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="data-table">
                        <thead>
                        <tr>
                            <th scope="col"><?php echo e(__('compiler.parts.id')); ?></th>
                            <th scope="col"><?php echo e(__('compiler.parts.name')); ?></th>
                            <th scope="col"><?php echo e(__('compiler.parts.role')); ?></th>
                            <th scope="col"><?php echo e(__('compiler.parts.provenance')); ?></th>
                            <th scope="col"><?php echo e(__('compiler.parts.length')); ?></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php $__currentLoopData = $circuit->parts(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $part): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <tr>
                                <td class="ltr-data font-bold">
                                    <?php if($part['registry_url']): ?>
                                        <a href="<?php echo e($part['registry_url']); ?>" target="_blank" rel="noopener noreferrer"
                                           class="text-brand-600 underline underline-offset-2"><?php echo e($part['id']); ?></a>
                                    <?php else: ?>
                                        <?php echo e($part['id']); ?>

                                    <?php endif; ?>
                                </td>
                                <td class="text-ink-700"><?php echo e($part['name']); ?></td>
                                <td><?php echo e(__('compiler.roles.' . $part['role'])); ?></td>
                                <td>
                                    <span class="chip <?php echo e(match ($part['provenance']) {
                                        'literal' => 'chip-good',
                                        'designed' => 'chip-signal',
                                        default => 'chip-muted',
                                    }); ?>">
                                        <?php echo e(__('compiler.provenance.' . $part['provenance'])); ?>

                                    </span>
                                </td>
                                <td class="ltr-data"><?php echo e(number_format($part['length'])); ?></td>
                            </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </tbody>
                    </table>
                </div>
            </section>
        <?php endif; ?>

        <?php if (isset($component)) { $__componentOriginala03f2a88feaa033dd9868f94acac7bee = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginala03f2a88feaa033dd9868f94acac7bee = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.diagnostics','data' => ['items' => $circuit->diagnostics(),'counts' => $counts,'namespace' => 'compiler']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('diagnostics'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['items' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($circuit->diagnostics()),'counts' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($counts),'namespace' => 'compiler']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginala03f2a88feaa033dd9868f94acac7bee)): ?>
<?php $attributes = $__attributesOriginala03f2a88feaa033dd9868f94acac7bee; ?>
<?php unset($__attributesOriginala03f2a88feaa033dd9868f94acac7bee); ?>
<?php endif; ?>
<?php if (isset($__componentOriginala03f2a88feaa033dd9868f94acac7bee)): ?>
<?php $component = $__componentOriginala03f2a88feaa033dd9868f94acac7bee; ?>
<?php unset($__componentOriginala03f2a88feaa033dd9868f94acac7bee); ?>
<?php endif; ?>
    </div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/macbookshop/Desktop/Projects/Developments/DNA-Web/DNA-Frontend/resources/views/compiler/show.blade.php ENDPATH**/ ?>