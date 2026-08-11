<?php $__env->startSection('title', __('simulator.result.title')); ?>

<?php $__env->startSection('header-actions'); ?>
    <?php if($simulation->succeeded): ?>
        <div class="hidden items-center gap-2 sm:flex">
            <a href="<?php echo e(route('simulator.csv', ['simulation' => $simulation->id])); ?>" class="btn btn-quiet btn-sm"><?php echo e(__('common.actions.csv')); ?></a>
            <a href="<?php echo e(route('simulator.json', ['simulation' => $simulation->id])); ?>" class="btn btn-quiet btn-sm"><?php echo e(__('common.actions.json')); ?></a>
        </div>
    <?php endif; ?>
<?php $__env->stopSection(); ?>

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

    <?php
        $request = $simulation->request();
        $genes = $simulation->genes();
        $statistics = $simulation->statistics();
        $crosstalk = $simulation->crosstalk();
        $performance = $simulation->performance();
        $time = $simulation->time();

        // Gene identity is carried by position and label first; colour only
        // reinforces it. Assigned by the gene's place in the network and never
        // by its rank in any measurement, so a gene keeps its colour across
        // every chart on the page.
        $palette = ['var(--color-brand-500)', 'var(--color-signal-500)', 'var(--color-good-600)'];
        $colours = [];
        foreach ($genes as $index => $gene) {
            $colours[$gene['id']] = $palette[$index % count($palette)];
        }

        $name = fn (array $gene) => __('simulator.genes.' . $gene['label']);
        $precision = $simulation->precision();
    ?>

    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div class="min-w-0">
            <p class="eyebrow"><?php echo e(__('simulator.result.title')); ?></p>
            <h1 class="mt-1 text-lg font-semibold leading-snug">
                <?php echo e(__('simulator.presets.' . $simulation->preset . '.name')); ?>

            </h1>
            <p class="mt-1 text-xs text-ink-400">
                <?php echo e($simulation->created_at->diffForHumans()); ?>

                · <span class="ltr-data"><?php echo e($simulation->cells); ?></span> <?php echo e(__('simulator.units.cells')); ?>

                · <span class="ltr-data"><?php echo e($simulation->minutes); ?></span> <?php echo e(__('simulator.units.min')); ?>

                · <?php echo e(__('simulator.result.seed')); ?> <span class="ltr-data"><?php echo e($simulation->seed); ?></span>
            </p>
        </div>
        <a href="<?php echo e(route('simulator.index')); ?>" class="btn btn-quiet no-print"><?php echo e(__('simulator.result.run_another')); ?></a>
    </div>

    <?php if (! ($simulation->succeeded)): ?>
        <div class="alert mb-6" role="alert">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="mt-0.5 h-5 w-5 shrink-0" aria-hidden="true">
                <circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01" stroke-linecap="round"/>
            </svg>
            <div>
                <p class="font-semibold"><?php echo e(__('simulator.result.failed')); ?></p>
                <p class="mt-1"><?php echo e(__('simulator.result.failed_hint')); ?></p>
            </div>
        </div>
    <?php endif; ?>

    <div class="space-y-6">
        <?php if($simulation->succeeded): ?>
            <?php
                $loudest = collect($statistics)->sortByDesc('cv')->first();
                $worstCrosstalk = $simulation->worstCrosstalk();
            ?>

            <div class="panel metric-strip overflow-hidden">
                <div class="metric">
                    <p class="metric-value"><?php echo e(number_format(($loudest['cv'] ?? 0) * 100, 0)); ?>%</p>
                    <p class="metric-label"><?php echo e(__('simulator.metrics.noisiest')); ?></p>
                    <p class="metric-sub ltr-data"><?php echo e($loudest['id'] ?? ''); ?> · ±<?php echo e(round($precision * 100)); ?>%</p>
                </div>
                <div class="metric">
                    <p class="metric-value"><?php echo e(number_format($loudest['fano'] ?? 0, 1)); ?></p>
                    <p class="metric-label"><?php echo e(__('simulator.metrics.fano')); ?></p>
                    <p class="metric-sub"><?php echo e(__('simulator.metrics.fano_sub')); ?></p>
                </div>
                <div class="metric">
                    <p class="metric-value <?php echo e($worstCrosstalk > 0.15 ? 'text-signal-600' : ''); ?>">
                        <?php echo e(number_format($worstCrosstalk * 100, 0)); ?>%
                    </p>
                    <p class="metric-label"><?php echo e(__('simulator.metrics.crosstalk')); ?></p>
                    <p class="metric-sub"><?php echo e(__('simulator.metrics.crosstalk_sub')); ?></p>
                </div>
                <div class="metric">
                    <p class="metric-value <?php echo e(($performance['availability'] ?? 1) < 0.85 ? 'text-signal-600' : ''); ?>">
                        <?php echo e(number_format(($performance['availability'] ?? 1) * 100, 0)); ?>%
                    </p>
                    <p class="metric-label"><?php echo e(__('simulator.metrics.availability')); ?></p>
                    <p class="metric-sub"><?php echo e(__('simulator.metrics.availability_sub')); ?></p>
                </div>
                <div class="metric">
                    <p class="metric-value ltr-data"><?php echo e(number_format($performance['events'] ?? 0)); ?></p>
                    <p class="metric-label"><?php echo e(__('simulator.metrics.events')); ?></p>
                    <p class="metric-sub ltr-data"><?php echo e(number_format(($performance['wall_ms'] ?? 0) / 1000, 1)); ?> s</p>
                </div>
            </div>

            
            <section class="panel overflow-hidden">
                <div class="panel-head">
                    <div>
                        <h2 class="panel-title"><?php echo e(__('simulator.trajectories.title')); ?></h2>
                        <p class="panel-note"><?php echo e(__('simulator.trajectories.subtitle')); ?></p>
                    </div>
                </div>

                <div class="divide-y divide-line">
                    <?php $__currentLoopData = $genes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $gene): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <?php $entry = $statistics[$gene['id']] ?? []; ?>
                        <div class="p-5">
                            <div class="mb-2 flex flex-wrap items-baseline justify-between gap-2">
                                <span class="flex items-center gap-2 text-sm font-bold">
                                    <span class="inline-block h-2.5 w-2.5 rounded-full"
                                          style="background: <?php echo e($colours[$gene['id']]); ?>;" aria-hidden="true"></span>
                                    <span class="ltr-data"><?php echo e($gene['id']); ?></span>
                                    <span class="font-semibold text-ink-700"><?php echo e($name($gene)); ?></span>
                                </span>
                                <span class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-ink-400">
                                    <span class="ltr-data">
                                        <?php echo e(__('simulator.trajectories.mean')); ?>

                                        <?php echo e(number_format($entry['mean_protein'] ?? 0, 0)); ?>

                                    </span>
                                    <span class="ltr-data">CV <?php echo e(number_format(($entry['cv'] ?? 0) * 100, 0)); ?>%</span>
                                    <span class="ltr-data">
                                        <?php echo e(__('simulator.trajectories.burst')); ?>

                                        <?php echo e(number_format($entry['burst_size'] ?? 0, 1)); ?>

                                    </span>
                                </span>
                            </div>

                            <?php if (isset($component)) { $__componentOriginal7b4d67cd6ea5ab3d1ba997c3d53efe86 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal7b4d67cd6ea5ab3d1ba997c3d53efe86 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.trajectory-chart','data' => ['series' => $simulation->trajectories()[$gene['id']] ?? [],'time' => $time,'colour' => $colours[$gene['id']],'label' => $name($gene)]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('trajectory-chart'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['series' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($simulation->trajectories()[$gene['id']] ?? []),'time' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($time),'colour' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($colours[$gene['id']]),'label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($name($gene))]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal7b4d67cd6ea5ab3d1ba997c3d53efe86)): ?>
<?php $attributes = $__attributesOriginal7b4d67cd6ea5ab3d1ba997c3d53efe86; ?>
<?php unset($__attributesOriginal7b4d67cd6ea5ab3d1ba997c3d53efe86); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal7b4d67cd6ea5ab3d1ba997c3d53efe86)): ?>
<?php $component = $__componentOriginal7b4d67cd6ea5ab3d1ba997c3d53efe86; ?>
<?php unset($__componentOriginal7b4d67cd6ea5ab3d1ba997c3d53efe86); ?>
<?php endif; ?>
                        </div>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </div>

                <p class="border-t border-line px-5 py-3 text-xs leading-relaxed text-ink-500">
                    <?php echo e(__('simulator.trajectories.legend')); ?>

                </p>
            </section>

            
            <section class="panel overflow-hidden">
                <div class="panel-head">
                    <div>
                        <h2 class="panel-title"><?php echo e(__('simulator.distributions.title')); ?></h2>
                        <p class="panel-note"><?php echo e(__('simulator.distributions.subtitle')); ?></p>
                    </div>
                </div>
                <div class="grid gap-6 p-5 sm:grid-cols-2 lg:grid-cols-3">
                    <?php $__currentLoopData = $genes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $gene): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <div>
                            <p class="mb-1 flex items-center gap-2 text-xs font-bold">
                                <span class="inline-block h-2.5 w-2.5 rounded-full"
                                      style="background: <?php echo e($colours[$gene['id']]); ?>;" aria-hidden="true"></span>
                                <span class="ltr-data"><?php echo e($gene['id']); ?></span>
                                <span class="font-semibold text-ink-700"><?php echo e($name($gene)); ?></span>
                            </p>
                            <?php if (isset($component)) { $__componentOriginal4432110e3a12e8314f50a31135b270d8 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal4432110e3a12e8314f50a31135b270d8 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.distribution-chart','data' => ['shape' => $simulation->distributions()[$gene['id']] ?? [],'statistics' => $statistics[$gene['id']] ?? [],'colour' => $colours[$gene['id']],'label' => $name($gene)]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('distribution-chart'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['shape' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($simulation->distributions()[$gene['id']] ?? []),'statistics' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($statistics[$gene['id']] ?? []),'colour' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($colours[$gene['id']]),'label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($name($gene))]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal4432110e3a12e8314f50a31135b270d8)): ?>
<?php $attributes = $__attributesOriginal4432110e3a12e8314f50a31135b270d8; ?>
<?php unset($__attributesOriginal4432110e3a12e8314f50a31135b270d8); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal4432110e3a12e8314f50a31135b270d8)): ?>
<?php $component = $__componentOriginal4432110e3a12e8314f50a31135b270d8; ?>
<?php unset($__componentOriginal4432110e3a12e8314f50a31135b270d8); ?>
<?php endif; ?>
                        </div>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </div>
            </section>

            
            <section class="panel overflow-hidden">
                <div class="panel-head">
                    <div>
                        <h2 class="panel-title"><?php echo e(__('simulator.crosstalk.title')); ?></h2>
                        <p class="panel-note"><?php echo e(__('simulator.crosstalk.subtitle')); ?></p>
                    </div>
                </div>

                <div class="space-y-6 p-5">
                    
                    <div>
                        <h3 class="mb-3 text-xs font-bold uppercase tracking-wider text-ink-500">
                            <?php echo e(__('simulator.crosstalk.attribution_title')); ?>

                        </h3>

                        <div class="space-y-3">
                            <?php $__currentLoopData = $genes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $gene): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <?php $share = $simulation->attribution()[$gene['id']] ?? []; ?>
                                <div>
                                    <div class="mb-1 flex items-baseline justify-between gap-3">
                                        <span class="text-xs font-bold">
                                            <span class="ltr-data"><?php echo e($gene['id']); ?></span>
                                            <span class="ms-1 font-semibold text-ink-500"><?php echo e($name($gene)); ?></span>
                                        </span>
                                        <span class="ltr-data text-[0.6875rem] text-ink-400">
                                            <?php echo e(number_format($share['transcripts'] ?? 0)); ?>

                                            <?php echo e(__('simulator.crosstalk.transcripts')); ?>

                                        </span>
                                    </div>
                                    <div class="track flex h-5 w-full gap-0.5 overflow-hidden rounded-md">
                                        <?php $__currentLoopData = [
                                            'cognate' => 'var(--color-good-600)',
                                            'crosstalk' => 'var(--color-signal-500)',
                                            'leak' => 'var(--color-ink-300)',
                                        ]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $source => $colour): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                            <?php $fraction = (float) ($share[$source] ?? 0); ?>
                                            <?php if($fraction > 0.0005): ?>
                                                <div class="flex items-center justify-center overflow-hidden text-[9px] font-semibold text-white"
                                                     style="width: <?php echo e(round($fraction * 100, 3)); ?>%; background: <?php echo e($colour); ?>;"
                                                     title="<?php echo e(__('simulator.crosstalk.' . $source)); ?> — <?php echo e(round($fraction * 100, 1)); ?>%">
                                                    <?php if($fraction > 0.12): ?>
                                                        <span class="truncate px-1"><?php echo e(round($fraction * 100)); ?>%</span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                    </div>
                                </div>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </div>

                        <p class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-[0.6875rem] text-ink-500">
                            <?php $__currentLoopData = [
                                'cognate' => 'var(--color-good-600)',
                                'crosstalk' => 'var(--color-signal-500)',
                                'leak' => 'var(--color-ink-300)',
                            ]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $source => $colour): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <span class="inline-flex items-center gap-1.5">
                                    <span class="inline-block h-2.5 w-4 rounded-sm" style="background: <?php echo e($colour); ?>;" aria-hidden="true"></span>
                                    <?php echo e(__('simulator.crosstalk.' . $source)); ?>

                                </span>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </p>
                    </div>

                    
                    <div class="border-t border-line pt-5">
                        <div class="grid gap-6 sm:grid-cols-2">
                            <?php if (isset($component)) { $__componentOriginalf6da7b30a65234213cc15bc6daa53ce9 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalf6da7b30a65234213cc15bc6daa53ce9 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.crosstalk-matrix','data' => ['genes' => $crosstalk['genes'] ?? [],'matrix' => $crosstalk['correlation'] ?? [],'caption' => __('simulator.crosstalk.measured'),'note' => __('simulator.crosstalk.measured_note')]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('crosstalk-matrix'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['genes' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($crosstalk['genes'] ?? []),'matrix' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($crosstalk['correlation'] ?? []),'caption' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('simulator.crosstalk.measured')),'note' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('simulator.crosstalk.measured_note'))]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalf6da7b30a65234213cc15bc6daa53ce9)): ?>
<?php $attributes = $__attributesOriginalf6da7b30a65234213cc15bc6daa53ce9; ?>
<?php unset($__attributesOriginalf6da7b30a65234213cc15bc6daa53ce9); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalf6da7b30a65234213cc15bc6daa53ce9)): ?>
<?php $component = $__componentOriginalf6da7b30a65234213cc15bc6daa53ce9; ?>
<?php unset($__componentOriginalf6da7b30a65234213cc15bc6daa53ce9); ?>
<?php endif; ?>

                            <?php if (isset($component)) { $__componentOriginalf6da7b30a65234213cc15bc6daa53ce9 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalf6da7b30a65234213cc15bc6daa53ce9 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.crosstalk-matrix','data' => ['genes' => $crosstalk['genes'] ?? [],'matrix' => $crosstalk['partial'] ?? [],'caption' => __('simulator.crosstalk.partial'),'note' => __('simulator.crosstalk.partial_note', [
                                                    'measured' => __('simulator.crosstalk.measured'),
                                                ])]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('crosstalk-matrix'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['genes' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($crosstalk['genes'] ?? []),'matrix' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($crosstalk['partial'] ?? []),'caption' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('simulator.crosstalk.partial')),'note' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('simulator.crosstalk.partial_note', [
                                                    'measured' => __('simulator.crosstalk.measured'),
                                                ]))]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalf6da7b30a65234213cc15bc6daa53ce9)): ?>
<?php $attributes = $__attributesOriginalf6da7b30a65234213cc15bc6daa53ce9; ?>
<?php unset($__attributesOriginalf6da7b30a65234213cc15bc6daa53ce9); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalf6da7b30a65234213cc15bc6daa53ce9)): ?>
<?php $component = $__componentOriginalf6da7b30a65234213cc15bc6daa53ce9; ?>
<?php unset($__componentOriginalf6da7b30a65234213cc15bc6daa53ce9); ?>
<?php endif; ?>
                        </div>

                        
                        <p class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-[0.6875rem] text-ink-500">
                            <?php $__currentLoopData = [
                                'opposed' => 'rgba(179, 53, 42, .82)',
                                'independent' => 'var(--color-paper)',
                                'together' => 'rgba(43, 80, 143, .82)',
                            ]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $reading => $colour): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <span class="inline-flex items-center gap-1.5">
                                    <span class="inline-block h-2.5 w-4 rounded-sm border border-line"
                                          style="background: <?php echo e($colour); ?>;" aria-hidden="true"></span>
                                    <?php echo e(__('simulator.crosstalk.' . $reading)); ?>

                                </span>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </p>
                    </div>
                </div>
            </section>

            
            <section class="panel overflow-hidden">
                <div class="panel-head">
                    <div>
                        <h2 class="panel-title"><?php echo e(__('simulator.budget.title')); ?></h2>
                        <p class="panel-note"><?php echo e(__('simulator.budget.subtitle')); ?></p>
                    </div>
                </div>

                <div class="space-y-5 p-5">
                    <?php $__currentLoopData = $genes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $gene): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <?php if (isset($component)) { $__componentOriginal08283653d397d825c5a0eb5a76c7caaf = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal08283653d397d825c5a0eb5a76c7caaf = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.noise-budget','data' => ['budget' => ($statistics[$gene['id']]['noise_budget'] ?? []),'label' => $gene['id'] . ' — ' . $name($gene)]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('noise-budget'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['budget' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(($statistics[$gene['id']]['noise_budget'] ?? [])),'label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($gene['id'] . ' — ' . $name($gene))]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal08283653d397d825c5a0eb5a76c7caaf)): ?>
<?php $attributes = $__attributesOriginal08283653d397d825c5a0eb5a76c7caaf; ?>
<?php unset($__attributesOriginal08283653d397d825c5a0eb5a76c7caaf); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal08283653d397d825c5a0eb5a76c7caaf)): ?>
<?php $component = $__componentOriginal08283653d397d825c5a0eb5a76c7caaf; ?>
<?php unset($__componentOriginal08283653d397d825c5a0eb5a76c7caaf); ?>
<?php endif; ?>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>

                    <div class="flex flex-wrap items-center gap-x-4 gap-y-1 border-t border-line pt-4 text-[0.6875rem] text-ink-500">
                        <?php $__currentLoopData = [
                            'floor' => 'var(--color-brand-100)',
                            'bursting' => 'var(--color-brand-200)',
                            'extrinsic' => 'var(--color-brand-400)',
                            'promoter' => 'var(--color-brand-600)',
                            'coupling' => 'var(--color-signal-500)',
                        ]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $source => $colour): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <span class="inline-flex items-center gap-1.5">
                                <span class="inline-block h-2.5 w-4 rounded-sm border border-line" style="background: <?php echo e($colour); ?>;" aria-hidden="true"></span>
                                <?php echo e(__('simulator.budget.' . $source)); ?>

                            </span>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </div>

                    <?php if(! ($performance['control_ensemble'] ?? false)): ?>
                        <p class="text-xs text-ink-400"><?php echo e(__('simulator.budget.no_control')); ?></p>
                    <?php endif; ?>
                </div>
            </section>

            
            <?php if($split = $simulation->decomposition()): ?>
                <section class="panel overflow-hidden">
                    <div class="panel-head">
                        <div>
                            <h2 class="panel-title"><?php echo e(__('simulator.decomposition.title')); ?></h2>
                            <p class="panel-note"><?php echo e(__('simulator.decomposition.subtitle')); ?></p>
                        </div>
                    </div>

                    <div class="p-5">
                        <?php
                            $total = max(0.000001, (float) $split['total']);
                            $intrinsicShare = (float) $split['intrinsic'] / $total * 100;
                        ?>

                        <div class="track flex h-8 w-full gap-0.5 overflow-hidden rounded-md">
                            <div class="flex items-center justify-center text-[10px] font-bold text-white"
                                 style="width: <?php echo e(round($intrinsicShare, 2)); ?>%; background: var(--color-brand-600);">
                                <?php echo e(round($intrinsicShare)); ?>%
                            </div>
                            <div class="flex items-center justify-center text-[10px] font-bold text-white"
                                 style="width: <?php echo e(round(100 - $intrinsicShare, 2)); ?>%; background: var(--color-signal-500);">
                                <?php echo e(round(100 - $intrinsicShare)); ?>%
                            </div>
                        </div>

                        <div class="mt-3 grid gap-4 sm:grid-cols-2">
                            <div>
                                <p class="flex items-center gap-1.5 text-xs font-bold">
                                    <span class="inline-block h-2.5 w-4 rounded-sm" style="background: var(--color-brand-600);" aria-hidden="true"></span>
                                    <?php echo e(__('simulator.decomposition.intrinsic')); ?>

                                    <span class="ltr-data font-normal text-ink-400">η² <?php echo e(number_format($split['intrinsic'], 4)); ?></span>
                                </p>
                                <p class="mt-1 text-xs leading-relaxed text-ink-500">
                                    <?php echo e(__('simulator.decomposition.intrinsic_note')); ?>

                                </p>
                            </div>
                            <div>
                                <p class="flex items-center gap-1.5 text-xs font-bold">
                                    <span class="inline-block h-2.5 w-4 rounded-sm" style="background: var(--color-signal-500);" aria-hidden="true"></span>
                                    <?php echo e(__('simulator.decomposition.extrinsic')); ?>

                                    <span class="ltr-data font-normal text-ink-400">η² <?php echo e(number_format($split['extrinsic'], 4)); ?></span>
                                </p>
                                <p class="mt-1 text-xs leading-relaxed text-ink-500">
                                    <?php echo e(__('simulator.decomposition.extrinsic_note')); ?>

                                </p>
                            </div>
                        </div>

                        <p class="mt-3 border-t border-line pt-3 text-xs text-ink-400">
                            <?php echo e(__('simulator.decomposition.method', [
                                'first' => $split['pair'][0],
                                'second' => $split['pair'][1],
                            ])); ?>

                        </p>
                    </div>
                </section>
            <?php endif; ?>

            
            <?php if($flips = $simulation->switching()): ?>
                <section class="panel overflow-hidden">
                    <div class="panel-head">
                        <div>
                            <h2 class="panel-title"><?php echo e(__('simulator.switching.title')); ?></h2>
                            <p class="panel-note"><?php echo e(__('simulator.switching.subtitle')); ?></p>
                        </div>
                    </div>
                    <div class="grid gap-4 p-5 sm:grid-cols-3">
                        <div>
                            <p class="metric-value"><?php echo e($flips['switches']); ?></p>
                            <p class="metric-label"><?php echo e(__('simulator.switching.flips')); ?></p>
                        </div>
                        <div>
                            <p class="metric-value">
                                <?php echo e($flips['cells_that_switched']); ?><span class="text-ink-300">/<?php echo e($flips['cells']); ?></span>
                            </p>
                            <p class="metric-label"><?php echo e(__('simulator.switching.cells')); ?></p>
                        </div>
                        <div>
                            <p class="metric-value">
                                <?php echo e($flips['mean_dwell_minutes'] !== null ? number_format($flips['mean_dwell_minutes'], 0) : '—'); ?>

                            </p>
                            <p class="metric-label"><?php echo e(__('simulator.switching.dwell')); ?></p>
                        </div>
                    </div>
                    <p class="border-t border-line px-5 py-3 text-xs leading-relaxed text-ink-500">
                        <?php echo e(__('simulator.switching.note')); ?>

                    </p>
                </section>
            <?php endif; ?>

            
            <section class="panel overflow-hidden">
                <div class="panel-head">
                    <div>
                        <h2 class="panel-title"><?php echo e(__('simulator.table.title')); ?></h2>
                        <p class="panel-note"><?php echo e(__('simulator.table.subtitle', ['percent' => round($precision * 100)])); ?></p>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="data-table">
                        <thead>
                        <tr>
                            <th scope="col"><?php echo e(__('simulator.table.gene')); ?></th>
                            <th scope="col"><?php echo e(__('simulator.table.protein')); ?></th>
                            <th scope="col"><?php echo e(__('simulator.table.mrna')); ?></th>
                            <th scope="col">CV</th>
                            <th scope="col"><?php echo e(__('simulator.table.fano')); ?></th>
                            <th scope="col"><?php echo e(__('simulator.table.predicted')); ?></th>
                            <th scope="col"><?php echo e(__('simulator.table.burst')); ?></th>
                            <th scope="col"><?php echo e(__('simulator.table.independent')); ?></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php $__currentLoopData = $genes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $gene): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <?php $entry = $statistics[$gene['id']] ?? []; ?>
                            <tr>
                                <td>
                                    <span class="flex items-center gap-2">
                                        <span class="inline-block h-2.5 w-2.5 shrink-0 rounded-full"
                                              style="background: <?php echo e($colours[$gene['id']]); ?>;" aria-hidden="true"></span>
                                        <span>
                                            <span class="ltr-data font-bold"><?php echo e($gene['id']); ?></span>
                                            <span class="block text-xs text-ink-400"><?php echo e($name($gene)); ?></span>
                                        </span>
                                    </span>
                                </td>
                                <td class="ltr-data">
                                    <?php echo e(number_format($entry['mean_protein'] ?? 0, 0)); ?>

                                    <span class="text-ink-400">± <?php echo e(number_format($entry['sd_protein'] ?? 0, 0)); ?></span>
                                </td>
                                <td class="ltr-data"><?php echo e(number_format($entry['mean_mrna'] ?? 0, 2)); ?></td>
                                <td class="ltr-data"><?php echo e(number_format(($entry['cv'] ?? 0) * 100, 1)); ?>%</td>
                                <td class="ltr-data font-bold"><?php echo e(number_format($entry['fano'] ?? 0, 1)); ?></td>
                                <td class="ltr-data text-ink-400"><?php echo e(number_format($entry['analytic_fano'] ?? 0, 1)); ?></td>
                                <td class="ltr-data"><?php echo e(number_format($entry['burst_size'] ?? 0, 1)); ?></td>
                                <td class="ltr-data text-ink-400"><?php echo e(number_format($entry['effective_samples'] ?? 0, 0)); ?></td>
                            </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </tbody>
                    </table>
                </div>
                <p class="border-t border-line px-5 py-3 text-xs leading-relaxed text-ink-500">
                    <?php echo e(__('simulator.table.note')); ?>

                </p>
            </section>

            
            <section class="panel overflow-hidden">
                <div class="panel-head">
                    <div>
                        <h2 class="panel-title"><?php echo e(__('simulator.conditions.title')); ?></h2>
                        <p class="panel-note"><?php echo e(__('simulator.conditions.subtitle')); ?></p>
                    </div>
                </div>
                <dl class="grid gap-x-6 gap-y-3 p-5 text-sm sm:grid-cols-2 lg:grid-cols-3">
                    <?php $__currentLoopData = [
                        'induction' => round(($request['induction'] ?? 0) * 100) . '%',
                        'crosstalk' => round(($request['crosstalk'] ?? 0) * 100) . '%',
                        'variability' => round(($request['variability'] ?? 0) * 100) . '%',
                        'cells' => $simulation->cells,
                        'duration' => $simulation->minutes . ' ' . __('simulator.units.min'),
                        'seed' => $simulation->seed,
                    ]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $field => $value): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <div class="flex items-baseline justify-between gap-3 border-b border-line pb-2">
                            <dt class="text-xs text-ink-500"><?php echo e(__('simulator.form.' . $field)); ?></dt>
                            <dd class="ltr-data text-xs font-bold"><?php echo e($value); ?></dd>
                        </div>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    <div class="flex items-baseline justify-between gap-3 border-b border-line pb-2">
                        <dt class="text-xs text-ink-500"><?php echo e(__('simulator.form.resources')); ?></dt>
                        <dd class="text-xs font-bold">
                            <?php echo e(($request['resource_coupling'] ?? false) ? __('simulator.conditions.on') : __('simulator.conditions.off')); ?>

                        </dd>
                    </div>
                </dl>
            </section>
        <?php endif; ?>

        <?php if (isset($component)) { $__componentOriginala03f2a88feaa033dd9868f94acac7bee = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginala03f2a88feaa033dd9868f94acac7bee = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.diagnostics','data' => ['items' => $simulation->diagnostics(),'counts' => $simulation->diagnosticCounts(),'namespace' => 'simulator']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('diagnostics'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['items' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($simulation->diagnostics()),'counts' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($simulation->diagnosticCounts()),'namespace' => 'simulator']); ?>
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

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/macbookshop/Desktop/Projects/Developments/DNA-Web/DNA-Frontend/resources/views/simulator/show.blade.php ENDPATH**/ ?>