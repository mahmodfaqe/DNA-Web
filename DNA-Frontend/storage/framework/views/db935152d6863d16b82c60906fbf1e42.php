<?php
    $summary = $analysis->summary();
    $variantCount = $analysis->variantCount();
?>

<div class="panel metric-strip overflow-hidden">
    <div class="metric">
        <p class="metric-value"><?php echo e(number_format($summary['total_genes'] ?? 0)); ?></p>
        <p class="metric-label"><?php echo e(__('analysis.metrics.records')); ?></p>
    </div>

    <div class="metric">
        <p class="metric-value"><?php echo e(number_format($summary['total_bases'] ?? 0)); ?></p>
        <p class="metric-label"><?php echo e(__('analysis.metrics.total_bases')); ?></p>
    </div>

    <div class="metric">
        <p class="metric-value"><?php echo e(number_format($summary['average_gc'] ?? 0, 1)); ?>%</p>
        <p class="metric-label"><?php echo e(__('analysis.metrics.avg_gc')); ?></p>
        <p class="metric-sub ltr-data">
            <?php echo e(number_format($summary['min_gc'] ?? 0, 1)); ?>–<?php echo e(number_format($summary['max_gc'] ?? 0, 1)); ?>%
        </p>
    </div>

    <div class="metric">
        <p class="metric-value"><?php echo e(number_format($summary['average_length'] ?? 0)); ?></p>
        <p class="metric-label"><?php echo e(__('analysis.metrics.avg_length')); ?> (<?php echo e(__('analysis.units.bp')); ?>)</p>
        <p class="metric-sub ltr-data">
            <?php echo e(number_format($summary['min_length'] ?? 0)); ?>–<?php echo e(number_format($summary['max_length'] ?? 0)); ?>

        </p>
    </div>

    <div class="metric">
        <p class="metric-value <?php echo e($variantCount > 0 ? 'text-signal-600' : ''); ?>">
            <?php echo e(number_format($variantCount)); ?>

        </p>
        <p class="metric-label"><?php echo e(__('analysis.metrics.variants')); ?></p>
        <?php if(($summary['unknown_bases'] ?? 0) > 0): ?>
            <p class="metric-sub">
                <?php echo e(__('analysis.metrics.unknown')); ?>: <?php echo e(number_format($summary['unknown_bases'])); ?>

            </p>
        <?php endif; ?>
    </div>
</div>
<?php /**PATH /Users/macbookshop/Desktop/Projects/Developments/DNA-Web/DNA-Frontend/resources/views/partials/metrics.blade.php ENDPATH**/ ?>