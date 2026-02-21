<?php
$ctx = \App\Core\UserContext::get();
$settings = $ctx->getSettings();
?>
<div class="flex-row align-center justify-between mb-lg">
    <h1><?php echo __('tx_title'); ?></h1>
    <div>
        <a href="/recurring" class="btn btn-secondary"><?php echo __('nav_automated'); ?></a>
        <a href="/transactions/add" class="btn btn-primary">+ <?php echo __('tx_add_title'); ?></a>
    </div>
</div>

<div class="flex-col">
    <?php foreach ($allTransactions as $tx): ?>
        <a href="/transactions/edit?date=<?php echo urlencode($tx['date']); ?>&payee=<?php echo urlencode($tx['payee'] ?? ''); ?>" style="text-decoration: none; color: inherit;">
        <div class="card p-md">
            <div class="flex-row align-center justify-between mb-sm">
                <?php if (!empty($tx['payee'])): ?>
                    <div>
                        <span class="font-medium"><?php echo htmlspecialchars($tx['payee']); ?></span>
                        <?php if (!empty($tx['description'])): ?>
                        <span class="text-muted text-md mb-sm">
                            <?php echo htmlspecialchars($tx['description']); ?>
                        </span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                <div class="flex-row align-center gap-sm text-sm">
                    <strong><?php echo htmlspecialchars($tx['date']); ?></strong>
                    <?php if (!empty($tx['status'])): ?>
                        <span class="badge"><?php echo htmlspecialchars($tx['status']); ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <?php if (isset($tx['postings']) && !empty($tx['postings'])): ?>
                <div class="flex-col gap-xs pt-sm pt-sm border-top">
                    <?php foreach ($tx['postings'] as $p): ?>
                        <div class="flex-row justify-between align-center">
                            <span class="text-sm text-muted"><?php echo htmlspecialchars($p['account']); ?></span>
                            <span class="text-sm <?php echo $p['amount'] < 0 ? 'amount-negative' : 'amount-positive'; ?>">
                                <?php echo formatCurrency($p['amount'], $settings); ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        </a>
    <?php endforeach; ?>
    
    <?php if (empty($allTransactions)): ?>
        <div class="card p-lg text-center text-muted">
            No transactions found.
        </div>
    <?php endif; ?>
</div>
