<?php
    $genes = $analysis->genes();
    $limits = $analysis->payload['limits'] ?? [];
    $interactive = $interactive ?? true;
?>

<section class="panel overflow-hidden">
    <div class="panel-head">
        <div>
            <h2 class="panel-title"><?php echo e(__('analysis.table.title')); ?></h2>
            <p class="panel-note"><?php echo e(__('analysis.table.subtitle')); ?></p>
        </div>
        <span class="chip"><?php echo e(count($genes)); ?> <?php echo e(__('analysis.result.records')); ?></span>
    </div>

    <div class="overflow-x-auto">
        <table class="data-table">
            <thead>
            <tr>
                <th scope="col"><?php echo e(__('analysis.table.id')); ?></th>
                <th scope="col"><?php echo e(__('analysis.table.length')); ?></th>
                <th scope="col"><?php echo e(__('analysis.table.gc')); ?></th>
                <th scope="col"><?php echo e(__('analysis.table.tm')); ?></th>
                <th scope="col"><?php echo e(__('analysis.table.protein')); ?></th>
                <th scope="col" class="ltr-data"><?php echo e(__('analysis.table.composition')); ?></th>
                <th scope="col"><?php echo e(__('analysis.table.quality')); ?></th>
            </tr>
            </thead>
            <tbody>
            <?php $__currentLoopData = $genes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $gene): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <?php
                    $composition = $gene['base_composition'] ?? [];
                    $tm = $gene['melting_temp'] ?? [];
                    $orf = $gene['orfs']['longest'] ?? null;
                    $gc = (float) ($gene['gc_content'] ?? 0);
                ?>
                <tr>
                    <td>
                        <span class="ltr-data block font-bold text-ink-900"><?php echo e($gene['id']); ?></span>
                        <?php if(!empty($gene['description']) && $gene['description'] !== $gene['id']): ?>
                            <span class="ltr-data mt-0.5 block max-w-[22ch] truncate text-[0.6875rem] text-ink-400"
                                  title="<?php echo e($gene['description']); ?>"><?php echo e($gene['description']); ?></span>
                        <?php endif; ?>
                    </td>

                    <td class="whitespace-nowrap font-semibold">
                        <span class="ltr-data"><?php echo e(number_format($gene['length'] ?? 0)); ?></span>
                        <span class="text-ink-400"><?php echo e(__('analysis.units.bp')); ?></span>
                    </td>

                    <td>
                        <div class="flex items-center gap-2">
                            <span class="track h-1.5 w-14 shrink-0 overflow-hidden rounded-full bg-line">
                                <span class="block h-full rounded-full bg-brand-500"
                                      style="width: <?php echo e(min(100, max(0, $gc))); ?>%"></span>
                            </span>
                            <span class="ltr-data font-semibold"><?php echo e(number_format($gc, 1)); ?>%</span>
                        </div>
                    </td>

                    <td class="whitespace-nowrap">
                        <?php if(($tm['value'] ?? null) !== null): ?>
                            <span class="ltr-data font-semibold"><?php echo e($tm['value']); ?> <?php echo e(__('analysis.units.celsius')); ?></span>
                            
                            <span class="mt-0.5 block text-[0.625rem] <?php echo e(($tm['reliable'] ?? false) ? 'text-ink-400' : 'text-signal-600'); ?>">
                                <?php echo e(__('analysis.tm_methods.' . ($tm['method'] ?? 'none'))); ?>

                                <?php if (! ($tm['reliable'] ?? false)): ?>
                                    · <?php echo e(__('analysis.tm.estimate')); ?>

                                <?php endif; ?>
                            </span>
                        <?php else: ?>
                            <span class="text-ink-300">—</span>
                        <?php endif; ?>
                    </td>

                    <td class="whitespace-nowrap">
                        <?php if($orf): ?>
                            <?php if($interactive): ?>
                                <button type="button"
                                        class="btn btn-quiet btn-sm"
                                        data-protein-trigger
                                        data-record-id="<?php echo e($gene['id']); ?>"
                                        data-protein="<?php echo e($gene['protein_sequence'] ?? ''); ?>">
                                    <span class="ltr-data"><?php echo e($orf['length_aa']); ?> <?php echo e(__('analysis.units.aa')); ?></span>
                                </button>
                            <?php else: ?>
                                <span class="ltr-data font-semibold"><?php echo e($orf['length_aa']); ?> <?php echo e(__('analysis.units.aa')); ?></span>
                            <?php endif; ?>
                            <span class="ltr-data mt-0.5 block text-[0.625rem] text-ink-400">
                                <?php echo e($orf['strand']); ?><?php echo e($orf['frame']); ?> · <?php echo e(number_format($orf['start'])); ?>–<?php echo e(number_format($orf['end'])); ?>

                            </span>
                        <?php else: ?>
                            <span class="text-ink-300">—</span>
                        <?php endif; ?>
                    </td>

                    <td class="ltr-data whitespace-nowrap text-ink-700">
                        <?php echo e($composition['A'] ?? 0); ?>/<?php echo e($composition['T'] ?? 0); ?>/<?php echo e($composition['C'] ?? 0); ?>/<?php echo e($composition['G'] ?? 0); ?>/<?php echo e($composition['N'] ?? 0); ?>

                    </td>

                    <td>
                        <?php if($gene['quality']['has_ambiguity'] ?? false): ?>
                            <span class="chip chip-signal">
                                <?php echo e(__('analysis.quality.unknown_fraction', ['percent' => $gene['quality']['unknown_fraction'] ?? 0])); ?>

                            </span>
                            <?php if(!empty($gene['ambiguity_codes'])): ?>
                                <span class="ltr-data mt-1 block text-[0.625rem] text-ink-400">
                                    <?php echo e(implode(' ', $gene['ambiguity_codes'])); ?>

                                </span>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="chip chip-good"><?php echo e(__('analysis.quality.clean')); ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </tbody>
        </table>
    </div>

    <?php if(!empty($limits['tm_nn_max_bp'])): ?>
        <p class="border-t border-line px-5 py-3 text-[0.6875rem] text-ink-400">
            <?php echo e(__('analysis.tm.estimate_note', ['length' => $limits['tm_nn_max_bp']])); ?>

        </p>
    <?php endif; ?>
</section>
<?php /**PATH /Users/macbookshop/Desktop/Projects/Developments/DNA-Web/DNA-Frontend/resources/views/partials/records-table.blade.php ENDPATH**/ ?>