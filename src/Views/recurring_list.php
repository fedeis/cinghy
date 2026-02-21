<?php
$today = date('Y-m-d');

$recentlyExecuted = [];
$upcomingExecutions = [];

foreach ($recurring as $rec) {
    if ($rec['next_run_date'] <= $today) {
        $upcomingExecutions[] = $rec;
    } else {
        $upcomingExecutions[] = $rec;
    }
    
    // Check if it was ever executed
    if (!empty($rec['last_run_date'])) {
        $recentlyExecuted[] = array_merge($rec, ['_sort_date' => $rec['last_run_date']]);
    }
}

// Sort recently executed by last_run_date DESC
usort($recentlyExecuted, fn($a, $b) => strcmp($b['_sort_date'], $a['_sort_date']));
// Sort upcoming by next_run_date ASC
usort($upcomingExecutions, fn($a, $b) => strcmp($a['next_run_date'], $b['next_run_date']));

?>

<header class="page-header flex-row align-center justify-between mb-lg">
    <h1><?php echo __('auto_title'); ?></h1>
    <a href="/recurring/add" class="btn btn-primary">+ <?php echo __('auto_create_new'); ?></a>
</header>

<div class="row gap-lg mb-lg">
    <div class="card flex-1">
        <h3 class="mb-md text-muted"><svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none" style="vertical-align: middle; margin-right: 8px;"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline><polyline points="17 6 23 6 23 12"></polyline></svg> <?php echo __('auto_upcoming'); ?></h3>
        <?php if (empty($upcomingExecutions)): ?>
            <p class="text-sm text-muted"><?php echo __('auto_no_scheduled'); ?></p>
        <?php else: ?>
            <ul style="list-style: none; padding: 0; margin: 0;">
                <?php foreach (array_slice($upcomingExecutions, 0, 5) as $rec): ?>
                    <li class="flex-row justify-between align-center border-bottom py-sm">
                        <div>
                            <strong><?php echo htmlspecialchars($rec['payee']); ?></strong>
                            <div class="text-sm text-muted">Runs <b><?php echo htmlspecialchars($rec['next_run_date']); ?></b></div>
                        </div>
                        <span class="badge" style="background:var(--surface-3); padding:4px 8px; border-radius:4px; font-size:0.8rem;"><?php echo htmlspecialchars($rec['frequency']); ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
    
    <div class="card flex-1">
        <h3 class="mb-md text-muted"><svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none" style="vertical-align: middle; margin-right: 8px;"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>   <?php echo __('auto_recently_executed'); ?></h3>
        <?php if (empty($recentlyExecuted)): ?>
            <p class="text-sm text-muted"><?php echo __('auto_no_recently_executed'); ?></p>
        <?php else: ?>
            <ul style="list-style: none; padding: 0; margin: 0;">
                <?php foreach (array_slice($recentlyExecuted, 0, 5) as $rec): ?>
                    <li class="flex-row justify-between align-center border-bottom py-sm">
                        <div>
                            <strong><?php echo htmlspecialchars($rec['payee']); ?></strong>
                            <div class="text-sm text-muted">Ran on <b><?php echo htmlspecialchars($rec['last_run_date']); ?></b></div>
                        </div>
                        <span class="text-sm text-success" style="color:var(--accent-color);">Success</span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <h3 class="mb-md"><?php echo __('auto_all_rules'); ?></h3>
    <?php if (empty($recurring)): ?>
        <p class="text-sm text-muted"><?php echo __('auto_no_rules'); ?></p>
    <?php else: ?>
        <table class="table" style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="text-align: left; border-bottom: 2px solid var(--border-color);">
                    <th style="padding: 1rem;"><?php echo __('tx_payee'); ?></th>
                    <th style="padding: 1rem;"><?php echo __('tx_memo'); ?></th>
                    <th style="padding: 1rem;"><?php echo __('auto_frequency'); ?></th>
                    <th style="padding: 1rem;"><?php echo __('auto_next_run'); ?></th>
                    <th style="padding: 1rem; text-align: right;"><?php echo __('admin_actions'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recurring as $rec): ?>
                <tr style="border-bottom: 1px solid var(--border-color);">
                    <td style="padding: 1rem;">
                        <strong><?php echo htmlspecialchars($rec['payee']); ?></strong>
                    </td>
                    <td style="padding: 1rem; color: var(--text-secondary); font-family: monospace; font-size: 0.9em;"><?php echo htmlspecialchars($rec['description']); ?></td>
                    <td style="padding: 1rem;">
                        <span style="font-size: 0.8rem; background: var(--surface-3); padding: 0.2rem 0.5rem; border-radius: 4px; display:inline-flex; gap: 4px; align-items:center;">
                            <svg viewBox="0 0 24 24" width="12" height="12" stroke="currentColor" stroke-width="2" fill="none"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                            <?php echo ucfirst($rec['frequency']); ?>
                            <?php if ($rec['interval'] > 1): ?>
                                (x<?php echo $rec['interval']; ?>)
                            <?php endif; ?>
                        </span>
                    </td>
                    <td style="padding: 1rem; font-weight:bold; color: <?php echo ($rec['next_run_date'] <= $today) ? 'var(--accent-color)' : 'var(--text-primary)'; ?>;">
                        <?php echo htmlspecialchars($rec['next_run_date']); ?>
                    </td>
                    <td style="padding: 1rem; text-align: right;">
                        <a href="/recurring/edit?id=<?php echo urlencode($rec['id']); ?>" class="btn btn-secondary btn-sm" style="margin-right:0.5rem;"><?php echo __('files_edit_title'); ?></a>
                        <form action="/recurring/delete" method="POST" style="display:inline;" onsubmit="return confirm('Delete this automated transaction?')">
                            <input type="hidden" name="id" value="<?php echo htmlspecialchars($rec['id']); ?>">
                            <button type="submit" class="btn btn-danger btn-sm" style="background: #ff4d4d33; color: #ff4d4d;"><?php echo __('admin_delete'); ?></button>
                            <input type="hidden" name="csrf_token" value="<?= \App\Core\Router::csrfToken() ?>">

                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
