<?php
$isEdit = isset($isEdit) && $isEdit;
$tx = $transaction ?? null;
$formAction = $isEdit ? '/transactions/edit' : '/transactions/add';

$ctx = \App\Core\UserContext::get();
$dataPath = $ctx->getDataPath();
$settings = $ctx->getSettings();
$termAssets = $settings['term_assets'] ?? 'Assets';
$termExpenses = $settings['term_expenses'] ?? 'Expenses';
?>
<form method="POST" action="<?php echo $formAction; ?>">
    <?php if ($isEdit && $tx): ?>
        <input type="hidden" name="original_date" value="<?php echo htmlspecialchars($tx['date']); ?>">
        <input type="hidden" name="original_payee" value="<?php echo htmlspecialchars($tx['payee']); ?>">
    <?php endif; ?>
    
    <div class="row-group">
        <div class="row date-row">
            <input type="date" name="date" value="<?php echo $isEdit && $tx ? htmlspecialchars($tx['date']) : date('Y-m-d'); ?>" required>
        </div>
        <div class="row" style="flex: 3;">
            <div class="autocomplete-container">
                <input type="text" name="payee" id="payee-input" placeholder="<?= __('tx_payee'); ?>" value="<?php echo $isEdit && $tx ? htmlspecialchars($tx['payee']) : ''; ?>" required autocomplete="off">
            </div>
        </div>
    </div>
    
    <div class="row-group">
        <?php if ($settings['use_pending']): ?>
        <div class="row status-row">
            <input type="text" name="status" value="<?php echo $isEdit && $tx ? htmlspecialchars($tx['status']) : '*'; ?>" class="text-center" placeholder="Stat">
        </div>
        <?php endif; ?>
        <div class="row">
            <div class="autocomplete-container">
                <input type="text" name="description" id="memo-input" placeholder="<?= __('tx_memo'); ?>" value="<?php echo $isEdit && $tx ? htmlspecialchars($tx['description']) : ''; ?>" autocomplete="off">
            </div>
        </div>
    </div>
    
    <div class="postings" id="postings-container">
        <?php
        $sym = htmlspecialchars($settings['currency_symbol']);
        $pos = $settings['currency_position'];
        $spacing = $settings['currency_spacing'] ? '&nbsp;' : '';
        
        function renderAmountInput($name, $value = '') {
            echo '<div class="amount-input-group">';
            echo "<input type='text' name='$name' value='" . htmlspecialchars($value) . "' placeholder='0,00€' oninput='updateBalancingSuggestion()' onblur='formatInput(this)' inputmode='decimal' autocomplete='off'>";
            echo '<span class="sign-toggle" onclick="toggleSign(this)">-</span>';
            echo '</div>';
        }
        
        if ($isEdit && $tx && !empty($tx['postings'])):
            foreach ($tx['postings'] as $posting):
                $amountStr = $posting['amount'] !== '' ? number_format((float)$posting['amount'], 2, ',', '.') : '';
                ?>
                <div class="posting-row">
                    <div class="autocomplete-container">
                        <input type="text" name="accounts[]" placeholder="<?= __('tx_account'); ?> (e.g. <?php echo htmlspecialchars($termExpenses); ?>:Pizza)" value="<?php echo htmlspecialchars($posting['account']); ?>" oninput="checkNewRow(this); updateBalancingSuggestion()" autocomplete="off">
                    </div>
                    <?php renderAmountInput('amounts[]', $amountStr); ?>
                </div>
            <?php endforeach;
        else: ?>
        <div class="posting-row">
            <div class="autocomplete-container">
                <input type="text" name="accounts[]" placeholder="<?= __('tx_account'); ?> (e.g. <?php echo htmlspecialchars($termExpenses); ?>:Pizza)" oninput="checkNewRow(this); updateBalancingSuggestion()" autocomplete="off">
            </div>
            <?php renderAmountInput('amounts[]'); ?>
        </div>
        <div class="posting-row">
            <div class="autocomplete-container">
                <input type="text" name="accounts[]" placeholder="<?= __('tx_account'); ?> (e.g. <?php echo htmlspecialchars($termAssets); ?>:Cash)" oninput="checkNewRow(this); updateBalancingSuggestion()" autocomplete="off">
            </div>
            <?php renderAmountInput('amounts[]'); ?>
        </div>
        <?php endif; ?>
    </div>

    <div class="flex-row justify-start align-center form-actions gap-md border-top mt-lg pt-lg">
        <button type="submit" class="btn btn-primary"><?php echo $isEdit ? __('tx_btn_update') : __('tx_btn_save'); ?></button> 
        <a href="/transactions" class="btn btn-secondary"><?php echo __('tx_btn_cancel'); ?></a>
    </div>
</form>

<script>
    // These need to be initialized after the JS files are loaded
    const AUTOCOMPLETE_DATA = <?php echo json_encode($autoData); ?>;
    window.APP_SETTINGS = <?php echo json_encode($settings); ?>;
    
    window.addEventListener('DOMContentLoaded', () => {
        // Initialize Autocompletes
        setupAutocomplete(document.getElementById("payee-input"), (val) => {
            return AUTOCOMPLETE_DATA.payees
                .filter(p => p.toLowerCase().includes(val.toLowerCase()))
                .slice(0, 10);
        });

        setupAutocomplete(document.getElementById("memo-input"), (val) => {
            const payee = document.getElementById("payee-input").value;
            const correlated = (AUTOCOMPLETE_DATA.correlations[payee] || {}).memos || [];
            return correlated.filter(m => m.toLowerCase().includes(val.toLowerCase())).slice(0, 10);
        });

        // Helper for accounts
        window.initAccountAutocomplete = function(input) {
            setupAutocomplete(input, (val) => {
                const payee = document.getElementById("payee-input").value;
                const correlated = (AUTOCOMPLETE_DATA.correlations[payee] || {}).accounts || [];
                const all = AUTOCOMPLETE_DATA.accounts;
                const combined = Array.from(new Set([...correlated, ...all]));
                return combined.filter(a => a.toLowerCase().includes(val.toLowerCase())).slice(0, 15);
            });
        };

        document.querySelectorAll('input[name="accounts[]"]').forEach(initAccountAutocomplete);
        
        // Listener for payee changes
        document.getElementById("payee-input").addEventListener('change', () => {
             // Future: auto-pick memo or first account if correlation is 100%
        });
    });
</script>
