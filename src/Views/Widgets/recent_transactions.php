<?php
// Recent Transactions Widget
$ctx = \App\Core\UserContext::get();
$cache = new \App\Cache\CacheManager();
$settings = $ctx->getSettings();
$dataPath = $ctx->getDataPath();
$files = glob($dataPath . '/*.journal');
$names = array_map(fn($f) => basename($f, '.journal'), $files);
rsort($names);

$recentTxs = [];
foreach ($names as $name) {
    if (count($recentTxs) >= 5) break;
    $txs = $cache->getFileData($name);
    // Sort file transactions descending by date
    usort($txs, fn($a, $b) => strcmp($b['date'], $a['date']));
    $recentTxs = array_merge($recentTxs, array_slice($txs, 0, 5 - count($recentTxs)));
}

// Re-sort global recent list
usort($recentTxs, fn($a, $b) => strcmp($b['date'], $a['date']));
?>
<div class="widget recents-widget">
    <h3><?php echo __('auto_recently_executed'); ?></h3>
    <div class="tx-list-container">
        <?php if (empty($recentTxs)): ?>
            <p class="text-muted"><?php echo __('auto_no_recently_executed'); ?></p>
        <?php else: ?>
            <ul class="tx-list">
                <?php foreach ($recentTxs as $tx): ?>
                    <li class="tx-item">
                        <span class="payee"><?php echo htmlspecialchars($tx['payee']); ?></span>
                        <span class="date text-muted"><?php echo $tx['date']; ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
            <a href="/transactions" class="view-all"><?php echo __('tx_all_transactions'); ?> →</a>
        <?php endif; ?>
    </div>
</div>
