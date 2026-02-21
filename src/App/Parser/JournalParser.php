<?php

namespace App\Parser;

use App\Accounting\Transaction;
use App\Accounting\Posting;

class JournalParser
{
    public function parse(string $filePath): array
    {
        if (!file_exists($filePath)) {
            return [];
        }

        $transactions = [];
        $handle = fopen($filePath, "r");
        
        $currentTx = null;

        while (($line = fgets($handle)) !== false) {
            $line = rtrim($line);
            
            // Skip empty lines and comments
            if (empty($line) || strpos($line, ';') === 0 || strpos($line, '#') === 0) {
                continue;
            }

            // Transaction Header Detection (Date at start)
            if (preg_match('/^(\d{4}[-\/]\d{2}[-\/]\d{2})\s*(.*)$/', $line, $matches)) {
                if ($currentTx) {
                    $this->balanceTransaction($currentTx);
                    $transactions[] = $currentTx;
                }

                $date = str_replace('/', '-', $matches[1]);
                $rest = trim($matches[2]);
                
                $ctx = \App\Core\UserContext::get();
                $settings = $ctx->getSettings();

                $status = '';
                $description = $rest;

                if ($settings['use_pending']) {
                    if (preg_match('/^([*!])\s*(.*)$/', $rest, $statusMatches)) {
                        $status = $statusMatches[1];
                        $description = $statusMatches[2];
                    }
                }

                $payee = $description;
                $memo = '';

                if ($settings['syntax'] === 'beancount') {
                    // Beancount: "Payee" "Narrative"
                    if (preg_match('/^"([^"]+)"\s+"([^"]+)"$/', $description, $descMatches)) {
                        $payee = $descMatches[1];
                        $memo = $descMatches[2];
                    } elseif (preg_match('/^"([^"]+)"\s*(.*)$/', $description, $descMatches)) {
                        $payee = $descMatches[1];
                        $memo = trim($descMatches[2], ' "');
                    }
                } else {
                    // hledger: Payee | Memo
                    if (strpos($description, ' | ') !== false) {
                        list($payee, $memo) = explode(' | ', $description, 2);
                    }
                }

                $currentTx = new Transaction($date, trim($payee), trim($memo), $status);
                continue;
            }

            // Posting Detection (Indented line)
            if ($currentTx && (strpos($line, ' ') === 0 || strpos($line, "\t") === 0)) {
                $trimmed = trim($line);
                
                // Strip inline comments (everything after  ;)
                if (($commentPos = strpos($trimmed, '  ;')) !== false) {
                    $trimmed = rtrim(substr($trimmed, 0, $commentPos));
                }

                // 1. Split by 2 or more spaces (Standard for ledger/hledger/beancount)
                $parts = preg_split('/\s{2,}/', $trimmed, 2);
                
                if (count($parts) === 2) {
                    $account = trim($parts[0]);
                    $rest = trim($parts[1]);
                    
                    // 2. Parse Amount and Currency from $rest
                    // Handle cases like: -60,88€, €-60,88, € -60,88, -60,88 €
                    $amountStr = '0.0';
                    $currency = 'EUR';

                    if (preg_match('/([-+]?\s*[\d.,]+)/', $rest, $amtMatches)) {
                        $amountStr = str_replace(' ', '', $amtMatches[1]);
                        // Currency is what remains after removing amount and spaces
                        $currency = trim(str_replace($amtMatches[1], '', $rest));
                        if (empty($currency)) $currency = 'EUR';
                    }
                    
                    // Normalize amount: 1.234,56 → 1234.56  /  1,234.56 → 1234.56  /  1,56 → 1.56
                    if (strpos($amountStr, ',') !== false && strpos($amountStr, '.') === false) {
                        // Only comma → decimal separator (e.g. 60,88)
                        $amountStr = str_replace(',', '.', $amountStr);
                    } elseif (strpos($amountStr, ',') !== false && strpos($amountStr, '.') !== false) {
                        if (strrpos($amountStr, ',') > strrpos($amountStr, '.')) {
                            // Comma is last → decimal separator (e.g. 1.234,56)
                            $amountStr = str_replace('.', '', $amountStr);
                            $amountStr = str_replace(',', '.', $amountStr);
                        } else {
                            // Dot is last → decimal separator (e.g. 1,234.56)
                            $amountStr = str_replace(',', '', $amountStr);
                        }
                    }
                    
                    $amount = (float)$amountStr;
                    $currentTx->addPosting(new Posting($account, $amount, $currency));

                } elseif (count($parts) === 1 && $parts[0] !== '') {
                    // Posting with no amount → will be auto-balanced later
                    $account = trim($parts[0]);
                    $currentTx->addPosting(new Posting($account, null, null));

                } else {
                    // Fallback for lines with only one space (less standard, but handle simple cases)
                    if (preg_match('/^(.*?)\s+([-+]?[\d.,]+)\s*(\S+)?$/', $trimmed, $matches)) {
                        $account = trim($matches[1]);
                        $amount = (float)str_replace(',', '.', str_replace('.', '', $matches[2]));
                        $currency = $matches[3] ?? 'EUR';
                        $currentTx->addPosting(new Posting($account, $amount, $currency));
                    } else {
                        // Unknown format — skip silently
                    }
                }
            }
        }

        if ($currentTx) {
            $this->balanceTransaction($currentTx);
            $transactions[] = $currentTx;
        }

        fclose($handle);
        
        // Convert objects to arrays for caching
        return array_map(fn($t) => $t->toArray(), $transactions);
    }

    /**
     * If exactly one posting has a null amount, assign it the value
     * that makes the transaction sum to zero, mirroring hledger behaviour.
     * If more than one posting has no amount, the transaction is malformed
     * and we leave it untouched (amounts stay null / will cast to 0).
     */
    private function balanceTransaction(Transaction $tx): void
    {
        $postings = $tx->getPostings();

        $nullIndexes = [];
        $total       = 0.0;
        $mainCurrency = 'EUR';

        foreach ($postings as $i => $posting) {
            if ($posting->amount === null) {
                $nullIndexes[] = $i;
            } else {
                $total += $posting->amount;
                if ($posting->currency !== null) {
                    $mainCurrency = $posting->currency;
                }
            }
        }

        if (count($nullIndexes) === 1) {
            $idx = $nullIndexes[0];
            $postings[$idx] = new Posting(
                $postings[$idx]->account,
                round(-$total, 10),   // negate to balance; round to avoid float drift
                $mainCurrency
            );
            $tx->setPostings($postings);
        }
        // If 0 or 2+ null postings: leave as-is
    }
}
