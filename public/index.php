<?php

declare(strict_types=1);

use App\Core\AuthService;
use App\Core\Router;
use App\Core\UserContext;
use App\Accounting\Transaction;
use App\Accounting\Posting;
use App\Accounting\TransactionWriter;
use App\Accounting\Aggregator;
use App\Accounting\RecurringManager;
use App\Cache\CacheManager;

// Error Reporting
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

// Session Start
session_start();

// Autoload
require_once __DIR__ . '/../src/bootstrap.php';

// --- Core UI Helper ---
function render(string $viewPath, array $data = []) {
    extract($data);
    $viewFile = __DIR__ . '/../src/Views/' . $viewPath . '.php';
    
    // Catch view content
    ob_start();
    if (file_exists($viewFile)) {
        require $viewFile;
    } else {
        // Fallback for logic-heavy views that are still in index.php but we want to wrap
        echo $content ?? '';
    }
    $content = ob_get_clean();
    
    $layoutFile = $layout ?? 'layout';
    require __DIR__ . '/../src/Views/' . $layoutFile . '.php';
}

$auth = new AuthService();
$router = new Router();

// Middleware-like check
if (!$auth->isLoggedIn()) {
    // Local Auto-Login Bypass
    $env = $_ENV['APP_ENV'] ?? getenv('APP_ENV');
    $autoUser = $_ENV['AUTO_LOGIN_USER'] ?? getenv('AUTO_LOGIN_USER');
    
    if ($env === 'local' && !empty($autoUser)) {
        $_SESSION['user'] = $autoUser;
    }
}

if (!$auth->isLoggedIn()) {
    $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

    // Initial Registration
    if (!$auth->hasUsers()) {
        $router->get('/register', function() {
            render('register', ['title' => 'Cinghy - Initial Setup', 'layout' => 'auth_layout']);
        });
        $router->post('/register', function() use ($auth) {
            $user = trim($_POST['username'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $pass1 = $_POST['password'] ?? '';
            $pass2 = $_POST['password_confirm'] ?? '';

            if (empty($user) || empty($email) || empty($pass1)) {
                render('register', ['title' => 'Cinghy - Initial Setup', 'layout' => 'auth_layout', 'error' => 'All fields required']);
                return;
            }
            if ($pass1 !== $pass2) {
                render('register', ['title' => 'Cinghy - Initial Setup', 'layout' => 'auth_layout', 'error' => 'Passwords do not match']);
                return;
            }

            if ($auth->register($user, $pass1, $email, 'superadmin')) {
                $auth->login($user, $pass1);
                header('Location: /');
            } else {
                render('register', ['title' => 'Cinghy - Initial Setup', 'layout' => 'auth_layout', 'error' => 'Registration failed']);
            }
        });

        if (in_array($uri, ['/register'])) {
            $router->dispatch();
            exit;
        }

        // If no users, force them to /register
        header('Location: /register');
        exit;
    }

    // Login for existing users
    $router->get('/login', function() {
        render('login', ['title' => 'Cinghy - Login', 'layout' => 'auth_layout']);
    });
    
    $router->post('/login', function() use ($auth) {
        $user = trim($_POST['username'] ?? '');
        $pass = $_POST['password'] ?? '';

        if ($auth->login($user, $pass)) {
            header('Location: /');
        } else {
            render('login', ['title' => 'Cinghy - Login', 'layout' => 'auth_layout', 'error' => 'Invalid credentials']);
        }
    });

    if (in_array($uri, ['/login'])) {
        $router->dispatch();
        exit;
    }

    header('Location: /login');
    exit;
}

// User is logged in, Initialize Context
UserContext::create($auth->getUser());

$appSettings = UserContext::get()->getSettings();
\App\Core\Lang::init($appSettings['language'] ?? 'en');

// Auto-run recurring transactions
(new RecurringManager())->processPending();

// The render function is moved above login handling

// --- Report Helpers ---

function formatCurrency(float $amount, array $settings): string {
    $formatted = number_format(abs($amount), 2, $settings['decimal_sep'], $settings['thousands_sep']);
    $symbol = $settings['currency_symbol'] ?? 'EUR';
    $spacing = $settings['currency_spacing'] ? ' ' : '';
    $sign = $amount < 0 ? '-' : '';

    if ($settings['currency_position'] === 'before') {
        return $sign . $symbol . $spacing . $formatted;
    } else {
        return $sign . $formatted . $spacing . $symbol;
    }
}

function buildAccountTree(array $periodicData, array $prefixes) {
    $tree = [];
    foreach ($periodicData as $periodLabel => $data) {
        foreach ($data['balances'] as $account => $currencies) {
            $parts = explode(':', $account);
            if (!in_array($parts[0], $prefixes)) continue;
            
            $currNode = &$tree;
            $fullPath = "";
            foreach ($parts as $part) {
                $fullPath = $fullPath ? $fullPath . ":" . $part : $part;
                if (!isset($currNode[$part])) {
                    $currNode[$part] = ['_values' => [], '_children' => [], '_path' => $fullPath];
                }
                foreach ($currencies as $curr => $amt) {
                    if (!isset($currNode[$part]['_values'][$periodLabel][$curr])) {
                        $currNode[$part]['_values'][$periodLabel][$curr] = 0.0;
                    }
                    $currNode[$part]['_values'][$periodLabel][$curr] += $amt;
                }
                // Mark if node has direct postings or is just a parent
                if ($part === end($parts)) {
                    $currNode[$part]['_has_direct'] = true;
                }
                $currNode = &$currNode[$part]['_children'];
            }
        }
    }
    return $tree;
}

function calculateTreeWidths(array $tree, array $periodLabels, int $indent = 0, &$maxWidths = []) {
    if (!isset($maxWidths['label'])) $maxWidths['label'] = 0;
    foreach ($periodLabels as $p) if (!isset($maxWidths['amounts'][$p])) $maxWidths['amounts'][$p] = 0;

    foreach ($tree as $name => $node) {
        $hasValue = false;
        foreach ($node['_values'] as $pVals) {
            foreach ($pVals as $val) if (abs($val) > 0.001) $hasValue = true;
        }
        if (!$hasValue && empty($node['_children'])) continue;

        $labelLen = strlen(str_repeat('  ', $indent) . $name);
        if ($labelLen > $maxWidths['label']) $maxWidths['label'] = $labelLen;

        foreach ($periodLabels as $pLabel) {
            if (!empty($node['_children']) && empty($node['_has_direct'])) continue;
            
            $vals = $node['_values'][$pLabel] ?? [];
            if (empty($vals)) {
                $len = 1; // "0"
            } else {
                reset($vals);
                $curr = key($vals);
                $amt = current($vals);
                $formatted = number_format($amt, 2, ',', '.') . $curr;
                $len = strlen($formatted);
            }
            if ($len > $maxWidths['amounts'][$pLabel]) {
                $maxWidths['amounts'][$pLabel] = $len;
            }
        }
        calculateTreeWidths($node['_children'], $periodLabels, $indent + 1, $maxWidths);
    }
    return $maxWidths;
}

function renderTree(array $tree, array $periodLabels, array &$periodicTotals, array $widths, int $indent = 0) {
    ksort($tree);
    foreach ($tree as $name => $node) {
        $hasValue = false;
        foreach ($node['_values'] as $pVals) {
            foreach ($pVals as $val) if (abs($val) > 0.001) $hasValue = true;
        }
        if (!$hasValue && empty($node['_children'])) continue;

        $label = str_repeat('  ', $indent) . $name;
        printf("%-" . $widths['label'] . "s", $label);
        
        foreach ($periodLabels as $pLabel) {
            $w = $widths['amounts'][$pLabel] ?? 13;
            if (!empty($node['_children']) && empty($node['_has_direct'])) {
                printf(" || %" . $w . "s", "");
                continue;
            }

            $vals = $node['_values'][$pLabel] ?? [];
            if (empty($vals)) {
                printf(" || %" . $w . "s", "0");
                continue;
            }
            reset($vals);
            $curr = key($vals);
            $amt = current($vals);
            
            if (abs($amt) < 0.001) {
                printf(" || %" . $w . "s", "0");
            } else {
                $ctx = UserContext::get();
                $formatted = formatCurrency($amt, $ctx->getSettings());
                printf(" || %" . $w . "s", $formatted);
            }
            
            if ($indent === 0) {
                $periodicTotals[$pLabel][$name][$curr] = ($periodicTotals[$pLabel][$name][$curr] ?? 0.0) + $amt;
            }
        }
        echo "\n";
        renderTree($node['_children'], $periodLabels, $periodicTotals, $widths, $indent + 1);
    }
}

$router->get('/', function() {
    $ctx = UserContext::get();
    $settings = $ctx->getSettings();
    
    $heuristicSettings = \App\Accounting\SettingsHeuristic::detect($ctx->getDataPath());
    $discrepancy = false;
    $checkKeys = ['decimal_sep', 'thousands_sep', 'currency_position']; 
    // we omit comparing currency_symbol because users might sometimes intentionally override it on display
    // but the position and separators strongly dictate formatting errors.
    foreach ($checkKeys as $key) {
        if (isset($settings[$key]) && isset($heuristicSettings[$key]) && $settings[$key] !== $heuristicSettings[$key]) {
            $discrepancy = true;
            break;
        }
    }

    render('home', [
        'title' => __('nav_home') . ' - Home',
        'extra_css' => ['dashboard'],
        'user' => htmlspecialchars($ctx->getUsername()),
        'path' => htmlspecialchars($ctx->getDataPath()),
        'settings' => $settings,
        'has_discrepancy' => $discrepancy
    ]);
});

$router->get('/balance', function() {
    $agg = new Aggregator();
    $periods = $agg->getAvailablePeriods();
    
    $year = $_GET['year'] ?? (end($periods['years']) ?: date('Y'));
    $startMonth = $_GET['start_month'] ?? '01';
    $endMonth = $_GET['end_month'] ?? '12';
    
    $buckets = [];
    for ($m = (int)$startMonth; $m <= (int)$endMonth; $m++) {
        $mStr = str_pad((string)$m, 2, '0', STR_PAD_LEFT);
        $lastDay = date('t', strtotime("$year-$mStr-01"));
        $date = "$year-$mStr-$lastDay";
        $buckets[$date] = ['start' => '0000-01-01', 'end' => $date];
    }
    $labels = array_keys($buckets);

    $ctx = UserContext::get();
    $settings = $ctx->getSettings();

    $periodicData = $agg->getPeriodicBalances($buckets);
    $termAssets = $settings['term_assets'] ?? 'Assets';
    $termLiabilities = $settings['term_liabilities'] ?? 'Liabilities';
    $termEquity = $settings['term_equity'] ?? 'Equity';
    
    $tree = buildAccountTree($periodicData, [$termAssets, $termLiabilities, $termEquity]);

    $widths = calculateTreeWidths($tree, $labels);
    $widths['label'] = max($widths['label'], 5);
    foreach ($labels as $l) {
        $headerLen = strlen(substr($l, 5)); 
        $widths['amounts'][$l] = max($widths['amounts'][$l] ?? 0, $headerLen, 11);
    }

    render('balance', [
        'title' => __('nav_home') . ' - Balance Sheet',
        'extra_css' => ['reports'],
        'periods' => $periods,
        'year' => $year,
        'startMonth' => $startMonth,
        'endMonth' => $endMonth,
        'availableMonths' => $periods['months'][$year] ?? array_map(fn($m) => str_pad((string)$m, 2, '0', STR_PAD_LEFT), range(1, 12)),
        'tree' => $tree,
        'labels' => $labels,
        'widths' => $widths
    ]);
});

$router->get('/income', function() {
    $agg = new \App\Accounting\Aggregator();
    $periods = $agg->getAvailablePeriods();
    
    $year = $_GET['year'] ?? (end($periods['years']) ?: date('Y'));
    $startMonth = $_GET['start_month'] ?? '01';
    $endMonth = $_GET['end_month'] ?? '12';
    
    $buckets = [];
    for ($m = (int)$startMonth; $m <= (int)$endMonth; $m++) {
        $mStr = str_pad((string)$m, 2, '0', STR_PAD_LEFT);
        $lastDay = date('t', strtotime("$year-$mStr-01"));
        $buckets[$mStr] = ['start' => "$year-$mStr-01", 'end' => "$year-$mStr-$lastDay"];
    }
    $labels = array_keys($buckets);

    $ctx = UserContext::get();
    $settings = $ctx->getSettings();

    $periodicData = $agg->getPeriodicBalances($buckets);
    $termIncome = $settings['term_income'] ?? 'Income';
    $termExpenses = $settings['term_expenses'] ?? 'Expenses';
    
    $tree = buildAccountTree($periodicData, [$termIncome, $termExpenses]);

    $widths = calculateTreeWidths($tree, $labels);
    $widths['label'] = max($widths['label'], 12);
    foreach ($labels as $l) {
        $widths['amounts'][$l] = max($widths['amounts'][$l] ?? 0, strlen($l), 11);
    }

    render('income', [
        'title' => __('nav_home') . ' - Income Statement',
        'extra_css' => ['reports'],
        'periods' => $periods,
        'year' => $year,
        'startMonth' => $startMonth,
        'endMonth' => $endMonth,
        'availableMonths' => $periods['months'][$year] ?? array_map(fn($m) => str_pad((string)$m, 2, '0', STR_PAD_LEFT), range(1, 12)),
        'tree' => $tree,
        'labels' => $labels,
        'widths' => $widths
    ]);
});

$router->get('/transactions', function() {
    $ctx = UserContext::get();
    $cache = new CacheManager();
    
    $dataPath = $ctx->getDataPath();
    $files = glob($dataPath . '/*.journal');
    $names = array_map(fn($f) => basename($f, '.journal'), $files);
    rsort($names);

    $allTransactions = [];
    foreach ($names as $name) {
        $txs = $cache->getFileData($name);
        $allTransactions = array_merge($allTransactions, $txs);
    }

    usort($allTransactions, fn($a, $b) => strcmp($b['date'], $a['date']));

    render('transactions', [
        'title' => 'Cinghy - Transactions',
        'allTransactions' => $allTransactions
    ]);
});

$router->get('/transactions/add', function() {
    $ctx = UserContext::get();
    $settings = $ctx->getSettings();
    $scope = $settings['autocomplete_scope'] ?? 'all';
    $year = ($scope === 'current_year') ? date('Y') : null;

    $agg = new \App\Accounting\Aggregator();
    $autoData = $agg->getAutocompleteData($year);
    
    render('add_transaction', [
        'title' => 'Cinghy - Add Transaction',
        'extra_css' => ['forms'],
        'extra_js' => ['autocomplete', 'transaction-form'],
        'autoData' => $autoData,
        'settings' => $ctx->getSettings()
    ]);
});
$router->post('/transactions/add', function() {
    $ctx = UserContext::get();
    $settings = $ctx->getSettings();

    $date = $_POST['date'] ?? '';
    $status = $_POST['status'] ?? ($settings['use_pending'] ? '*' : '');
    $payee = $_POST['payee'] ?? '';
    $description = $_POST['description'] ?? '';
    $accounts = $_POST['accounts'] ?? [];
    $amounts = $_POST['amounts'] ?? [];

    if (empty($date) || empty($payee) || empty($accounts)) {
        http_response_code(400);
        echo "Missing required fields.";
        exit;
    }

    $tx = new Transaction($date, $payee, $description, $status);
    
    $total = 0.0;
    $missingIndex = -1;
    $validPostings = [];

    foreach ($accounts as $i => $account) {
        $account = trim($account);
        if (empty($account)) continue;
        
        $amtRaw = trim($amounts[$i] ?? '');
        if ($amtRaw === '') {
            if ($missingIndex === -1) {
                $missingIndex = count($validPostings);
            } else {
                $missingIndex = -2;
            }
            $validPostings[] = ['account' => $account, 'amount' => 0.0];
        } else {
            // Robust parsing of formatted currency strings
            $dec = $settings['decimal_sep'] ?? '.';
            $thousands = $settings['thousands_sep'] ?? '';
            $symbol = $settings['currency_symbol'] ?? 'EUR';
            
            $clean = str_replace([$symbol, $thousands, ' ', "\xc2\xa0"], '', $amtRaw);
            $clean = str_replace($dec, '.', $clean);
            $val = (float)$clean;
            
            $total += $val;
            $validPostings[] = ['account' => $account, 'amount' => $val];
        }
    }

    if ($missingIndex >= 0) {
        $validPostings[$missingIndex]['amount'] = -$total;
    }

    foreach ($validPostings as $vp) {
        $currency = $settings['currency_symbol'] ?? 'EUR';
        $tx->addPosting(new Posting($vp['account'], $vp['amount'], $currency));
    }

    $writer = new TransactionWriter();
    $writer->write($tx);

    header('Location: /transactions');
    exit;
});

// Edit transaction routes
$router->get('/transactions/edit', function() {
    $ctx = UserContext::get();
    $settings = $ctx->getSettings();
    
    $date = $_GET['date'] ?? '';
    $payee = $_GET['payee'] ?? '';
    
    if (empty($date) || empty($payee)) {
        http_response_code(400);
        echo "Missing transaction identifier.";
        exit;
    }
    
    // Find the transaction in the journal file
    $year = substr($date, 0, 4);
    $journalFile = $ctx->getDataPath("{$year}.journal");
    
    if (!file_exists($journalFile)) {
        http_response_code(404);
        echo "Journal file not found.";
        exit;
    }
    
    $lines = file($journalFile, FILE_IGNORE_NEW_LINES);
    $transaction = null;
    $inTransaction = false;
    $txLines = [];
    
    foreach ($lines as $line) {
        // Check if this is the start of our transaction
        if (preg_match('/^(\d{4}-\d{2}-\d{2})\s+([*!])?\s*(.+)$/', $line, $matches)) {
            if ($inTransaction && !empty($txLines)) {
                // We've hit a new transaction, stop collecting
                break;
            }
            
            $lineDate = $matches[1];
            $lineStatus = $matches[2] ?? '';
            $lineRest = trim($matches[3]);
            
            // Separate payee from description (format: "Payee | Description")
            $parts = explode('|', $lineRest, 2);
            $linePayee = trim($parts[0]);
            $lineDescription = isset($parts[1]) ? trim($parts[1]) : '';
            
            if ($lineDate === $date && $linePayee === $payee) {
                $inTransaction = true;
                $transaction = [
                    'date' => $lineDate,
                    'status' => $lineStatus,
                    'payee' => $linePayee,
                    'description' => $lineDescription,
                    'postings' => []
                ];
                $txLines[] = $line;
            }
        } elseif ($inTransaction) {
            // Collect transaction lines
            if (preg_match('/^\s+(.+?)\s{2,}(.+)$/', $line, $matches)) {
                // Posting line with amount
                $account = trim($matches[1]);
                $amountStr = trim($matches[2]);
                
                // Parse amount and currency
                preg_match('/(-?\d+[.,]\d+)\s*(.+)/', $amountStr, $amtMatch);
                $amount = isset($amtMatch[1]) ? str_replace(',', '.', $amtMatch[1]) : '0';
                $currency = isset($amtMatch[2]) ? trim($amtMatch[2]) : $settings['currency_symbol'];
                
                $transaction['postings'][] = [
                    'account' => $account,
                    'amount' => (float)$amount,
                    'currency' => $currency
                ];
                $txLines[] = $line;
            } elseif (preg_match('/^\s+(.+)$/', $line, $matches)) {
                // Posting without amount (balancing posting)
                $account = trim($matches[1]);
                $transaction['postings'][] = [
                    'account' => $account,
                    'amount' => '',
                    'currency' => $settings['currency_symbol']
                ];
                $txLines[] = $line;
            } elseif (trim($line) === '') {
                // Empty line marks end of transaction
                break;
            }
        }
    }
    
    if (!$transaction) {
        http_response_code(404);
        echo "Transaction not found.";
        exit;
    }
    
    // Get autocomplete data
    $scope = $settings['autocomplete_scope'] ?? 'all';
    $autoYear = ($scope === 'current_year') ? date('Y') : null;
    $agg = new \App\Accounting\Aggregator();
    $autoData = $agg->getAutocompleteData($autoYear);
    
    render('add_transaction', [
        'title' => 'Cinghy - Edit Transaction',
        'extra_css' => ['forms'],
        'extra_js' => ['autocomplete', 'transaction-form'],
        'autoData' => $autoData,
        'settings' => $settings,
        'transaction' => $transaction,
        'isEdit' => true
    ]);
});

$router->post('/transactions/edit', function() {
    $ctx = UserContext::get();
    $settings = $ctx->getSettings();
    
    $originalDate = $_POST['original_date'] ?? '';
    $originalPayee = $_POST['original_payee'] ?? '';
    $newDate = $_POST['date'] ?? '';
    $status = $_POST['status'] ?? ($settings['use_pending'] ? '*' : '');
    $payee = $_POST['payee'] ?? '';
    $description = $_POST['description'] ?? '';
    $accounts = $_POST['accounts'] ?? [];
    $amounts = $_POST['amounts'] ?? [];
    
    if (empty($originalDate) || empty($originalPayee) || empty($newDate) || empty($payee)) {
        http_response_code(400);
        echo "Missing required fields.";
        exit;
    }
    
    // Build the new transaction
    $tx = new Transaction($newDate, $payee, $description, $status);
    
    $total = 0.0;
    $missingIndex = -1;
    $validPostings = [];
    
    foreach ($accounts as $i => $account) {
        $account = trim($account);
        if (empty($account)) continue;
        
        $amtRaw = trim($amounts[$i] ?? '');
        if ($amtRaw === '') {
            if ($missingIndex === -1) {
                $missingIndex = count($validPostings);
            } else {
                $missingIndex = -2;
            }
            $validPostings[] = ['account' => $account, 'amount' => 0.0];
        } else {
            $dec = $settings['decimal_sep'] ?? '.';
            $thousands = $settings['thousands_sep'] ?? '';
            $symbol = $settings['currency_symbol'] ?? 'EUR';
            
            $clean = str_replace([$symbol, $thousands, ' ', "\xc2\xa0"], '', $amtRaw);
            $clean = str_replace($dec, '.', $clean);
            $val = (float)$clean;
            
            $total += $val;
            $validPostings[] = ['account' => $account, 'amount' => $val];
        }
    }
    
    if ($missingIndex >= 0) {
        $validPostings[$missingIndex]['amount'] = -$total;
    }
    
    foreach ($validPostings as $vp) {
        $currency = $settings['currency_symbol'] ?? 'EUR';
        $tx->addPosting(new Posting($vp['account'], $vp['amount'], $currency));
    }
    
    // Find and replace the transaction in the journal file
    $year = substr($originalDate, 0, 4);
    $journalFile = $ctx->getDataPath("{$year}.journal");
    
    if (!file_exists($journalFile)) {
        http_response_code(404);
        echo "Journal file not found.";
        exit;
    }
    
    $lines = file($journalFile, FILE_IGNORE_NEW_LINES);
    $newLines = [];
    $inTransaction = false;
    $replaced = false;
    
    foreach ($lines as $line) {
        if (preg_match('/^(\d{4}-\d{2}-\d{2})\s+([*!])?\s*(.+)$/', $line, $matches)) {
            if ($inTransaction) {
                // We were in the transaction to replace, now we're at the next one
                $inTransaction = false;
            }
            
            $lineDate = $matches[1];
            $linePayee = trim($matches[3]);
            $lineRest = trim($matches[3]);

            $parts = explode('|', $lineRest, 2);
            $linePayee = trim($parts[0]);
            
            if ($lineDate === $originalDate && $linePayee === $originalPayee && !$replaced) {
                // This is the transaction to replace
                $inTransaction = true;
                $replaced = true;

                
                
                // Format the new transaction inline
                $statusStr = ($settings['use_pending'] && $tx->status) ? $tx->status . " " : ($settings['use_pending'] ? "* " : "");
                $payeeStr = $tx->payee;
                $memoStr = $tx->description;
                
                if ($settings['syntax'] === 'beancount') {
                    $headerDesc = '"' . str_replace('"', '', $payeeStr) . '"';
                    if ($memoStr) {
                        $headerDesc .= ' "' . str_replace('"', '', $memoStr) . '"';
                    }
                } else {
                    $headerDesc = $payeeStr;
                    if ($memoStr) {
                        $headerDesc .= ' | ' . $memoStr;
                    }
                }
                
                $newLines[] = sprintf("%s %s%s", $tx->date, $statusStr, $headerDesc);
                
                $indentWidth = (int)($settings['indent_spaces'] ?? 4);
                $journalWidth = (int)($settings['journal_width'] ?? 50);
                $indent = str_repeat(' ', $indentWidth);
                
                foreach ($tx->postings as $posting) {
                    $p = $posting instanceof Posting ? $posting : (object)$posting;
                    
                    $amt = number_format(abs($p->amount), 2, $settings['decimal_sep'], $settings['thousands_sep']);
                    $symbol = $settings['currency_symbol'] ?? $p->currency;
                    $spacing = $settings['currency_spacing'] ? ' ' : '';
                    $sign = ($p->amount < 0 ? '-' : '');
                    
                    if ($settings['currency_position'] === 'before') {
                        $amtStr = $sign . $symbol . $spacing . $amt;
                        $amtStrNoSym = $sign . $amt;
                    } else {
                        $amtStr = $sign . $amt . $spacing . $symbol;
                        $amtStrNoSym = $sign . $amt;
                    }
                    
                    $occupied = $indentWidth + strlen($p->account) + strlen($amtStrNoSym);
                    $padding = max(2, $journalWidth - $occupied);
                    
                    $newLines[] = $indent . $p->account . str_repeat(' ', $padding) . $amtStr;
                }
                
                // Add empty line after transaction
                $newLines[] = '';
                
                continue; // Skip the original transaction line
            }
        }
        
        if ($inTransaction) {
            // Skip lines that are part of the old transaction
            if (preg_match('/^\s+/', $line) || trim($line) === '') {
                continue;
            } else {
                // This line is not part of the transaction anymore
                $inTransaction = false;
            }
        }
        
        $newLines[] = $line;
    }
    
    // Write back to file
    file_put_contents($journalFile, implode("\n", $newLines) . "\n");
    
    // Clear cache
    $cache = new \App\Cache\CacheManager();
    $cache->invalidateFile($year);
    
    header('Location: /transactions');
    exit;
});

$router->get('/cache/reset', function() {
    $cache = new CacheManager();
    $cache->clearAll();
    header('Location: /');
    exit;
});

$router->get('/logout', function() use ($auth) {
    (new AuthService())->logout();
    header('Location: /');
    exit;
});

$router->get('/settings', function() {
    $ctx = UserContext::get();
    render('settings', [
        'title' => 'Cinghy - Settings',
        'extra_css' => ['forms'],
        'settings' => $ctx->getSettings()
    ]);
});

$router->post('/settings', function() {
    $ctx = UserContext::get();
    $newSettings = [
        'language' => $_POST['language'] ?? 'en',
        'term_assets' => trim($_POST['term_assets'] ?? 'Assets'),
        'term_liabilities' => trim($_POST['term_liabilities'] ?? 'Liabilities'),
        'term_equity' => trim($_POST['term_equity'] ?? 'Equity'),
        'term_income' => trim($_POST['term_income'] ?? 'Income'),
        'term_expenses' => trim($_POST['term_expenses'] ?? 'Expenses'),
        'syntax' => $_POST['syntax'] ?? 'hledger',
        'use_pending' => isset($_POST['use_pending']),
        'decimal_sep' => $_POST['decimal_sep'] ?? ',',
        'thousands_sep' => $_POST['thousands_sep'] ?? '.',
        'currency_symbol' => $_POST['currency_symbol'] ?? 'EUR',
        'currency_position' => $_POST['currency_position'] ?? 'after',
        'currency_spacing' => isset($_POST['currency_spacing']),
        'journal_width' => (int)($_POST['journal_width'] ?? 50),
        'indent_spaces' => (int)($_POST['indent_spaces'] ?? 4),
        'autocomplete_scope' => $_POST['autocomplete_scope'] ?? 'all',
        'accent_color' => $_POST['accent_color'] ?? '#32e68f',
        'theme' => $_POST['theme'] ?? 'system',
        'dashboard_widgets' => array_values(array_intersect($_POST['dashboard_widgets_order'] ?? [], $_POST['enabled_widgets'] ?? [])),
        'github_sync_enabled' => isset($_POST['github_sync_enabled']),
        'github_token' => trim($_POST['github_token'] ?? ''),
        'github_repo' => trim($_POST['github_repo'] ?? ''),
        'github_branch' => trim($_POST['github_branch'] ?? 'main'),
    ];
    $ctx->saveSettings($newSettings);
    header('Location: /settings?saved=1');
    exit;
});

// Admin Area (Superadmin only)
$router->get('/admin', function() use ($auth) {
    if (!$auth->isSuperAdmin()) {
        header('Location: /');
        exit;
    }
    render('admin_users', [
        'title' => 'Cinghy - Manage Users',
        'extra_css' => ['forms'],
        'users' => $auth->getAllUsers(),
        'success' => $_GET['success'] ?? null,
        'error' => $_GET['error'] ?? null,
    ]);
});

$router->post('/admin/users/create', function() use ($auth) {
    if (!$auth->isSuperAdmin()) {
        header('Location: /');
        exit;
    }
    $user = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $pass1 = $_POST['password'] ?? '';
    $pass2 = $_POST['password_confirm'] ?? '';
    $role = $_POST['role'] ?? 'user';

    if (empty($user) || empty($email) || empty($pass1) || $pass1 !== $pass2) {
        header('Location: /admin?error=Invalid+data+provided');
        exit;
    }
    
    if ($auth->register($user, $pass1, $email, $role)) {
        header('Location: /admin?success=User+created');
    } else {
        header('Location: /admin?error=User+already+exists');
    }
    exit;
});

$router->post('/admin/users/password', function() use ($auth) {
    if (!$auth->isSuperAdmin()) {
        header('Location: /');
        exit;
    }
    $user = trim($_POST['username'] ?? '');
    $pass = $_POST['new_password'] ?? '';

    if (empty($user) || empty($pass)) {
        header('Location: /admin?error=Invalid+data');
        exit;
    }
    
    if ($auth->updatePassword($user, $pass)) {
        header('Location: /admin?success=Password+updated');
    } else {
        header('Location: /admin?error=Update+failed');
    }
    exit;
});

$router->post('/admin/users/delete', function() use ($auth) {
    if (!$auth->isSuperAdmin()) {
        header('Location: /');
        exit;
    }
    $user = trim($_POST['username'] ?? '');
    
    if ($auth->deleteUser($user)) {
        // If they deleted themselves, logout
        if ($user === $auth->getUser()) {
            $auth->logout();
            header('Location: /login');
        } else {
            header('Location: /admin?success=User+deleted');
        }
    } else {
        header('Location: /admin?error=Cannot+delete+user');
    }
    exit;
});

// File Management
$router->get('/files', function() {
    (new \App\Core\FilesController())->index();
});
$router->get('/files/new', function() {
    (new \App\Core\FilesController())->create();
});
$router->get('/files/new', function() {
    (new \App\Core\FilesController())->create();
});
$router->get('/files/edit', function() {
    (new \App\Core\FilesController())->edit();
});
$router->post('/files/save', function() {
    (new \App\Core\FilesController())->save();
});
$router->post('/files/delete', function() {
    (new \App\Core\FilesController())->delete();
});

// Recurring Transactions
$router->get('/recurring', function() {
    $manager = new RecurringManager();
    render('recurring_list', [
        'title' => 'Cinghy - Automated',
        'recurring' => $manager->getAll()
    ]);
});

$router->get('/recurring/add', function() {
    $ctx = UserContext::get();
    $settings = $ctx->getSettings();
    $scope = $settings['autocomplete_scope'] ?? 'all';
    $year = ($scope === 'current_year') ? date('Y') : null;
    $agg = new Aggregator();
    $autoData = $agg->getAutocompleteData($year);
    
    render('recurring_form', [
        'title' => 'Cinghy - Add Automated',
        'extra_css' => ['forms'],
        'extra_js' => ['autocomplete', 'transaction-form'],
        'autoData' => $autoData,
        'settings' => $settings
    ]);
});

$router->post('/recurring/add', function() {
    $manager = new RecurringManager();
    $accounts = $_POST['accounts'] ?? [];
    $amounts = $_POST['amounts'] ?? [];
    $postings = [];
    
    $settings = UserContext::get()->getSettings();
    $total = 0.0;
    $missingIndex = -1;
    
    foreach ($accounts as $i => $account) {
        $account = trim($account);
        if (empty($account)) continue;
        
        $amtRaw = trim($amounts[$i] ?? '');
        if ($amtRaw === '') {
            if ($missingIndex === -1) {
                $missingIndex = count($postings);
            } else {
                $missingIndex = -2;
            }
            $postings[] = ['account' => $account, 'amount' => 0.0];
        } else {
            $dec = $settings['decimal_sep'] ?? '.';
            $thousands = $settings['thousands_sep'] ?? '';
            $symbol = $settings['currency_symbol'] ?? 'EUR';
            
            $clean = str_replace([$symbol, $thousands, ' ', "\xc2\xa0"], '', $amtRaw);
            $clean = str_replace($dec, '.', $clean);
            $val = (float)$clean;
            $total += $val;
            
            $postings[] = ['account' => $account, 'amount' => $val, 'currency' => $symbol];
        }
    }
    
    if ($missingIndex >= 0) {
        $postings[$missingIndex]['amount'] = -$total;
        $postings[$missingIndex]['currency'] = $settings['currency_symbol'] ?? 'EUR';
    }
    
    $data = [
        'payee' => $_POST['payee'] ?? '',
        'description' => $_POST['description'] ?? '',
        'status' => $_POST['status'] ?? '',
        'frequency' => $_POST['frequency'] ?? 'monthly',
        'interval' => (int)($_POST['interval'] ?? 1),
        'next_run_date' => $_POST['next_run_date'] ?? date('Y-m-d'),
        'postings' => $postings
    ];
    
    $manager->save($data);
    header('Location: /recurring');
    exit;
});

$router->get('/recurring/edit', function() {
    $id = $_GET['id'] ?? '';
    $manager = new RecurringManager();
    $recurring = $manager->getById($id);
    
    if (!$recurring) {
        header('Location: /recurring');
        exit;
    }
    
    $ctx = UserContext::get();
    $settings = $ctx->getSettings();
    $scope = $settings['autocomplete_scope'] ?? 'all';
    $year = ($scope === 'current_year') ? date('Y') : null;
    $agg = new Aggregator();
    $autoData = $agg->getAutocompleteData($year);
    
    render('recurring_form', [
        'title' => 'Cinghy - Edit Automated',
        'extra_css' => ['forms'],
        'extra_js' => ['autocomplete', 'transaction-form'],
        'autoData' => $autoData,
        'settings' => $settings,
        'recurring' => $recurring,
        'isEdit' => true
    ]);
});

$router->post('/recurring/edit', function() {
    $manager = new RecurringManager();
    $id = $_POST['id'] ?? '';
    if (!$id) {
        header('Location: /recurring');
        exit;
    }
    
    $accounts = $_POST['accounts'] ?? [];
    $amounts = $_POST['amounts'] ?? [];
    $postings = [];
    
    $settings = UserContext::get()->getSettings();
    $total = 0.0;
    $missingIndex = -1;
    
    foreach ($accounts as $i => $account) {
        $account = trim($account);
        if (empty($account)) continue;
        
        $amtRaw = trim($amounts[$i] ?? '');
        if ($amtRaw === '') {
            if ($missingIndex === -1) {
                $missingIndex = count($postings);
            } else {
                $missingIndex = -2;
            }
            $postings[] = ['account' => $account, 'amount' => 0.0];
        } else {
            $dec = $settings['decimal_sep'] ?? '.';
            $thousands = $settings['thousands_sep'] ?? '';
            $symbol = $settings['currency_symbol'] ?? 'EUR';
            
            $clean = str_replace([$symbol, $thousands, ' ', "\xc2\xa0"], '', $amtRaw);
            $clean = str_replace($dec, '.', $clean);
            $val = (float)$clean;
            $total += $val;
            
            $postings[] = ['account' => $account, 'amount' => $val, 'currency' => $symbol];
        }
    }
    
    if ($missingIndex >= 0) {
        $postings[$missingIndex]['amount'] = -$total;
        $postings[$missingIndex]['currency'] = $settings['currency_symbol'] ?? 'EUR';
    }
    
    $data = [
        'id' => $id,
        'payee' => $_POST['payee'] ?? '',
        'description' => $_POST['description'] ?? '',
        'status' => $_POST['status'] ?? '',
        'frequency' => $_POST['frequency'] ?? 'monthly',
        'interval' => (int)($_POST['interval'] ?? 1),
        'next_run_date' => $_POST['next_run_date'] ?? date('Y-m-d'),
        'postings' => $postings
    ];
    
    $manager->save($data);
    header('Location: /recurring');
    exit;
});

$router->post('/recurring/delete', function() {
    $id = $_POST['id'] ?? '';
    if ($id) {
        (new RecurringManager())->delete($id);
    }
    header('Location: /recurring');
    exit;
});

$router->dispatch();
\App\Core\GitHubSyncService::flushAndContinue();