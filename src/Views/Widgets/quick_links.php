<?php
// Quick Links Widget
$ctx = \App\Core\UserContext::get();
$settings = $ctx->getSettings();
$accentColor = $settings['accent_color'] ?? '#0774e9';
?>
<a href="/transactions/add" class="btn btn-primary">+ <?php echo __('dashboard_add_transaction'); ?></a>

<button
    type="button"
    class="btn btn-secondary"
    id="checkpoint-btn"
    onclick="document.getElementById('checkpoint-modal').style.display='flex'"
    style="margin-top: 8px; display: flex; align-items: center; gap: 6px;"
>
    <svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2.5" fill="none" stroke-linecap="round" stroke-linejoin="round">
        <polyline points="20 6 9 17 4 12"></polyline>
    </svg>
    <?php echo __('checkpoint_set_btn'); ?>
</button>

<?php if ($checkpointDate ?? false): ?>
<p class="text-muted" style="font-size: 0.78rem; margin-top: 6px;">
    <?php echo __('checkpoint_last_label'); ?>: <strong><?php echo htmlspecialchars($checkpointDate); ?></strong>
</p>
<?php endif; ?>

<!-- Confirmation Modal -->
<div id="checkpoint-modal"
     role="dialog" aria-modal="true" aria-labelledby="checkpoint-modal-title"
     style="display:none; position:fixed; inset:0; z-index:9999;
            background:rgba(0,0,0,0.45); backdrop-filter:blur(4px);
            -webkit-backdrop-filter:blur(4px);
            align-items:center; justify-content:center;">
    <div style="background:var(--card-bg); border:1px solid var(--border-color);
                border-radius:var(--radius-lg); padding:28px 28px 24px;
                max-width:380px; width:90%; box-shadow:0 20px 60px rgba(0,0,0,0.25);
                animation: modal-in .15s ease;">
        <div style="display:flex; align-items:center; gap:10px; margin-bottom:12px;">
            <span style="width:34px; height:34px; border-radius:50%;
                         background:<?php echo htmlspecialchars($accentColor); ?>;
                         display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                <svg viewBox="0 0 24 24" width="18" height="18" stroke="#fff" stroke-width="2.5" fill="none" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="20 6 9 17 4 12"></polyline>
                </svg>
            </span>
            <h3 id="checkpoint-modal-title" style="margin:0; font-size:1rem;"><?php echo __('checkpoint_modal_title'); ?></h3>
        </div>
        <p style="margin:0 0 20px; color:var(--text-muted); font-size:0.9rem; line-height:1.5;">
            <?php echo __('checkpoint_modal_body'); ?> <strong><?php echo date('Y-m-d'); ?></strong>.
        </p>
        <div style="display:flex; gap:10px; justify-content:flex-end;">
            <button type="button" class="btn btn-secondary"
                    onclick="document.getElementById('checkpoint-modal').style.display='none'">
                <?php echo __('checkpoint_modal_cancel'); ?>
            </button>
            <form method="POST" action="/checkpoint/set" style="margin:0;">
                <input type="hidden" name="csrf_token" value="<?= \App\Core\Router::csrfToken() ?>">
                <button type="submit" class="btn btn-primary"
                        style="display:flex; align-items:center; gap:6px;">
                    <svg viewBox="0 0 24 24" width="15" height="15" stroke="currentColor" stroke-width="2.5" fill="none" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="20 6 9 17 4 12"></polyline>
                    </svg>
                    <?php echo __('checkpoint_modal_confirm'); ?>
                </button>
            </form>
        </div>
    </div>
</div>

<script>
// Close modal on backdrop click
document.getElementById('checkpoint-modal').addEventListener('click', function(e) {
    if (e.target === this) this.style.display = 'none';
});
// Close modal on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.getElementById('checkpoint-modal').style.display = 'none';
    }
});
</script>
<style>
@keyframes modal-in {
    from { opacity: 0; transform: scale(.96) translateY(6px); }
    to   { opacity: 1; transform: scale(1) translateY(0); }
}
</style>
