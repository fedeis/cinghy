<?php 
if (isset($has_discrepancy) && $has_discrepancy): ?>
    <div class="card bg-warning mb-md" style="border-left: 4px solid var(--danger);">
        <strong><?php echo __('error_heuristic_mismatch'); ?></strong>
    </div>
<?php endif; 

$renderer = new \App\Dashboard\WidgetRenderer($settings ?? []);
$renderer->renderAll(); ?>
