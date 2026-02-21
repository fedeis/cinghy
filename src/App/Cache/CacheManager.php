<?php

namespace App\Cache;

use App\Core\UserContext;
use App\Parser\JournalParser;

class CacheManager
{
    private UserContext $context;
    private JournalParser $parser;

    public function __construct()
    {
        $this->context = UserContext::get();
        $this->parser  = new JournalParser();
    }

    public function getFileData(string $name): array
    {
        $dataFile  = $this->context->getDataPath($name . '.journal');
        $cacheFile = $this->context->getCachePath($name . '.json'); // JSON, non PHP

        // 1. Nessun journal, nessun dato
        if (!file_exists($dataFile)) {
            return [];
        }

        // 2. Cache valida?
        if (file_exists($cacheFile)) {
            $dataTime  = filemtime($dataFile);
            $cacheTime = filemtime($cacheFile);

            if ($cacheTime >= $dataTime) {
                // Cache hit
                $decoded = json_decode(file_get_contents($cacheFile), true);
                return is_array($decoded) ? $decoded : [];
            }
        }

        // 3. Cache miss o scaduta → parsing
        $data = $this->parser->parse($dataFile);

        // 4. Scrivi cache come JSON (non eseguibile, nessun rischio code injection)
        file_put_contents($cacheFile, json_encode($data));

        // Invalida aggregati ogni volta che un file viene ri-parsato
        $this->invalidateAggregates();

        return $data;
    }

    public function invalidateFile(string $name): void
    {
        $cacheFile = $this->context->getCachePath($name . '.json');
        if (file_exists($cacheFile)) {
            unlink($cacheFile);
        }
        // Compatibilità: rimuovi anche eventuale cache .php legacy
        $legacyCache = $this->context->getCachePath($name . '.php');
        if (file_exists($legacyCache)) {
            unlink($legacyCache);
        }
        $this->invalidateAggregates();
    }

    public function invalidateAggregates(): void
    {
        // Rimuovi sia la versione .php legacy che quella .json
        foreach (['aggregates.php', 'aggregates.json'] as $filename) {
            $file = $this->context->getCachePath($filename);
            if (file_exists($file)) {
                unlink($file);
            }
        }
    }

    public function clearAll(): void
    {
        $cachePath = $this->context->getCachePath();

        // Rimuovi tutti i .json di cache (escluso settings)
        foreach (glob($cachePath . '/*.json') as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        // Rimuovi anche eventuali .php legacy (escluso settings.php)
        foreach (glob($cachePath . '/*.php') as $file) {
            if (is_file($file) && basename($file) !== 'settings.php') {
                unlink($file);
            }
        }
    }
}
