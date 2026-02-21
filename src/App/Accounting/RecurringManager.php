<?php

namespace App\Accounting;

use App\Core\UserContext;

class RecurringManager
{
    private UserContext $context;
    private string $filePath;
    
    public function __construct()
    {
        $this->context = UserContext::get();
        $this->filePath = $this->context->getDataPath('recurring.json');
    }
    
    public function getAll(): array
    {
        if (!file_exists($this->filePath)) {
            return [];
        }
        $data = json_decode(file_get_contents($this->filePath), true);
        
        // Sort by next_run_date ASC
        usort($data, fn($a, $b) => strcmp($a['next_run_date'], $b['next_run_date']));
        return $data;
    }
    
    public function getById(string $id): ?array
    {
        $all = $this->getAll();
        foreach ($all as $item) {
            if ($item['id'] === $id) return $item;
        }
        return null;
    }
    
    public function save(array $data): void
    {
        $all = $this->getAll();
        $isNew = !isset($data['id']) || empty($data['id']);
        
        if ($isNew) {
            $data['id'] = uniqid('rec_');
            $data['created_at'] = date('c');
            $all[] = $data;
        } else {
            foreach ($all as &$item) {
                if ($item['id'] === $data['id']) {
                    $data['created_at'] = $item['created_at'];
                    $item = $data;
                    break;
                }
            }
        }
        
        file_put_contents($this->filePath, json_encode($all, JSON_PRETTY_PRINT));
        (new \App\Core\GitHubSyncService())->syncFile(basename($this->filePath), file_get_contents($this->filePath), "Auto save recurring transactions");
    }
    
    public function delete(string $id): void
    {
        $all = $this->getAll();
        $all = array_filter($all, fn($item) => $item['id'] !== $id);
        file_put_contents($this->filePath, json_encode(array_values($all), JSON_PRETTY_PRINT));
        (new \App\Core\GitHubSyncService())->syncFile(basename($this->filePath), file_get_contents($this->filePath), "Auto delete recurring transaction");
    }
    
    public function processPending(): void
    {
        $all = $this->getAll();
        $now = date('Y-m-d');
        $changed = false;
        
        $writer = new TransactionWriter();
        
        foreach ($all as &$item) {
            while ($item['next_run_date'] <= $now) {
                // Generate transaction
                $txDate = $item['next_run_date'];
                
                $status = $item['status'] ?? '';
                $payee = $this->parsePlaceholders($item['payee'], $txDate);
                $desc = $this->parsePlaceholders($item['description'], $txDate);
                
                $tx = new Transaction($txDate, $payee, $desc, $status);
                
                foreach ($item['postings'] as $p) {
                    $tx->addPosting(new Posting($p['account'], (float)$p['amount'], $p['currency'] ?? 'EUR'));
                }
                
                $writer->write($tx);
                
                // Update dates
                $item['last_run_date'] = $txDate;
                $item['next_run_date'] = $this->calculateNextDate($txDate, $item['frequency'], (int)($item['interval'] ?? 1));
                $changed = true;
                
                // Safety break for infinite loops in case of malformed data
                if ($item['next_run_date'] <= $txDate) {
                    break;
                }
            }
        }
        
        if ($changed) {
            file_put_contents($this->filePath, json_encode($all, JSON_PRETTY_PRINT));
            (new \App\Core\GitHubSyncService())->syncFile(basename($this->filePath), file_get_contents($this->filePath), "Auto process recurring transactions");
        }
    }
    
    private function calculateNextDate(string $baseDate, string $frequency, int $interval): string
    {
        $time = strtotime($baseDate);
        switch ($frequency) {
            case 'daily':
                return date('Y-m-d', strtotime("+$interval days", $time));
            case 'weekly':
                return date('Y-m-d', strtotime("+$interval weeks", $time));
            case 'biweekly':
                return date('Y-m-d', strtotime("+2 weeks", $time));
            case 'monthly':
                return date('Y-m-d', strtotime("+$interval months", $time));
            case 'yearly':
                return date('Y-m-d', strtotime("+$interval years", $time));
            default:
                return date('Y-m-d', strtotime('+1 month', $time));
        }
    }
    
    private function parsePlaceholders(string $text, string $dateStr): string
    {
        if (empty($text)) return $text;
        
        $time = strtotime($dateStr);
        $monthKey = 'month_' . (int)date('m', $time);
        
        $replacements = [
            '{{year}}' => date('Y', $time),
            '{{month}}' => date('m', $time),
            '{{day}}' => date('d', $time),
            '{{month_name}}' => __($monthKey),
            '{{month_name_it}}' => __($monthKey), // legacy alias
        ];
        
        return strtr($text, $replacements);
    }
}
