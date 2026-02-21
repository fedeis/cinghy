<?php

namespace App\Accounting;

use App\Cache\CacheManager;
use App\Core\UserContext;

class Aggregator
{
    private CacheManager $cache;
    private UserContext $context;

    public function __construct()
    {
        $this->cache = new CacheManager();
        $this->context = UserContext::get();
    }

    public function getAvailablePeriods(): array
    {
        $files = glob($this->context->getDataPath('*.journal'));
        $names = array_map(fn($f) => basename($f, '.journal'), $files);
        
        $years = [];
        $months = []; // year => [month => true]

        foreach ($names as $name) {
            $transactions = $this->cache->getFileData($name);
            foreach ($transactions as $tx) {
                if (!isset($tx['date'])) continue;
                $y = substr($tx['date'], 0, 4);
                $m = substr($tx['date'], 5, 2);
                if ($y && $m) {
                    $years[$y] = true;
                    $months[$y][$m] = true;
                }
            }
        }

        $years = array_keys($years);
        sort($years);
        
        $resultMonths = [];
        foreach ($months as $y => $ms) {
            $msKeys = array_keys($ms);
            sort($msKeys);
            $resultMonths[$y] = $msKeys;
        }

        // Fallback to current year if nothing found
        if (empty($years)) {
            $years = [date('Y')];
            $resultMonths[date('Y')] = array_map(fn($m) => str_pad((string)$m, 2, '0', STR_PAD_LEFT), range(1, 12));
        }

        return ['years' => $years, 'months' => $resultMonths];
    }

    public function getRangeBalances(string $start, string $end, ?string $cacheName = null): array
    {
        return $this->getPeriodicBalances([
            'total' => ['start' => $start, 'end' => $end]
        ], $cacheName)['total'];
    }

    /**
     * buckets: [ 'label' => ['start' => '...', 'end' => '...'], ... ]
     */
    public function getPeriodicBalances(array $buckets, ?string $cacheName = null): array
    {
        if ($cacheName) {
            $cacheFile = $this->context->getCachePath($cacheName);
            if (file_exists($cacheFile)) {
                return require $cacheFile;
            }
        }

        // 1. Determine Global Range to Scan
        $globalStart = '9999-12-31';
        $globalEnd = '0000-01-01';
        foreach ($buckets as $b) {
            if ($b['start'] < $globalStart) $globalStart = $b['start'];
            if ($b['end'] > $globalEnd) $globalEnd = $b['end'];
        }

        $startYear = (int)substr($globalStart, 0, 4);
        $endYear = (int)substr($globalEnd, 0, 4);
        
        $years = [];
        for ($y = $startYear; $y <= $endYear; $y++) {
            if (file_exists($this->context->getDataPath($y . '.journal'))) {
                $years[] = (string)$y;
            }
        }
        // Also find all journals that might match (closing files etc)
        $allFiles = glob($this->context->getDataPath('*.journal'));
        $journalNames = array_map(fn($f) => basename($f, '.journal'), $allFiles);

        $results = [];
        foreach ($buckets as $label => $b) {
            $results[$label] = [
                'balances' => [],
                'range' => $b,
            ];
        }

        foreach ($journalNames as $name) {
            $transactions = $this->cache->getFileData($name);

            foreach ($transactions as $tx) {
                // Check each bucket
                foreach ($buckets as $label => $b) {
                    if ($tx['date'] >= $b['start'] && $tx['date'] <= $b['end']) {
                        foreach ($tx['postings'] as $posting) {
                            $account = $posting['account'];
                            $amount = (float)$posting['amount'];
                            $currency = $posting['currency'];

                            if (!isset($results[$label]['balances'][$account][$currency])) {
                                $results[$label]['balances'][$account][$currency] = 0.0;
                            }
                            $results[$label]['balances'][$account][$currency] += $amount;
                        }
                    }
                }
            }
        }

        if ($cacheName) {
            $content = "<?php\n\nreturn " . var_export($results, true) . ";\n";
            file_put_contents($cacheFile, $content);
        }

        return $results;
    }

    public function getAllAccounts(): array
    {
        $data = $this->getAutocompleteData();
        return $data['accounts'];
    }

    public function getAutocompleteData(?string $specificYear = null): array
    {
        $allFiles = glob($this->context->getDataPath('*.journal'));
        $journalNames = array_map(fn($f) => basename($f, '.journal'), $allFiles);

        if ($specificYear) {
            $journalNames = array_filter($journalNames, fn($n) => $n === $specificYear);
        }

        $payees = [];
        $accounts = [];
        $correlations = [];

        foreach ($journalNames as $name) {
            $transactions = $this->cache->getFileData($name);
            foreach ($transactions as $tx) {
                $payee = $tx['payee'] ?? '';
                $memo = $tx['description'] ?? '';
                
                if ($payee !== '') {
                    $payees[$payee] = ($payees[$payee] ?? 0) + 1;
                    if (!isset($correlations[$payee])) {
                        $correlations[$payee] = ['accounts' => [], 'memos' => []];
                    }
                    if ($memo !== '') {
                        $correlations[$payee]['memos'][$memo] = ($correlations[$payee]['memos'][$memo] ?? 0) + 1;
                    }
                }

                foreach ($tx['postings'] as $posting) {
                    $acc = $posting['account'];
                    $accounts[$acc] = ($accounts[$acc] ?? 0) + 1;
                    if ($payee !== '') {
                        $correlations[$payee]['accounts'][$acc] = ($correlations[$payee]['accounts'][$acc] ?? 0) + 1;
                    }
                }
            }
        }

        // Helper to sort and extract keys
        $sortByFreq = function($dict) {
            arsort($dict);
            return array_keys($dict);
        };

        $result = [
            'payees' => $sortByFreq($payees),
            'accounts' => $sortByFreq($accounts),
            'correlations' => []
        ];

        foreach ($correlations as $payee => $data) {
            $result['correlations'][$payee] = [
                'accounts' => $sortByFreq($data['accounts']),
                'memos' => $sortByFreq($data['memos'])
            ];
        }

        return $result;
    }
}
