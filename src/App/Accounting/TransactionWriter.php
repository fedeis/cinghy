<?php

namespace App\Accounting;

use App\Core\UserContext;
use App\Cache\CacheManager;

class TransactionWriter
{
    private UserContext $context;
    private CacheManager $cache;
    private string $defaultCurrency = 'EUR';

    public function __construct()
    {
        $this->context = UserContext::get();
        $this->cache = new CacheManager();
    }

    public function write(Transaction $transaction): void
    {
        // 1. Determine Year from Date
        $year = substr($transaction->date, 0, 4);
        $file = $this->context->getDataPath($year . '.journal');

        // 2. Format as String (Ledger format)
        // 2025-01-01 * Payee
        //     Assets:Bank    -10.00 EUR
        //     Expenses:Food   10.00 EUR
        
        $settings = $this->context->getSettings();
        
        $lines = [];
        $lines[] = ""; // Empty line separator
        
        $statusStr = ($settings['use_pending'] && $transaction->status) ? $transaction->status . " " : ($settings['use_pending'] ? "* " : "");
        $payee = $transaction->payee;
        $memo = $transaction->description;

        if ($settings['syntax'] === 'beancount') {
            $headerDesc = '"' . str_replace('"', '', $payee) . '"';
            if ($memo) {
                $headerDesc .= ' "' . str_replace('"', '', $memo) . '"';
            }
        } else {
            $headerDesc = $payee;
            if ($memo) {
                $headerDesc .= ' | ' . $memo;
            }
        }

        $lines[] = sprintf("%s %s%s", 
            $transaction->date, 
            $statusStr,
            $headerDesc
        );

        $indentWidth = (int)($settings['indent_spaces'] ?? 4);
        $journalWidth = (int)($settings['journal_width'] ?? 50);
        $indent = str_repeat(' ', $indentWidth);

        foreach ($transaction->postings as $posting) {
            $p = $posting instanceof Posting ? $posting : (object)$posting;
            
            // Format amount according to settings
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

            // Calculate spacing: Width - Indent - Account - Amount(no sym)
            $occupied = $indentWidth + strlen($p->account) + strlen($amtStrNoSym);
            $padding = max(2, $journalWidth - $occupied);
            
            $lines[] = $indent . $p->account . str_repeat(' ', $padding) . $amtStr;
        }

        $content = implode("\n", $lines) . "\r\n";

        // 3. Append to File
        file_put_contents($file, $content, FILE_APPEND | LOCK_EX);

        // Sync with GitHub if enabled
        $fullContent = file_get_contents($file);
        $syncService = new \App\Core\GitHubSyncService();
        $syncService->syncFile(basename($file), $fullContent, "Auto save transaction: " . $transaction->payee);

        // 4. Invalidate Cache
        $this->cache->invalidateFile($year);
        $this->cache->invalidateAggregates();
    }
}
