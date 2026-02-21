<div class="report-controls">
    <form method="GET">
        <label>Year: </label>
        <select name="year" onchange="this.form.submit()">
            <?php foreach ($periods['years'] as $y): ?>
                <option value="<?php echo $y; ?>" <?php echo $y == $year ? 'selected' : ''; ?>><?php echo $y; ?></option>
            <?php endforeach; ?>
        </select>
        
        <label>From: </label>
        <select name="start_month">
            <?php foreach ($availableMonths as $ms): ?>
                <option value="<?php echo $ms; ?>" <?php echo $ms == $startMonth ? 'selected' : ''; ?>><?php echo $ms; ?></option>
            <?php endforeach; ?>
        </select>
        
        <label>To: </label>
        <select name="end_month">
            <?php foreach ($availableMonths as $ms): ?>
                <option value="<?php echo $ms; ?>" <?php echo $ms == $endMonth ? 'selected' : ''; ?>><?php echo $ms; ?></option>
            <?php endforeach; ?>
        </select>
        
        <button type="submit" class="btn-primary">Update</button>
    </form>
</div>

<div class="report-title">
    Monthly Income Statement: <?php echo $year; ?> (<?php echo $startMonth; ?> to <?php echo $endMonth; ?>)
</div>

<div class="report-container">
<?php
printf("%-" . $widths['label'] . "s", "");
foreach ($labels as $l) printf(" || %" . $widths['amounts'][$l] . "s", $l);

$rowWidth = $widths['label'];
foreach ($labels as $l) $rowWidth += 4 + $widths['amounts'][$l];
$separator = "\n" . str_repeat('=', $rowWidth) . "\n";

echo $separator;

$periodicTotals = [];
renderTree($tree, $labels, $periodicTotals, $widths);

echo $separator;

printf("%-" . $widths['label'] . "s", " Net Income:");
foreach ($labels as $l) {
    $inc = $periodicTotals[$l]['Income'] ?? [];
    $exp = $periodicTotals[$l]['Expenses'] ?? [];
    $total = 0.0;
    foreach ($inc as $c => $a) { $total += $a; }
    foreach ($exp as $c => $a) { $total += $a; }
    
    $formatted = formatCurrency($total, \App\Core\UserContext::get()->getSettings());
    printf(" || %" . $widths['amounts'][$l] . "s", $formatted);
}
echo "\n" . str_repeat('=', $rowWidth) . "\n";
?>
</div>
