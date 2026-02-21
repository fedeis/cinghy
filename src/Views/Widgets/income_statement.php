<?php
// Income Statement Widget
use App\Accounting\Aggregator;
use App\Accounting\AccountTreeBuilder;

$agg = new Aggregator();

// Get selected month from query parameter or default to current month
$selectedMonth = $_GET['income_month'] ?? date('Y-m');
list($year, $month) = explode('-', $selectedMonth);

// Get all available months from journal files
$ctx = \App\Core\UserContext::get();
$dataPath = $ctx->getDataPath();
$journalFiles = glob($dataPath . '/*.journal');
$availableMonths = [];

foreach ($journalFiles as $file) {
    $fileYear = basename($file, '.journal');
    if (is_numeric($fileYear)) {
        for ($m = 1; $m <= 12; $m++) {
            $monthKey = sprintf('%s-%02d', $fileYear, $m);
            if ($monthKey <= date('Y-m')) {
                $availableMonths[] = $monthKey;
            }
        }
    }
}
rsort($availableMonths);

// Get transactions for selected month
$startDate = sprintf('%s-%02d-01', $year, $month);
$endDate = date('Y-m-t', strtotime($startDate));

$balances = $agg->getRangeBalances($startDate, $endDate);

// Calculate income and expenses
$totalIncome = 0.0;
$totalExpenses = 0.0;
$expenseAccounts = [];
$incomeAccounts = [];

$settings = $ctx->getSettings();
$termIncome = $settings['term_income'] ?? 'Income';
$termExpenses = $settings['term_expenses'] ?? 'Expenses';

foreach ($balances['balances'] as $account => $currs) {
    $amount = array_sum($currs);
    
    // Check both standard 'Revenues' and user's custom term for backward compatibility
    if (str_starts_with($account, 'Revenues') || str_starts_with($account, $termIncome)) {
        $totalIncome += abs($amount); // Revenues are negative, so we invert
        $incomeAccounts[$account] = abs($amount);
    } elseif (str_starts_with($account, $termExpenses)) {
        $totalExpenses += $amount;
        $expenseAccounts[$account] = $amount;
    }
}

// Build expense tree
$expenseTree = AccountTreeBuilder::buildTree($expenseAccounts, $termExpenses);
$expenseNodes = AccountTreeBuilder::flattenTree($expenseTree, 1); // Default: show only top level

// Month names using Translations
$monthNames = [
    '01' => __('month_1'), '02' => __('month_2'), '03' => __('month_3'), '04' => __('month_4'),
    '05' => __('month_5'), '06' => __('month_6'), '07' => __('month_7'), '08' => __('month_8'),
    '09' => __('month_9'), '10' => __('month_10'), '11' => __('month_11'), '12' => __('month_12')
];

$expanded = isset($_GET['expand_expenses']) && $_GET['expand_expenses'] === '1';
if ($expanded) {
    $expenseNodes = AccountTreeBuilder::flattenTree($expenseTree);
}
?>

<div class="widget income-statement-widget">
    <div class="widget-header flex-row align-center justify-between">
        <h3><?php echo __('widget_income_statement'); ?></h3>
        <form method="GET" action="/" class="month-selector">
            <select name="income_month" onchange="this.form.submit()" class="compact-select">
                <?php foreach ($availableMonths as $monthKey): 
                    list($y, $m) = explode('-', $monthKey);
                    $label = $monthNames[$m] . ' ' . $y;
                ?>
                    <option value="<?php echo $monthKey; ?>" <?php echo $monthKey === $selectedMonth ? 'selected' : ''; ?>>
                        <?php echo $label; ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <input type="hidden" name="csrf_token" value="<?= \App\Core\Router::csrfToken() ?>">
        </form>
    </div>
    
    <div class="income-statement-summary">
        <div class="income-box flex-row justify-between align-center">
            <div class="label text-muted"><?php echo htmlspecialchars($termIncome); ?></div>
            <div class="amount income-positive font-code">
                <?php echo formatCurrency($totalIncome, $settings); ?>
            </div>
        </div>
        <div class="expense-box flex-row justify-between align-center">
            <div class="label text-muted"><?php echo htmlspecialchars($termExpenses); ?></div>
            <div class="amount expense-negative font-code">
                <?php echo formatCurrency($totalExpenses, $settings); ?>
            </div>
        </div>
    </div>
    
    <div class="net-result <?php echo ($totalIncome - $totalExpenses) >= 0 ? 'positive' : 'negative'; ?>">
        <span class="label"><?php echo __('widget_balance'); ?>:</span>
        <span class="amount font-code">
            <?php echo formatCurrency($totalIncome - $totalExpenses, $settings); ?>
        </span>
    </div>
    
    <?php if (!empty($expenseNodes)): ?>
    <div class="expense-breakdown border-top">
        <div class="flex-row align-center justify-between mb-sm">
            <h4><?php echo __('widget_summary'); ?></h4>
            <a href="/?expand_expenses=<?php echo $expanded ? '0' : '1'; ?>&income_month=<?php echo $selectedMonth; ?>" class="btn-text">
                <?php echo $expanded ? '▼ ' . __('widget_collapse') : '▶ ' . __('widget_expand'); ?>
            </a>
        </div>
        
        <div class="account-tree">
            <?php foreach ($expenseNodes as $node): ?>
                <?php if ($node->balance > 0): ?>
                    <div class="account-node level-<?php echo $node->level; ?>">
                    <span class="account-name font-code"><?php echo htmlspecialchars($node->name); ?></span>
                    <span class="account-balance font-code">
                        <?php echo formatCurrency($node->balance, $settings); ?>
                    </span>
                </div>
                <?php endif; ?>
                
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>
