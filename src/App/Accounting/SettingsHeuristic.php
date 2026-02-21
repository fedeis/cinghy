<?php

namespace App\Accounting;

use App\Core\UserContext;
use App\Cache\CacheManager;

class SettingsHeuristic
{
    public static function detect(string $dataPath): array
    {
        $files = glob($dataPath . '/*.journal');
        $sampleText = "";
        $txSample = [];
        foreach ($files as $file) {
            $content = file_get_contents($file);
            // Focus on indented lines to avoid headers matching as currency/amount
            if (preg_match_all('/^\s+.*?\s{2,}([-+]?[\d.,]+)\s*([^\d\s,.-]{1,3})|^\s+.*?\s+([^\d\s,.-]{1,3})\s*([-+]?[\d.,]+)/m', $content, $matches, PREG_SET_ORDER)) {
                $txSample = array_merge($txSample, array_slice($matches, 0, 100));
            }
            $sampleText .= substr($content, 0, 4000);
            if (count($txSample) > 500) break;
        }

        $settings = [
            'syntax' => 'hledger',
            'use_pending' => true,
            'decimal_sep' => '.',
            'thousands_sep' => '',
            'currency_symbol' => 'EUR',
            'currency_position' => 'after',
            'currency_spacing' => false,
            'journal_width' => 44,
            'indent_spaces' => 4,
        ];

        if (empty($sampleText)) return $settings;

        // 1. Detect Syntax
        if (strpos($sampleText, 'option "') !== false || preg_match('/^\d{4}-\d{2}-\d{2}\s+"/', $sampleText)) {
            $settings['syntax'] = 'beancount';
        }

        // 2. Detect Pending
        if (strpos($sampleText, ' * ') === false && strpos($sampleText, ' ! ') === false) {
            $settings['use_pending'] = false;
        }

        // 3. Analyze Posting Samples for Currency & Separators
        $symbols = [];
        $positions = [];
        $spacings = [];
        $seps = [];
        $indentations = [];
        $widths = [];

        foreach ($txSample as $m) {
            $rawLine = $m[0];
            
            // Detect indentation
            if (preg_match('/^(\s+)/', $rawLine, $indentMatches)) {
                $indentations[strlen($indentMatches[1])] = ($indentations[strlen($indentMatches[1])] ?? 0) + 1;
            }

            $line = trim($rawLine);
            $parts = preg_split('/\s{2,}/', $line, 2);
            if (count($parts) < 2) continue;

            $rest = trim($parts[1]);
            
            // Re-detect amount and symbol from $rest
            if (!preg_match('/([-+]?[\d.,]+)/', $rest, $amtMatches)) continue;
            
            $amt = $amtMatches[1];
            $sym = trim(str_replace($amt, '', $rest));
            if (empty($sym)) continue;

            $isPrefix = (strpos($rest, $sym) === 0);
            
            // Estimate Width: text length minus currency symbol
            $lineWithoutCurrency = str_replace($sym, '', $rawLine);
            $widths[] = strlen(rtrim($lineWithoutCurrency));

            $symbols[$sym] = ($symbols[$sym] ?? 0) + 1;
            $positions[$isPrefix ? 'before' : 'after'] = ($positions[$isPrefix ? 'before' : 'after'] ?? 0) + 1;
            
            $hasSpace = (strpos($rest, ' ') !== false);
            $spacings[$hasSpace ? 1 : 0] = ($spacings[$hasSpace ? 1 : 0] ?? 0) + 1;

            // Detect separators from amount
            if (preg_match('/(\d+)\.([\d]{3}),([\d]{2})/', $amt)) {
                $seps['.,'] = ($seps['.,'] ?? 0) + 1;
            } elseif (preg_match('/(\d+),([\d]{3})\.([\d]{2})/', $amt)) {
                $seps[',.'] = ($seps[',.'] ?? 0) + 1;
            } elseif (preg_match('/,([\d]{2})$/', $amt)) {
                $seps['none,'] = ($seps['none,'] ?? 0) + 1;
            } elseif (preg_match('/\.([\d]{2})$/', $amt)) {
                $seps['none.'] = ($seps['none.'] ?? 0) + 1;
            }
        }

        if (!empty($symbols)) {
            arsort($symbols);
            $settings['currency_symbol'] = key($symbols);
        }
        if (!empty($positions)) {
            arsort($positions);
            $settings['currency_position'] = key($positions);
        }
        if (!empty($spacings)) {
            arsort($spacings);
            $settings['currency_spacing'] = (bool)key($spacings);
        }

        if (!empty($seps)) {
            arsort($seps);
            $bestSep = key($seps);
            if ($bestSep === '.,') {
                $settings['decimal_sep'] = ',';
                $settings['thousands_sep'] = '.';
            } elseif ($bestSep === ',.') {
                $settings['decimal_sep'] = '.';
                $settings['thousands_sep'] = ',';
            } elseif ($bestSep === 'none,') {
                $settings['decimal_sep'] = ',';
                $settings['thousands_sep'] = '';
            } elseif ($bestSep === 'none.') {
                $settings['decimal_sep'] = '.';
                $settings['thousands_sep'] = '';
            }
        }

        if (!empty($indentations)) {
            arsort($indentations);
            $settings['indent_spaces'] = (int)key($indentations);
        }
        if (!empty($widths)) {
            $settings['journal_width'] = (int)(array_sum($widths) / count($widths));
            // Round to nearest 5 for cleaner defaults
            $settings['journal_width'] = round($settings['journal_width'] / 5) * 5;
            if ($settings['journal_width'] < 30) $settings['journal_width'] = 50; 
        }

        return $settings;
    }
}
