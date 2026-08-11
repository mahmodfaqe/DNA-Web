<?php
    $comparisons = $analysis->comparisons();
    $limits = $analysis->payload['limits'] ?? [];
?>

<?php if(empty($comparisons)): ?>
    <section class="panel p-5">
        <h2 class="panel-title"><?php echo e(__('analysis.compare.title')); ?></h2>
        <p class="mt-1 text-sm text-ink-500"><?php echo e(__('analysis.compare.none')); ?></p>
    </section>
<?php else: ?>
    <?php $__currentLoopData = $comparisons; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $comparison): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <?php
            $identity = (float) ($comparison['identity_percent'] ?? 0);
            $counts = array_filter($comparison['counts'] ?? []);
            $effects = array_filter($comparison['effects'] ?? []);
            $variants = $comparison['variants'] ?? [];
        ?>

        <section class="panel overflow-hidden">
            <div class="panel-head">
                <div>
                    <h2 class="panel-title">
                        <span class="ltr-data"><?php echo e($comparison['reference_id']); ?></span>
                        <span class="mx-1 font-normal text-ink-400"><?php echo e(__('analysis.compare.against')); ?></span>
                        <span class="ltr-data"><?php echo e($comparison['alternative_id']); ?></span>
                    </h2>
                    <p class="panel-note">
                        <?php echo e(__('analysis.compare.method')); ?>:
                        <?php echo e(__('analysis.methods.' . ($comparison['method'] ?? 'global_alignment'))); ?>

                        · <?php echo e(__('analysis.compare.aligned_length')); ?>

                        <span class="ltr-data"><?php echo e(number_format($comparison['aligned_length'] ?? 0)); ?></span>
                    </p>
                </div>

                <div class="flex items-center gap-2">
                    <span class="chip <?php echo e($identity >= 99 ? 'chip-good' : ($identity >= 90 ? 'chip' : 'chip-signal')); ?>">
                        <?php echo e(__('analysis.compare.identity')); ?>

                        <span class="ltr-data"><?php echo e(number_format($identity, 2)); ?>%</span>
                    </span>
                    <span class="chip <?php echo e(($comparison['total_variants'] ?? 0) > 0 ? 'chip-signal' : 'chip-muted'); ?>">
                        <?php echo e(__('analysis.compare.total')); ?>

                        <span class="ltr-data"><?php echo e(number_format($comparison['total_variants'] ?? 0)); ?></span>
                    </span>
                </div>
            </div>

            <?php if(($comparison['method'] ?? '') === 'positional_diff'): ?>
                <p class="border-b border-line bg-signal-50 px-5 py-2.5 text-xs text-signal-600">
                    <?php echo e(__('analysis.methods.positional_note', ['length' => $limits['align_max_bp'] ?? 3000])); ?>

                </p>
            <?php endif; ?>

            <?php if(($comparison['total_variants'] ?? 0) === 0): ?>
                <p class="px-5 py-6 text-center text-sm text-good-600"><?php echo e(__('analysis.compare.identical')); ?></p>
            <?php else: ?>
                <div class="grid gap-5 border-b border-line p-5 sm:grid-cols-2">
                    <div>
                        <p class="eyebrow"><?php echo e(__('analysis.compare.counts_title')); ?></p>
                        <ul class="mt-2 space-y-1 text-sm">
                            <?php $__currentLoopData = $counts; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $type => $count): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <li class="flex items-baseline justify-between gap-3">
                                    <span class="text-ink-700"><?php echo e(__('analysis.variant_types.' . $type)); ?></span>
                                    <span class="ltr-data font-semibold"><?php echo e(number_format($count)); ?></span>
                                </li>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            <?php if(($comparison['frameshift_events'] ?? 0) > 0): ?>
                                <li class="flex items-baseline justify-between gap-3 border-t border-line pt-1">
                                    <span class="font-semibold text-alert-600"><?php echo e(__('analysis.compare.frameshift')); ?></span>
                                    <span class="ltr-data font-semibold text-alert-600"><?php echo e($comparison['frameshift_events']); ?></span>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </div>

                    <div>
                        <p class="eyebrow"><?php echo e(__('analysis.compare.effects_title')); ?></p>
                        <?php if(empty($effects)): ?>
                            <p class="mt-2 text-sm text-ink-400">—</p>
                        <?php else: ?>
                            <ul class="mt-2 space-y-1 text-sm">
                                <?php $__currentLoopData = $effects; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $effect => $count): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <li class="flex items-baseline justify-between gap-3">
                                        <span class="text-ink-700"><?php echo e(__('analysis.effects.' . $effect)); ?></span>
                                        <span class="ltr-data font-semibold"><?php echo e(number_format($count)); ?></span>
                                    </li>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="max-h-[26rem] overflow-auto">
                    <table class="data-table">
                        <thead class="sticky top-0">
                        <tr>
                            <th scope="col"><?php echo e(__('analysis.variant.type')); ?></th>
                            <th scope="col"><?php echo e(__('analysis.variant.position')); ?></th>
                            <th scope="col"><?php echo e(__('analysis.variant.codon')); ?></th>
                            <th scope="col"><?php echo e(__('analysis.variant.change')); ?></th>
                            <th scope="col"><?php echo e(__('analysis.variant.effect')); ?></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php $__currentLoopData = $variants; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $variant): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <?php $type = $variant['type'] ?? 'substitution'; ?>
                            <tr>
                                <td>
                                    <span class="chip <?php echo e(in_array($type, ['insertion', 'deletion'], true) ? 'chip-alert' : 'chip-muted'); ?>">
                                        <?php echo e(__('analysis.variant_types.' . $type)); ?>

                                    </span>
                                    <?php if($variant['frameshift'] ?? false): ?>
                                        <span class="ltr-data mt-1 block text-[0.625rem] font-semibold text-alert-600">
                                            frameshift
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <td class="ltr-data whitespace-nowrap"><?php echo e(number_format($variant['position'] ?? 0)); ?></td>
                                <td class="ltr-data whitespace-nowrap text-ink-500">#<?php echo e($variant['codon'] ?? '—'); ?></td>

                                <td class="ltr-data whitespace-nowrap">
                                    <?php if($type === 'substitution'): ?>
                                        <span style="color: var(--color-base-<?php echo e(strtolower($variant['reference_base'] ?? 'n')); ?>)"><?php echo e($variant['reference_base'] ?? ''); ?></span>
                                        <span class="text-ink-300">→</span>
                                        <span style="color: var(--color-base-<?php echo e(strtolower($variant['alternative_base'] ?? 'n')); ?>)"><?php echo e($variant['alternative_base'] ?? ''); ?></span>
                                        <?php if(!empty($variant['ref_codon'])): ?>
                                            <span class="mt-0.5 block text-[0.625rem] text-ink-400">
                                                <?php echo e($variant['ref_codon']); ?> → <?php echo e($variant['alt_codon']); ?>

                                            </span>
                                        <?php endif; ?>
                                    <?php elseif($type === 'insertion'): ?>
                                        +<?php echo e($variant['length'] ?? 0); ?> <?php echo e(__('analysis.units.bp')); ?>

                                        <span class="mt-0.5 block max-w-[18ch] truncate text-[0.625rem] text-ink-400"><?php echo e($variant['inserted'] ?? ''); ?></span>
                                    <?php elseif($type === 'deletion'): ?>
                                        −<?php echo e($variant['length'] ?? 0); ?> <?php echo e(__('analysis.units.bp')); ?>

                                        <span class="mt-0.5 block max-w-[18ch] truncate text-[0.625rem] text-ink-400"><?php echo e($variant['deleted'] ?? ''); ?></span>
                                    <?php else: ?>
                                        <?php echo e($variant['length'] ?? ''); ?> <?php echo e(__('analysis.units.bp')); ?>

                                    <?php endif; ?>
                                </td>

                                <td>
                                    <?php if($type === 'substitution'): ?>
                                        <?php $effect = $variant['effect'] ?? 'unknown'; ?>
                                        <span class="chip <?php echo e(match ($effect) {
                                            'synonymous' => 'chip-good',
                                            'nonsense', 'stop_lost' => 'chip-alert',
                                            'missense' => 'chip-signal',
                                            default => 'chip-muted',
                                        }); ?>">
                                            <?php echo e(__('analysis.effects.' . $effect)); ?>

                                        </span>
                                        <?php if(!empty($variant['ref_aa'])): ?>
                                            <span class="ltr-data mt-1 block text-[0.625rem] text-ink-400">
                                                <?php echo e($variant['ref_aa']); ?> → <?php echo e($variant['alt_aa']); ?>

                                            </span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-ink-300">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </tbody>
                    </table>
                </div>

                <?php if($comparison['variants_truncated'] ?? false): ?>
                    <p class="border-t border-line px-5 py-3 text-[0.6875rem] text-ink-400">
                        <?php echo e(__('analysis.compare.truncated', ['total' => number_format($comparison['total_variants'])])); ?>

                    </p>
                <?php endif; ?>
            <?php endif; ?>
        </section>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
<?php endif; ?>
<?php /**PATH /Users/macbookshop/Desktop/Projects/Developments/DNA-Web/DNA-Frontend/resources/views/partials/comparison.blade.php ENDPATH**/ ?>