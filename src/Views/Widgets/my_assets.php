<?php
// My Assets Widget
use App\Accounting\Aggregator;
use App\Accounting\AccountTreeBuilder;

$agg = new Aggregator();
$ctx = \App\Core\UserContext::get();
$settings = $ctx->getSettings();

// Get all balances (all time)
$balances = $agg->getRangeBalances('2000-01-01', '2111-12-31');

// Calculate asset balances
$assetAccounts = [];
$totalAssets = 0.0;

$termAssets = $settings['term_assets'] ?? 'Assets';

foreach ($balances['balances'] as $account => $currs) {
    if (str_starts_with($account, $termAssets)) {
        $amount = array_sum($currs);
        $assetAccounts[$account] = $amount;
        $totalAssets += $amount;
    }
}

// Build asset tree
$assetTree = AccountTreeBuilder::buildTree($assetAccounts, $termAssets);
$topLevelAssets = AccountTreeBuilder::getTopLevelAccounts($assetTree);

// Calculate percentages for chart
$chartData = [];
$chartColors = ['--chart-1', '--chart-2', '--chart-3', '--chart-4', '--chart-5'];
$colorIndex = 0;

foreach ($topLevelAssets as $node) {
    $percentage = $totalAssets > 0 ? ($node->balance / $totalAssets) * 100 : 0;
    $chartData[] = [
        'name' => $node->name,
        'balance' => $node->balance,
        'percentage' => $percentage,
        'color' => $chartColors[$colorIndex % count($chartColors)]
    ];
    $colorIndex++;
}

// Get all asset nodes for detailed tree
$allAssetNodes = AccountTreeBuilder::flattenTree($assetTree);
?>

<div class="widget my-assets-widget">
    
    <h3><?php echo __('widget_my_assets'); ?></h3>
    

    <!--<div class="net-worth-total">
        <span class="label text-muted">Net Worth</span>
        <span class="amount font-code <?php echo $totalAssets >= 0 ? 'amount-positive' : 'amount-negative'; ?>">
            <?php echo formatCurrency($totalAssets, $settings); ?>
        </span>
    </div> -->
    
    <?php if (!empty($chartData)): ?>
    <div class="asset-chart-container">
        <div class="asset-bar-chart">
            <?php foreach ($chartData as $data): ?>
                <?php if ($data['percentage'] > 0): ?>
                <div class="asset-segment" 
                     style="width: <?php echo $data['percentage']; ?>%; background-color: var(<?php echo $data['color']; ?>);"
                     title="<?php echo htmlspecialchars($data['name']); ?>: <?php echo number_format($data['percentage'], 1); ?>%">
                </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
        
        <div class="asset-legend">
            <?php foreach ($chartData as $data): ?>
                <?php if ($data['percentage'] > 0): ?>
                <div class="asset-legend-item">
                    <span class="legend-color" style="background-color: var(<?php echo $data['color']; ?>);"></span>
                    <span class="legend-label"><?php echo htmlspecialchars($data['name']); ?></span>
                    <span class="legend-percentage text-muted"><?php echo number_format($data['percentage'], 1); ?>%</span>
                    <span class="legend-amount font-code">
                        <?php echo formatCurrency($data['balance'], $settings); ?>
                    </span>
                </div>
                <?php endif; ?>
            <?php endforeach; ?>
            <div class="asset-legend-item font-bold border-top">
                    <span class="legend-color" style="background-color: #fff;"></span>
                    <span class="legend-label"><?php echo __('widget_balance'); ?></span>
                    <span class="legend-percentage text-muted">100%</span>
                    <span class="legend-amount font-code">
                        <?php echo formatCurrency($totalAssets, $settings); ?>
                    </span>
                </div>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if (!empty($allAssetNodes)): ?>
    <div class="asset-tree-container">
        <h4><?php echo __('widget_summary'); ?></h4>
        <div class="account-tree">
            <?php 
            // Create a map of top-level names to colors
            $colorMap = [];
            foreach ($chartData as $data) {
                $colorMap[$data['name']] = $data['color'];
            }
            
            foreach ($allAssetNodes as $node): 
                // Get color for top-level account
                $topLevelName = explode(':', $node->fullPath)[1] ?? '';
                $nodeColor = $colorMap[$topLevelName] ?? null;

                if( abs($node->balance) > 0.001):
            ?>
                <div class="account-node level-<?php echo $node->level; ?> <?php echo $node->level === 0 ? 'top-level' : ''; ?>">
                    <?php if ($nodeColor && $node->level === 0): ?>
                        <span class="account-color-dot" style="background-color: var(<?php echo $nodeColor; ?>);"></span>
                    <?php endif; ?>
                    <span class="account-name font-code"><?php echo htmlspecialchars($node->name); ?></span>
                    <span class="account-balance font-code <?php echo $node->balance >= 0 ? 'amount-positive' : 'amount-negative'; ?>">
                        <?php echo formatCurrency($node->balance, $settings); ?>
                    </span>
                </div>
            <?php 
            endif;
            endforeach; 
            ?>
        </div>
    </div>
    <?php endif; ?>
</div>
