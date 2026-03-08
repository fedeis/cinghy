<h1><?php echo __('settings_title'); ?></h1>

<form method="POST" action="/settings">
    <section class="card">
        <h3><?php echo __('settings_language_section'); ?></h3>
        
        <div class="row align-center gap-md mb-4">
            <label><?php echo __('settings_language'); ?>:</label>
            <select name="language" style="width: auto;">
                <?php 
                $langFiles = glob(__DIR__ . '/../../lang/*.php');
                foreach ($langFiles as $file) {
                    $code = basename($file, '.php');
                    $langData = include $file;
                    $name = $langData['language_name'] ?? strtoupper($code);
                    $selected = ($settings['language'] ?? 'en') === $code ? 'selected' : '';
                    echo "<option value=\"$code\" $selected>$name</option>\n";
                }
                ?>
            </select>
        </div>

        <div class="row">
            <label><?php echo __('settings_term_assets'); ?>:</label>
            <input type="text" name="term_assets" value="<?php echo htmlspecialchars($settings['term_assets'] ?? 'Assets'); ?>" placeholder="Assets">
        </div>
        <div class="row">
            <label><?php echo __('settings_term_liabilities'); ?>:</label>
            <input type="text" name="term_liabilities" value="<?php echo htmlspecialchars($settings['term_liabilities'] ?? 'Liabilities'); ?>" placeholder="Liabilities">
        </div>
        <div class="row">
            <label><?php echo __('settings_term_equity'); ?>:</label>
            <input type="text" name="term_equity" value="<?php echo htmlspecialchars($settings['term_equity'] ?? 'Equity'); ?>" placeholder="Equity">
        </div>
        <div class="row">
            <label><?php echo __('settings_term_income'); ?>:</label>
            <input type="text" name="term_income" value="<?php echo htmlspecialchars($settings['term_income'] ?? 'Income'); ?>" placeholder="Income">
        </div>
        <div class="row mb-4">
            <label><?php echo __('settings_term_expenses'); ?>:</label>
            <input type="text" name="term_expenses" value="<?php echo htmlspecialchars($settings['term_expenses'] ?? 'Expenses'); ?>" placeholder="Expenses">
        </div>
    </section>

    <section class="card">
        <h3><?php echo __('settings_ui_personalization'); ?></h3>
        
        <div class="row align-center gap-md mb-4">
            <label><?php echo __('settings_accent_color'); ?>:</label>
            <input type="color" name="accent_color" value="<?php echo htmlspecialchars($settings['accent_color'] ?? '#32e68f'); ?>" class="color-picker">
            <code class="text-sm"><?php echo htmlspecialchars($settings['accent_color'] ?? '#32e68f'); ?></code>
        </div>
        
        <div class="row">
            <label><?php echo __('settings_theme'); ?>:</label>
            <select name="theme">
                <option value="system" <?php echo ($settings['theme'] ?? 'system') === 'system' ? 'selected' : ''; ?>><?php echo __('settings_theme_system'); ?></option>
                <option value="light" <?php echo ($settings['theme'] ?? '') === 'light' ? 'selected' : ''; ?>><?php echo __('settings_theme_light'); ?></option>
                <option value="dark" <?php echo ($settings['theme'] ?? '') === 'dark' ? 'selected' : ''; ?>><?php echo __('settings_theme_dark'); ?></option>
            </select>
        </div>
    </section>

    <section class="card">
        <h3><?php echo __('settings_parsing_logic'); ?></h3>
        
        <div class="row">
            <label><?php echo __('settings_journal_syntax'); ?>:</label>
            <select name="syntax">
                <option value="hledger" <?php echo ($settings['syntax'] ?? '') === 'hledger' ? 'selected' : ''; ?>>hledger</option>
                <option value="ledger" <?php echo ($settings['syntax'] ?? '') === 'ledger' ? 'selected' : ''; ?>>ledger</option>
                <option value="beancount" <?php echo ($settings['syntax'] ?? '') === 'beancount' ? 'selected' : ''; ?>>beancount</option>
            </select>
        </div>
        
        <div class="row">
            <label><?php echo __('settings_use_pending'); ?>:</label>
            <div class="flex-row align-center gap-sm">
                <input type="checkbox" name="use_pending" value="1" <?php echo ($settings['use_pending'] ?? false) ? 'checked' : ''; ?> class="auto-width">
            </div>
        </div>

        <div class="row">
            <label><?php echo __('settings_autocomplete_scope'); ?>:</label>
            <select name="autocomplete_scope">
                <option value="all" <?php echo ($settings['autocomplete_scope'] ?? 'all') === 'all' ? 'selected' : ''; ?>><?php echo __('settings_autocomplete_all'); ?></option>
                <option value="current_year" <?php echo ($settings['autocomplete_scope'] ?? '') === 'current_year' ? 'selected' : ''; ?>><?php echo __('settings_autocomplete_year'); ?></option>
            </select>
        </div>
    </section>

    <section class="card">
        <h3><?php echo __('settings_currency_formatting'); ?></h3>
        
        <div class="row">
            <label><?php echo __('settings_currency_symbol'); ?>:</label>
            <input type="text" name="currency_symbol" value="<?php echo htmlspecialchars($settings['currency_symbol'] ?? 'EUR'); ?>" class="symbol-input text-center">
        </div>
        
        <div class="row">
            <label><?php echo __('settings_currency_position'); ?>:</label>
            <select name="currency_position">
                <option value="after" <?php echo ($settings['currency_position'] ?? '') === 'after' ? 'selected' : ''; ?>><?php echo __('settings_pos_after'); ?> (100.00 EUR)</option>
                <option value="before" <?php echo ($settings['currency_position'] ?? '') === 'before' ? 'selected' : ''; ?>><?php echo __('settings_pos_before'); ?> (EUR 100.00)</option>
            </select>
        </div>
        
        <div class="row">
            <label><?php echo __('settings_currency_spacing'); ?>:</label>
            <div class="flex-row align-center gap-sm">
                <input type="checkbox" name="currency_spacing" value="1" <?php echo ($settings['currency_spacing'] ?? false) ? 'checked' : ''; ?> class="auto-width">
            </div>
        </div>
        
        <div class="row">
            <label><?php echo __('settings_separators'); ?>:</label>
            <div class="flex-row gap-sm">
                <div class="flex-1">
                    <small><?php echo __('settings_sep_decimal'); ?></small>
                    <input type="text" name="decimal_sep" value="<?php echo htmlspecialchars($settings['decimal_sep'] ?? ','); ?>" class="text-center">
                </div>
                <div class="flex-1">
                    <small><?php echo __('settings_sep_thousands'); ?></small>
                    <input type="text" name="thousands_sep" value="<?php echo htmlspecialchars($settings['thousands_sep'] ?? '.'); ?>" class="text-center">
                </div>
            </div>
        </div>
    </section>

    <section class="card">
        <h3><?php echo __('settings_journal_formatting'); ?></h3>
        
        <div class="row">
            <label><?php echo __('settings_indent_spaces'); ?>:</label>
            <input type="number" name="indent_spaces" value="<?php echo htmlspecialchars($settings['indent_spaces'] ?? 4); ?>" min="1" max="8" class="text-center">
        </div>
        
        <div class="row">
            <label><?php echo __('settings_journal_width'); ?>:</label>
            <input type="number" name="journal_width" value="<?php echo htmlspecialchars($settings['journal_width'] ?? 50); ?>" min="40" max="120" class="text-center">
        </div>
    </section>

    <section class="card">
        <h3><?php echo __('settings_dashboard_widgets'); ?></h3>
        
        <div id="widget-list">
            <?php
            $availableWidgets = [
                'quick_links' => __('widget_quick_links'),
                'net_worth' => __('widget_net_worth'),
                'recent_transactions' => __('widget_recent_transactions'),
                'income_statement' => __('widget_income_statement'),
                'my_assets' => __('widget_my_assets')
            ];
            $currentWidgets = $settings['dashboard_widgets'] ?? array_keys($availableWidgets);
            
            // Ensure all available widgets are in the list (even if disabled)
            foreach ($availableWidgets as $key => $label) {
                if (!in_array($key, $currentWidgets)) {
                    $currentWidgets[] = $key;
                }
            }

            foreach ($currentWidgets as $widgetKey): 
                if (!isset($availableWidgets[$widgetKey])) continue;
                $isEnabled = in_array($widgetKey, ($settings['dashboard_widgets'] ?? array_keys($availableWidgets)));
            ?>
                <div class="row widget-config-item flex-row align-center gap-md p-sm border radius-sm mb-sm bg-default">
                    <input type="checkbox" name="enabled_widgets[]" value="<?php echo $widgetKey; ?>" <?php echo $isEnabled ? 'checked' : ''; ?> class="auto-width">
                    <input type="hidden" name="dashboard_widgets_order[]" value="<?php echo $widgetKey; ?>">
                    <span class="flex-1"><?php echo $availableWidgets[$widgetKey]; ?></span>
                    <div class="flex-row gap-xs">
                        <button type="button" class="btn-icon" onclick="moveWidget(this, -1)">↑</button>
                        <button type="button" class="btn-icon" onclick="moveWidget(this, 1)">↓</button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="card">
        <h3><?php echo __('settings_git_sync'); ?></h3>
        
        <div class="row align-center gap-md mb-4">
            <input type="checkbox" name="git_sync_enabled" id="git_sync_enabled" value="1" <?php echo ($settings['git_sync_enabled'] ?? false) ? 'checked' : ''; ?> class="auto-width" onchange="document.getElementById('git-options').style.display = this.checked ? 'block' : 'none'">
            <label for="git_sync_enabled"><?php echo __('settings_git_enable'); ?></label>
        </div>
        
        <div id="git-options" style="display: <?php echo ($settings['git_sync_enabled'] ?? false) ? 'block' : 'none'; ?>; padding: 1rem; background: var(--surface-3); border-radius: 8px;">
            <div class="row mb-sm align-center gap-md">
                <label><?php echo __('settings_git_service'); ?>:</label>
                <div class="flex-row gap-lg">
                    <label class="flex-row align-center gap-xs">
                        <input type="radio" name="git_service" value="github" <?php echo ($settings['git_service'] ?? 'github') === 'github' ? 'checked' : ''; ?> class="auto-width" onchange="document.getElementById('git-url-row').style.display = 'none';"> GitHub
                    </label>
                    <label class="flex-row align-center gap-xs">
                        <input type="radio" name="git_service" value="codeberg" <?php echo ($settings['git_service'] ?? '') === 'codeberg' ? 'checked' : ''; ?> class="auto-width" onchange="document.getElementById('git-url-row').style.display = 'flex';"> Codeberg / Gitea
                    </label>
                </div>
            </div>

            <div class="row mb-sm" id="git-url-row" style="display: <?php echo ($settings['git_service'] ?? 'github') === 'codeberg' ? 'flex' : 'none'; ?>;">
                <label><?php echo __('settings_git_base_url'); ?>:</label>
                <input type="text" name="git_base_url" value="<?php echo htmlspecialchars($settings['git_base_url'] ?? 'https://codeberg.org'); ?>" placeholder="https://codeberg.org">
            </div>

            <div class="row mb-sm">
                <label><?php echo __('settings_git_token'); ?>:</label>
                <input type="password" name="git_token" value="<?php echo htmlspecialchars($settings['git_token'] ?? $settings['github_token'] ?? ''); ?>" placeholder="Token / Personal Access Token">
            </div>
            <div class="row mb-sm">
                <label><?php echo __('settings_git_repo'); ?>:</label>
                <input type="text" name="git_repo" value="<?php echo htmlspecialchars($settings['git_repo'] ?? $settings['github_repo'] ?? ''); ?>" placeholder="owner/repository">
            </div>
            <div class="row">
                <label><?php echo __('settings_git_branch'); ?>:</label>
                <input type="text" name="git_branch" value="<?php echo htmlspecialchars($settings['git_branch'] ?? $settings['github_branch'] ?? 'main'); ?>" placeholder="main">
            </div>
        </div>
    </section>

    <section class="card">
        <h3><?php echo __('settings_system_utilities'); ?></h3>
        
        <div class="flex-row gap-sm">
            <a href="/cache/reset" class="btn btn-secondary" onclick="return confirm('Clear all cache?')"><?php echo __('settings_reset_cache'); ?></a>
            <a href="/logout" class="btn btn-danger"><?php echo __('settings_logout'); ?></a>
            <?php if ((new \App\Core\AuthService())->isSuperAdmin()): ?>
                <a href="/admin" class="btn btn-primary" style="margin-left: auto;"><?php echo __('admin_manage_users'); ?></a>
            <?php endif; ?>
        </div>
    </section>

    <script>
    function moveWidget(btn, direction) {
        const item = btn.closest('.widget-config-item');
        if (direction === -1 && item.previousElementSibling) {
            item.parentNode.insertBefore(item, item.previousElementSibling);
        } else if (direction === 1 && item.nextElementSibling) {
            item.parentNode.insertBefore(item.nextElementSibling, item);
        }
    }
    </script>
    
    <div class="mt-4">
        <button type="submit" class="btn btn-primary"><?php echo __('settings_save'); ?></button>
    </div>
    <input type="hidden" name="csrf_token" value="<?= \App\Core\Router::csrfToken() ?>">
</form>
