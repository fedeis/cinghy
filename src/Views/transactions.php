<?php
$ctx = \App\Core\UserContext::get();
$settings = $ctx->getSettings();
$accentColor = $settings['accent_color'] ?? '#0774e9';
$cpDate = $checkpointDate ?? null; // passed from the route
?>
<div class="flex-row align-center justify-between mb-lg">
    <h1><?php echo __('tx_title'); ?></h1>
    <div class="flex-row gap-xs">
        <a href="/recurring" class="btn btn-secondary" title="<?php echo __('nav_automated'); ?>" aria-label="<?php echo __('nav_automated'); ?>" style="display: inline-flex; align-items: center; justify-content: center; padding: 0.5em 0.6em;">
            <svg viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
        </a>
        <a href="/transactions/add" class="btn btn-primary" title="<?php echo __('tx_add_title'); ?>" aria-label="<?php echo __('tx_add_title'); ?>" style="display: inline-flex; align-items: center; justify-content: center; padding: 0.5em 0.6em;">
            <svg viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
        </a>
    </div>
</div>

<?php if (!empty($allTransactions)): ?>
<div class="mb-md">
    <input type="text" id="tx-search" placeholder="<?php echo __('tx_search_placeholder'); ?>" autocomplete="off" style="width: 100%; padding: 10px 14px; border: 1px solid var(--border-color); border-radius: var(--radius-md); background: var(--card-bg); color: var(--text-color); font-size: 16px; transition: border-color var(--transition-fast);" onfocus="this.style.borderColor='var(--accent-color)'" onblur="this.style.borderColor='var(--border-color)'">
</div>
<?php endif; ?>

<div class="flex-col" id="transactions-list">
    <?php foreach ($allTransactions as $tx): ?>
        <?php
        $accountsList = [];
        if (isset($tx['postings']) && !empty($tx['postings'])) {
            foreach ($tx['postings'] as $p) {
                $accountsList[] = strtolower($p['account']);
            }
        }
        $accountsAttr = htmlspecialchars(implode(' ', $accountsList));
        $payeeAttr = htmlspecialchars(strtolower($tx['payee'] ?? ''));
        $descAttr = htmlspecialchars(strtolower($tx['description'] ?? ''));
        ?>
        <a href="/transactions/edit?date=<?php echo urlencode($tx['date']); ?>&payee=<?php echo urlencode($tx['payee'] ?? ''); ?>" class="transaction-card-link" data-payee="<?php echo $payeeAttr; ?>" data-description="<?php echo $descAttr; ?>" data-accounts="<?php echo $accountsAttr; ?>" style="text-decoration: none; color: inherit;">
        <?php $isVerified = $cpDate !== null && $tx['date'] <= $cpDate; ?>
        <div class="card p-md" style="position: relative;">
            <?php if ($isVerified): ?>
            <span class="tx-verified-badge" title="<?php echo __('checkpoint_verified_label'); ?>" style="
                position: absolute; top: 2px; right: 2px;
                width: 18px; height: 18px; border-radius: 50%;
                display: flex; align-items: center; justify-content: center;
                flex-shrink: 0;
            ">
                <svg viewBox="0 0 24 24" width="12" height="12" stroke="<?php echo htmlspecialchars($accentColor); ?>" stroke-width="4" fill="none" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
            </span>
            <?php endif; ?>
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
            <?php echo __('tx_no_transactions'); ?>
        </div>
    <?php endif; ?>
</div>

<?php if (!empty($allTransactions)): ?>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.getElementById('tx-search');
    const transactionsContainer = document.getElementById('transactions-list');
    const cards = transactionsContainer.querySelectorAll('.transaction-card-link');
    
    // Create a "No results found" container if it doesn't exist yet
    const noResultsDiv = document.createElement('div');
    noResultsDiv.className = 'card p-lg text-center text-muted';
    noResultsDiv.id = 'tx-no-results';
    noResultsDiv.style.display = 'none';
    noResultsDiv.textContent = '<?php echo htmlspecialchars(__('tx_no_transactions')); ?>';
    transactionsContainer.appendChild(noResultsDiv);
    
    const filterList = (query) => {
        let visibleCount = 0;
        cards.forEach(card => {
            const payee = card.getAttribute('data-payee') || '';
            const description = card.getAttribute('data-description') || '';
            const accounts = card.getAttribute('data-accounts') || '';
            
            const matches = payee.includes(query) || description.includes(query) || accounts.includes(query);
            
            if (matches) {
                card.style.display = '';
                visibleCount++;
            } else {
                card.style.display = 'none';
            }
        });
        
        if (visibleCount === 0 && query !== '') {
            noResultsDiv.style.display = '';
        } else {
            noResultsDiv.style.display = 'none';
        }
    };
    
    if (searchInput) {
        searchInput.addEventListener('input', () => {
            const query = searchInput.value.toLowerCase().trim();
            filterList(query);
            sessionStorage.setItem('transactions_search_query', searchInput.value);
        });
    }
    
    // Save scroll position on click of any transaction card
    cards.forEach(card => {
        card.addEventListener('click', () => {
            sessionStorage.setItem('transactions_scroll_pos', window.scrollY);
        });
    });
    
    // Clear scroll and search when clicking any main navigation menu link
    document.querySelectorAll('#menu-island a').forEach(link => {
        link.addEventListener('click', () => {
            sessionStorage.removeItem('transactions_scroll_pos');
            sessionStorage.removeItem('transactions_search_query');
        });
    });
    
    // Restore search query if present
    const savedQuery = sessionStorage.getItem('transactions_search_query');
    if (savedQuery && searchInput) {
        searchInput.value = savedQuery;
        filterList(savedQuery.toLowerCase().trim());
    }
    
    // Restore scroll position if present
    const savedScrollPos = sessionStorage.getItem('transactions_scroll_pos');
    if (savedScrollPos !== null) {
        window.scrollTo(0, parseInt(savedScrollPos, 10));
        // Clear saved scroll position so subsequent refreshes or menu clicks start fresh
        sessionStorage.removeItem('transactions_scroll_pos');
        sessionStorage.removeItem('transactions_search_query');
    }
});
</script>
<?php endif; ?>
