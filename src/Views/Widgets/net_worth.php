<?php
// Net Worth Widget
$agg = new \App\Accounting\Aggregator();
$balances = $agg->getRangeBalances('2000-01-01', '2111-12-31');

$assets = 0;
$liabilities = 0;

$ctx = \App\Core\UserContext::get();
$settings = $ctx->getSettings();
$termAssets = $settings['term_assets'] ?? 'Assets';
$termLiabilities = $settings['term_liabilities'] ?? 'Liabilities';

foreach ($balances['balances'] as $account => $currs) {
    if (str_starts_with($account, 'Assets') || str_starts_with($account, $termAssets)) {
        foreach ($currs as $amount) $assets += $amount;
    } elseif (str_starts_with($account, 'Liabilities') || str_starts_with($account, $termLiabilities)) {
        foreach ($currs as $amount) $liabilities += $amount;
    }
}

$netWorth = $assets + $liabilities; // Liabilities are usually negative in ledger
$formattedNet = formatCurrency($netWorth, $settings);
?>
<div class="widget net-worth-widget">
    <h3><?php echo __('widget_net_worth'); ?></h3>
    <div class="net-worth-value <?php echo $netWorth >= 0 ? 'net-worth-positive' : 'net-worth-negative'; ?>">
        <?php echo $formattedNet; ?>
    </div>
    <div class="net-worth-details text-muted">
        <?php echo htmlspecialchars($termAssets); ?>: <?php echo formatCurrency($assets, $settings); ?><br>
        <?php echo htmlspecialchars($termLiabilities); ?>: <?php echo formatCurrency(abs($liabilities), $settings); ?>
    </div>
</div>
