<?php $__env->startSection('title', __('memory.result.title')); ?>

<?php $__env->startSection('header-actions'); ?>
    <?php if($design->succeeded): ?>
        <div class="hidden items-center gap-2 sm:flex">
            <a href="<?php echo e(route('memory.fasta', ['design' => $design->id])); ?>" class="btn btn-quiet btn-sm">FASTA</a>
            <a href="<?php echo e(route('memory.json', ['design' => $design->id])); ?>" class="btn btn-quiet btn-sm"><?php echo e(__('common.actions.json')); ?></a>
        </div>
    <?php endif; ?>
<?php $__env->stopSection(); ?>

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

    <?php
        $request = $design->request();
        $recommendation = $design->recommendation();
        $winner = $recommendation['architecture'] ?? null;
        $comparison = collect($design->comparison());

        // Colour follows the architecture, not its rank, so the winner does not
        // change colour when the parameters do.
        $colours = [
            'recombinase' => 'var(--color-brand-500)',
            'recombinase_reversible' => 'var(--color-brand-500)',
            'toggle' => 'var(--color-signal-500)',
        ];

        $writeMinutes = (float) ($request['signal_minutes'] ?? 60);
        $holdMinutes = (float) ($request['hold_hours'] ?? 24) * 60;

        // Both architectures reduced to one comparable quantity: how much of the
        // memory is set. See the chart component for why this matters.
        $state = function (array $outcome, string $phase) {
            $found = collect($outcome['phases'] ?? [])->firstWhere('name', $phase);
            if (! $found) {
                return [];
            }

            $series = $found['series'];
            $points = [];
            foreach ($found['minutes'] as $index => $minute) {
                if (isset($series['flipped'])) {
                    $value = $series['flipped'][$index] ?? 0;
                } else {
                    $set = $series['set'][$index] ?? 0;
                    $reset = $series['reset'][$index] ?? 0;
                    $value = ($set + $reset) > 0 ? $set / ($set + $reset) : 0;
                }
                $points[] = [$minute, $value];
            }
            return $points;
        };

        $lines = fn (string $phase) => $design->comparison() ? array_values(array_map(
            fn ($entry) => [
                'label' => __('memory.architectures.' . $entry['architecture'] . '.name'),
                'colour' => $colours[$entry['architecture']] ?? 'var(--color-ink-500)',
                'points' => $state($design->outcomeFor($entry['architecture']), $phase),
            ],
            $design->comparison()
        )) : [];

        $hours = function (?float $value) {
            if ($value === null) {
                return null;
            }
            return $value;
        };
    ?>

    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div class="min-w-0">
            <p class="eyebrow"><?php echo e(__('memory.result.title')); ?></p>
            <h1 class="mt-1 text-lg font-semibold leading-snug">
                <?php echo e(__('memory.signals.' . $design->signal)); ?> → <?php echo e(__('memory.chassis.' . $design->chassis)); ?>

            </h1>
            <p class="mt-1 text-xs text-ink-400">
                <?php echo e($design->created_at->diffForHumans()); ?>

                · <?php echo e(__('memory.result.hold')); ?> <span class="ltr-data"><?php echo e($design->hold_hours); ?></span> <?php echo e(__('memory.units.hours')); ?>

                · <?php echo e(__('memory.result.exposure')); ?> <span class="ltr-data"><?php echo e(round($writeMinutes)); ?></span> <?php echo e(__('memory.units.min')); ?>

            </p>
        </div>
        <a href="<?php echo e(route('memory.index')); ?>" class="btn btn-quiet no-print"><?php echo e(__('memory.result.design_another')); ?></a>
    </div>

    <?php if (! ($design->succeeded)): ?>
        <div class="alert mb-6" role="alert">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="mt-0.5 h-5 w-5 shrink-0" aria-hidden="true">
                <circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01" stroke-linecap="round"/>
            </svg>
            <div>
                <p class="font-semibold"><?php echo e(__('memory.result.refused')); ?></p>
                <p class="mt-1"><?php echo e(__('memory.result.refused_hint')); ?></p>
            </div>
        </div>
    <?php endif; ?>

    <div class="space-y-6">
        <?php if($design->succeeded && $winner): ?>
            
            <section class="panel overflow-hidden">
                <div class="p-5 sm:p-6">
                    <p class="eyebrow"><?php echo e(__('memory.result.recommended')); ?></p>
                    <h2 class="mt-1.5 text-2xl font-bold leading-tight">
                        <?php echo e(__('memory.architectures.' . $winner . '.name')); ?>

                    </h2>
                    <p class="mt-2 max-w-2xl text-sm leading-relaxed text-ink-700">
                        <?php echo e(__('memory.architectures.' . $winner . '.why')); ?>

                    </p>

                    <?php if($design->isCloseCall()): ?>
                        <p class="mt-3 inline-flex items-start gap-2 rounded-lg bg-signal-50 px-3 py-2 text-xs leading-relaxed text-signal-600">
                            <?php echo e(__('memory.result.close_call', [
                                'other' => __('memory.architectures.' . ($recommendation['runner_up'] ?? '') . '.name'),
                                'gap' => number_format(($recommendation['gap'] ?? 0) * 100, 1),
                            ])); ?>

                        </p>
                    <?php endif; ?>
                </div>

                <div class="metric-strip border-t border-line">
                    <?php $best = $comparison->firstWhere('architecture', $winner) ?? []; ?>
                    <div class="metric">
                        <p class="metric-value"><?php echo e(number_format(($best['retention'] ?? 0) * 100, 0)); ?>%</p>
                        <p class="metric-label"><?php echo e(__('memory.metrics.retention')); ?></p>
                    </div>
                    <div class="metric">
                        <p class="metric-value <?php echo e(($best['false_write_share'] ?? 0) > 0.05 ? 'text-signal-600' : ''); ?>">
                            <?php echo e(number_format(($best['false_write_share'] ?? 0) * 100, 1)); ?>%
                        </p>
                        <p class="metric-label"><?php echo e(__('memory.metrics.false_writes')); ?></p>
                    </div>
                    <div class="metric">
                        <?php $out = $design->outcomeFor($winner); ?>
                        <p class="metric-value ltr-data">
                            <?php echo e($out['write_minutes_to_half'] !== null ? round($out['write_minutes_to_half']) : '—'); ?>

                        </p>
                        <p class="metric-label"><?php echo e(__('memory.metrics.write_time')); ?></p>
                    </div>
                    <div class="metric">
                        <p class="metric-value"><?php echo e($out['stores_in_dna'] ?? false ? __('memory.metrics.in_dna') : __('memory.metrics.in_protein')); ?></p>
                        <p class="metric-label"><?php echo e(__('memory.metrics.stored_in')); ?></p>
                    </div>
                    <div class="metric">
                        <p class="metric-value ltr-data"><?php echo e(number_format($design->totals()['length'] ?? 0)); ?></p>
                        <p class="metric-label"><?php echo e(__('memory.metrics.length')); ?> (<?php echo e(__('analysis.units.bp')); ?>)</p>
                    </div>
                </div>
            </section>

            
            <section class="panel overflow-hidden">
                <div class="panel-head">
                    <div>
                        <h2 class="panel-title"><?php echo e(__('memory.compare.title')); ?></h2>
                        <p class="panel-note"><?php echo e(__('memory.compare.subtitle')); ?></p>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="data-table">
                        <thead>
                        <tr>
                            <th scope="col"><?php echo e(__('memory.compare.criterion')); ?></th>
                            <?php $__currentLoopData = $design->comparison(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $entry): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <th scope="col">
                                    <span class="flex items-center gap-1.5">
                                        <span class="inline-block h-2.5 w-2.5 rounded-full"
                                              style="background: <?php echo e($colours[$entry['architecture']] ?? 'var(--color-ink-500)'); ?>;" aria-hidden="true"></span>
                                        <?php echo e(__('memory.architectures.' . $entry['architecture'] . '.name')); ?>

                                        <?php if($entry['architecture'] === $winner): ?>
                                            <span class="chip chip-good"><?php echo e(__('memory.compare.chosen')); ?></span>
                                        <?php endif; ?>
                                    </span>
                                </th>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </tr>
                        </thead>
                        <tbody>
                        <?php
                            $rows = [
                                'retention' => fn ($e) => number_format($e['retention'] * 100, 0) . '%',
                                'fidelity' => fn ($e) => number_format($e['fidelity'] * 100, 0) . '%',
                                'speed' => fn ($e) => number_format($e['speed'] * 100, 0) . '%',
                                'cost' => fn ($e) => number_format($e['cost'] * 100, 0) . '%',
                            ];
                        ?>

                        <?php $__currentLoopData = $rows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $format): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <tr>
                                <td>
                                    <span class="font-semibold"><?php echo e(__('memory.compare.' . $key)); ?></span>
                                    <span class="block text-xs text-ink-400"><?php echo e(__('memory.compare.' . $key . '_note')); ?></span>
                                </td>
                                <?php $__currentLoopData = $design->comparison(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $entry): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <td class="ltr-data <?php echo e($entry['disqualified'] ? 'text-ink-300' : ''); ?>">
                                        <?php echo e($format($entry)); ?>

                                    </td>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>

                        <tr>
                            <td>
                                <span class="font-semibold"><?php echo e(__('memory.compare.survives_division')); ?></span>
                                <span class="block text-xs text-ink-400"><?php echo e(__('memory.compare.survives_division_note')); ?></span>
                            </td>
                            <?php $__currentLoopData = $design->comparison(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $entry): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <?php $outcome = $design->outcomeFor($entry['architecture']); ?>
                                <td>
                                    <span class="chip <?php echo e(($outcome['stores_in_dna'] ?? false) ? 'chip-good' : 'chip-signal'); ?>">
                                        <?php echo e(($outcome['stores_in_dna'] ?? false) ? __('memory.compare.yes_dna') : __('memory.compare.needs_expression')); ?>

                                    </span>
                                </td>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </tr>

                        <tr>
                            <td>
                                <span class="font-semibold"><?php echo e(__('memory.compare.erasable')); ?></span>
                                <span class="block text-xs text-ink-400"><?php echo e(__('memory.compare.erasable_note')); ?></span>
                            </td>
                            <?php $__currentLoopData = $design->comparison(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $entry): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <?php $outcome = $design->outcomeFor($entry['architecture']); ?>
                                <td>
                                    <span class="chip <?php echo e(($outcome['reversible'] ?? false) ? 'chip-good' : 'chip-muted'); ?>">
                                        <?php echo e(($outcome['reversible'] ?? false) ? __('memory.compare.yes') : __('memory.compare.no')); ?>

                                    </span>
                                </td>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </tr>

                        <tr>
                            <td><span class="font-semibold"><?php echo e(__('memory.compare.total')); ?></span></td>
                            <?php $__currentLoopData = $design->comparison(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $entry): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <td class="ltr-data font-bold <?php echo e($entry['architecture'] === $winner ? 'text-brand-600' : ''); ?>">
                                    <?php if($entry['disqualified']): ?>
                                        <span class="chip chip-alert">
                                            <?php echo e(__('memory.compare.excluded.' . ($entry['disqualified_reason'] ?? 'never_written'))); ?>

                                        </span>
                                    <?php else: ?>
                                        <?php echo e(number_format($entry['total'] * 100, 1)); ?>

                                    <?php endif; ?>
                                </td>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </tr>
                        </tbody>
                    </table>
                </div>

                <p class="border-t border-line px-5 py-3 text-xs leading-relaxed text-ink-500">
                    <?php echo e(__('memory.compare.weights')); ?>

                </p>
            </section>

            
            <section class="panel overflow-hidden">
                <div class="panel-head">
                    <div>
                        <h2 class="panel-title"><?php echo e(__('memory.dynamics.title')); ?></h2>
                        <p class="panel-note"><?php echo e(__('memory.dynamics.subtitle')); ?></p>
                    </div>
                </div>

                <div class="grid gap-6 p-5 sm:grid-cols-2">
                    <?php if (isset($component)) { $__componentOriginalbfec556595196bfda912b3e7af630da6 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalbfec556595196bfda912b3e7af630da6 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.memory-chart','data' => ['series' => $lines('write'),'span' => $writeMinutes,'caption' => __('memory.dynamics.write'),'unit' => __('memory.units.min')]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('memory-chart'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['series' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($lines('write')),'span' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($writeMinutes),'caption' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('memory.dynamics.write')),'unit' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('memory.units.min'))]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalbfec556595196bfda912b3e7af630da6)): ?>
<?php $attributes = $__attributesOriginalbfec556595196bfda912b3e7af630da6; ?>
<?php unset($__attributesOriginalbfec556595196bfda912b3e7af630da6); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalbfec556595196bfda912b3e7af630da6)): ?>
<?php $component = $__componentOriginalbfec556595196bfda912b3e7af630da6; ?>
<?php unset($__componentOriginalbfec556595196bfda912b3e7af630da6); ?>
<?php endif; ?>

                    <?php if (isset($component)) { $__componentOriginalbfec556595196bfda912b3e7af630da6 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalbfec556595196bfda912b3e7af630da6 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.memory-chart','data' => ['series' => $lines('hold'),'span' => $holdMinutes,'caption' => __('memory.dynamics.hold'),'unit' => __('memory.units.min')]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('memory-chart'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['series' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($lines('hold')),'span' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($holdMinutes),'caption' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('memory.dynamics.hold')),'unit' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('memory.units.min'))]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalbfec556595196bfda912b3e7af630da6)): ?>
<?php $attributes = $__attributesOriginalbfec556595196bfda912b3e7af630da6; ?>
<?php unset($__attributesOriginalbfec556595196bfda912b3e7af630da6); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalbfec556595196bfda912b3e7af630da6)): ?>
<?php $component = $__componentOriginalbfec556595196bfda912b3e7af630da6; ?>
<?php unset($__componentOriginalbfec556595196bfda912b3e7af630da6); ?>
<?php endif; ?>
                </div>

                <div class="flex flex-wrap items-center gap-x-4 gap-y-1 border-t border-line px-5 py-3 text-[0.6875rem] text-ink-500">
                    <?php $__currentLoopData = $design->comparison(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $entry): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <span class="inline-flex items-center gap-1.5">
                            <span class="inline-block h-2.5 w-4 rounded-sm"
                                  style="background: <?php echo e($colours[$entry['architecture']] ?? 'var(--color-ink-500)'); ?>;" aria-hidden="true"></span>
                            <?php echo e(__('memory.architectures.' . $entry['architecture'] . '.name')); ?>

                        </span>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    <span class="text-ink-400"><?php echo e(__('memory.dynamics.legend')); ?></span>
                </div>
            </section>

            
            <?php $orientation = $design->orientation(); ?>
            <?php if($orientation): ?>
                <section class="panel overflow-hidden">
                    <div class="panel-head">
                        <div>
                            <h2 class="panel-title"><?php echo e(__('memory.orientation.title')); ?></h2>
                            <p class="panel-note"><?php echo e(__('memory.orientation.subtitle')); ?></p>
                        </div>
                        <span class="chip <?php echo e($orientation['decided_by_sequence'] ? 'chip-good' : 'chip-muted'); ?>">
                            <?php echo e(__('memory.orientation.' . ($orientation['decided_by_sequence'] ? $orientation['preferred'] : 'either'))); ?>

                        </span>
                    </div>

                    <div class="grid gap-5 p-5 sm:grid-cols-2">
                        <?php $__currentLoopData = ['forward', 'reverse']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $side): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <?php
                                $panel = $orientation[$side];
                                $isChosen = $orientation['decided_by_sequence'] && $orientation['preferred'] === $side;
                            ?>
                            <div class="rounded-xl border p-4 <?php echo e($isChosen ? 'border-brand-600 bg-brand-50' : 'border-line'); ?>">
                                <div class="mb-2 flex items-baseline justify-between gap-2">
                                    <span class="text-sm font-bold"><?php echo e(__('memory.orientation.' . $side)); ?></span>
                                    <span class="ltr-data text-xs text-ink-400">
                                        <?php echo e(__('memory.orientation.risk')); ?> <?php echo e(number_format($panel['risk'], 2)); ?>

                                    </span>
                                </div>

                                <dl class="space-y-1.5 text-xs">
                                    <?php $__currentLoopData = [
                                        'promoters_outward' => $panel['counts']['promoters_outward'],
                                        'promoters_inward' => $panel['counts']['promoters_inward'],
                                        'terminators' => $panel['counts']['terminators'],
                                        'repeats' => $panel['counts']['repeats'],
                                    ]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $value): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                        <div class="flex items-baseline justify-between gap-3">
                                            <dt class="text-ink-500"><?php echo e(__('memory.orientation.' . $key)); ?></dt>
                                            <dd class="ltr-data font-bold <?php echo e($key === 'promoters_outward' && $value > 0 ? 'text-signal-600' : ''); ?>">
                                                <?php echo e($value); ?>

                                            </dd>
                                        </div>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                    <div class="flex items-baseline justify-between gap-3 border-t border-line pt-1.5">
                                        <dt class="text-ink-500">GC</dt>
                                        <dd class="ltr-data font-bold"><?php echo e(number_format($panel['gc_percent'], 1)); ?>%</dd>
                                    </div>
                                </dl>
                            </div>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </div>

                    <p class="border-t border-line px-5 py-3 text-xs leading-relaxed text-ink-500">
                        <?php echo e(__('memory.orientation.explanation')); ?>

                        <?php if($design->composition()['is_default_payload'] ?? false): ?>
                            <span class="block mt-1 text-ink-400"><?php echo e(__('memory.orientation.default_payload')); ?></span>
                        <?php endif; ?>
                    </p>
                </section>
            <?php endif; ?>

            
            <section class="panel overflow-hidden">
                <div class="panel-head">
                    <div>
                        <h2 class="panel-title"><?php echo e(__('memory.construct.title')); ?></h2>
                        <p class="panel-note"><?php echo e(__('memory.construct.subtitle')); ?></p>
                    </div>
                    <a href="<?php echo e(route('memory.fasta', ['design' => $design->id])); ?>" class="btn btn-quiet btn-sm no-print">
                        <?php echo e(__('memory.construct.download')); ?>

                    </a>
                </div>

                <div class="space-y-6 p-5">
                    <?php $__currentLoopData = $design->constructs(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $unit): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <div>
                            <div class="mb-2 flex flex-wrap items-baseline justify-between gap-2">
                                <span class="ltr-data text-sm font-bold"><?php echo e($unit['name']); ?></span>
                                <span class="flex items-center gap-2 text-xs text-ink-400">
                                    <span class="chip chip-muted"><?php echo e(__('memory.purposes.' . $unit['purpose'])); ?></span>
                                    <span class="ltr-data"><?php echo e(number_format($unit['length'])); ?> <?php echo e(__('analysis.units.bp')); ?></span>
                                    <span class="ltr-data"><?php echo e($unit['resolved_percent']); ?>% <?php echo e(__('memory.construct.resolved')); ?></span>
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
                            'att' => 'var(--color-brand-700)',
                            'payload' => 'var(--color-ink-700)',
                            'terminator' => 'var(--color-alert-600)',
                        ]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $role => $colour): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <span class="inline-flex items-center gap-1.5">
                                <span class="inline-block h-2.5 w-4 rounded-sm" style="background: <?php echo e($colour); ?>;" aria-hidden="true"></span>
                                <?php echo e(__('memory.roles.' . $role)); ?>

                            </span>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </div>
                </div>
            </section>

            
            <section class="panel overflow-hidden">
                <div class="panel-head">
                    <div>
                        <h2 class="panel-title"><?php echo e(__('memory.parts.title')); ?></h2>
                        <p class="panel-note"><?php echo e(__('memory.parts.subtitle')); ?></p>
                    </div>
                    <?php if($design->synthesis()['difficult'] ?? false): ?>
                        <span class="chip chip-signal"><?php echo e(__('memory.parts.difficult')); ?></span>
                    <?php endif; ?>
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
                        <?php $__currentLoopData = $design->parts(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $part): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
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
                                <td><?php echo e(__('memory.roles.' . $part['role'])); ?></td>
                                <td>
                                    <span class="chip <?php echo e($part['provenance'] === 'literal' ? 'chip-good' : 'chip-muted'); ?>">
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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.diagnostics','data' => ['items' => $design->diagnostics(),'counts' => $design->diagnosticCounts(),'namespace' => 'memory']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('diagnostics'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['items' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($design->diagnostics()),'counts' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($design->diagnosticCounts()),'namespace' => 'memory']); ?>
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

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/macbookshop/Desktop/Projects/Developments/DNA-Web/DNA-Frontend/resources/views/memory/show.blade.php ENDPATH**/ ?>