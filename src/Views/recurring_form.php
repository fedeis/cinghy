<?php
$isEdit = isset($isEdit) && $isEdit;
$rec = $recurring ?? null;
$formAction = $isEdit ? '/recurring/edit' : '/recurring/add';
?>
<header class="page-header mb-lg">
    <h1><?php echo $isEdit ? __('tx_edit_title') : __('auto_create_new'); ?></h1>
</header>

<form method="POST" action="<?php echo $formAction; ?>" class="card">
    <?php if ($isEdit && $rec): ?>
        <input type="hidden" name="id" value="<?php echo htmlspecialchars($rec['id']); ?>">
    <?php endif; ?>
    
    <div class="row-group mb-md">
        <div class="row" style="flex: 1;">
            <label class="text-sm text-muted block mb-xs">Next Run Date</label>
            <input type="date" name="next_run_date" class="form-control" value="<?php echo $isEdit && $rec ? htmlspecialchars($rec['next_run_date']) : date('Y-m-d'); ?>" required style="height: 48px; border-radius: 8px;">
        </div>
        <div class="row" style="flex: 1;">
            <label class="text-sm text-muted block mb-xs">Frequency</label>
            <select name="frequency" class="form-control" required style="height: 48px; border-radius: 8px; width: 100%;">
                <option value="daily" <?php echo ($isEdit && $rec && $rec['frequency'] === 'daily') ? 'selected' : ''; ?>>Daily</option>
                <option value="weekly" <?php echo ($isEdit && $rec && $rec['frequency'] === 'weekly') ? 'selected' : ''; ?>>Weekly</option>
                <option value="biweekly" <?php echo ($isEdit && $rec && $rec['frequency'] === 'biweekly') ? 'selected' : ''; ?>>Bi-weekly</option>
                <option value="monthly" <?php echo (!$isEdit || ($rec && $rec['frequency'] === 'monthly')) ? 'selected' : ''; ?>>Monthly</option>
                <option value="yearly" <?php echo ($isEdit && $rec && $rec['frequency'] === 'yearly') ? 'selected' : ''; ?>>Yearly</option>
            </select>
        </div>
        <div class="row" style="flex: 1;">
            <label class="text-sm text-muted block mb-xs">Interval</label>
            <input type="number" name="interval" class="form-control" value="<?php echo $isEdit && $rec ? (int)($rec['interval'] ?? 1) : 1; ?>" min="1" required style="height: 48px; border-radius: 8px;">
        </div>
    </div>
    
    <div class="row-group mb-md">
        <?php if ($settings['use_pending']): ?>
        <div class="row status-row" style="flex: 0 0 60px;">
            <label class="text-sm text-muted block mb-xs">Status</label>
            <input type="text" name="status" class="form-control text-center" value="<?php echo $isEdit && $rec ? htmlspecialchars($rec['status']) : '*'; ?>" placeholder="*">
        </div>
        <?php endif; ?>
        <div class="row" style="flex: 2;">
            <label class="text-sm text-muted block mb-xs">Payee</label>
            <div class="autocomplete-container">
                <input type="text" name="payee" id="payee-input" class="form-control" placeholder="Payee" value="<?php echo $isEdit && $rec ? htmlspecialchars($rec['payee']) : ''; ?>" required autocomplete="off">
            </div>
        </div>
        <div class="row" style="flex: 3;">
            <label class="text-sm text-muted block mb-xs flex-row justify-between">
                <span>Description</span>
                <span style="font-size: 0.75rem;">Try: {{month_name}}, {{month_name_it}}, {{year}}</span>
            </label>
            <div class="autocomplete-container">
                <input type="text" name="description" id="memo-input" class="form-control" placeholder="e.g. Netflix Subscription for {{month_name}}" value="<?php echo $isEdit && $rec ? htmlspecialchars($rec['description']) : ''; ?>" autocomplete="off">
            </div>
        </div>
    </div>
    
    <hr style="border: 0; border-top: 1px solid var(--border-color); margin: 1.5rem 0;">
    <h3 class="mb-md">Template Postings</h3>
    
    <div class="postings" id="postings-container">
        <?php
        function renderAmountInput($name, $value = '') {
            echo '<div class="amount-input-group">';
            echo "<input type='text' name='$name' value='" . htmlspecialchars($value) . "' placeholder='0,00' oninput='updateBalancingSuggestion()' onblur='formatInput(this)' inputmode='decimal' autocomplete='off'>";
            echo '<span class="sign-toggle" onclick="toggleSign(this)">-</span>';
            echo '</div>';
        }
        
        if ($isEdit && $rec && !empty($rec['postings'])):
            foreach ($rec['postings'] as $posting):
                $amountStr = $posting['amount'] !== '' ? number_format((float)$posting['amount'], 2, ',', '.') : '';
                ?>
                <div class="posting-row">
                    <div class="autocomplete-container">
                        <input type="text" name="accounts[]" placeholder="Account (e.g. Expenses:Food)" value="<?php echo htmlspecialchars($posting['account']); ?>" oninput="checkNewRow(this); updateBalancingSuggestion()" autocomplete="off">
                    </div>
                    <?php renderAmountInput('amounts[]', $amountStr); ?>
                </div>
            <?php endforeach;
        else: ?>
        <div class="posting-row">
            <div class="autocomplete-container">
                <input type="text" name="accounts[]" placeholder="Account (e.g. Expenses:Food)" oninput="checkNewRow(this); updateBalancingSuggestion()" autocomplete="off">
            </div>
            <?php renderAmountInput('amounts[]'); ?>
        </div>
        <div class="posting-row">
            <div class="autocomplete-container">
                <input type="text" name="accounts[]" placeholder="Account (e.g. Assets:Cash)" oninput="checkNewRow(this); updateBalancingSuggestion()" autocomplete="off">
            </div>
            <?php renderAmountInput('amounts[]'); ?>
        </div>
        <?php endif; ?>
    </div>

    <div class="flex-row justify-start align-center form-actions gap-md border-top mt-lg pt-lg">
        <button type="submit" class="btn btn-primary"><?php echo $isEdit ? __('tx_btn_update') : __('tx_btn_save'); ?></button> 
        <a href="/recurring" class="btn btn-secondary"><?php echo __('tx_btn_cancel'); ?></a>
    </div>
</form>

<script>
    const AUTOCOMPLETE_DATA = <?php echo json_encode($autoData); ?>;
    window.APP_SETTINGS = <?php echo json_encode($settings); ?>;
    
    window.addEventListener('DOMContentLoaded', () => {
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
    });
</script>
