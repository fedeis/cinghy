<?php
require __DIR__ . '/../src/bootstrap.php';

use App\Core\UserContext;
use App\Cache\CacheManager;

session_start();

// Inizializza il contesto — cambia 'federico' con il tuo username reale
UserContext::create('federico');

// Invalida la cache per forzare re-parsing con il nuovo JournalParser
$cache = new CacheManager();
$cache->clearAll();

// Rileggi le transazioni
$txs2026 = $cache->getFileData('2026');

$intesaTotal = 0.0;
$rows = [];

foreach ($txs2026 as $tx) {
    foreach ($tx['postings'] as $p) {
        if ($p['account'] === 'Assets:Bank:Intesa') {
            $intesaTotal += $p['amount'];
            $rows[] = [
                'date'    => $tx['date'],
                'payee'   => $tx['payee'],
                'amount'  => $p['amount'],
                'running' => $intesaTotal,
            ];
        }
    }
}

echo '<pre>';
printf("%-12s %-35s %10s %12s\n", 'Data', 'Payee', 'Importo', 'Saldo corr.');
echo str_repeat('-', 75) . "\n";
foreach ($rows as $r) {
    printf("%-12s %-35s %10.2f %12.2f\n",
        $r['date'],
        substr($r['payee'], 0, 34),
        $r['amount'],
        $r['running']
    );
}
echo "\nTOTALE CINGHY: " . number_format($intesaTotal, 2, '.', '') . "\n";
echo '</pre>';

// Dopo il getFileData, calcola il totale grezzo senza il widget
$intesaRaw = 0.0;
foreach ($txs2026 as $tx) {
    foreach ($tx['postings'] as $p) {
        if ($p['account'] === 'Assets:Bank:Intesa') {
            $intesaRaw += $p['amount'];
        }
    }
}
echo "Totale raw da transazioni: " . number_format($intesaRaw, 2) . "\n";

// Poi simula quello che fa il widget
use App\Accounting\Aggregator;
use App\Accounting\AccountTreeBuilder;

UserContext::create('federico');
$agg = new Aggregator();
$balances = $agg->getRangeBalances('2000-01-01', '2111-12-31');

$intesaWidget = $balances['balances']['Assets:Bank:Intesa']['€'] ?? 0.0;
echo "Totale dal widget (Aggregator): " . number_format($intesaWidget, 2) . "\n";

$allFiles = glob(UserContext::get()->getDataPath('*.journal'));
$journalNames = array_map(fn($f) => basename($f, '.journal'), $allFiles);

echo '<pre>';
foreach ($journalNames as $name) {
    $txs = $cache->getFileData($name);
    $tot = 0.0;
    foreach ($txs as $tx) {
        foreach ($tx['postings'] as $p) {
            if ($p['account'] === 'Assets:Bank:Intesa') {
                $tot += $p['amount'];
            }
        }
    }
    if ($tot != 0) echo "$name => " . number_format($tot, 2) . "\n";
}
echo '</pre>';