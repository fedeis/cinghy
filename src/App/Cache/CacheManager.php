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
        $this->parser = new JournalParser();
    }

    public function getFileData(string $name): array
    {
        $dataFile = $this->context->getDataPath($name . '.journal');
        $cacheFile = $this->context->getCachePath($name . '.php');

        // 1. Check if Data exists
        if (!file_exists($dataFile)) {
            // No data, empty result
            return [];
        }

        // 2. Check if Cache is valid
        if (file_exists($cacheFile)) {
            $dataTime = filemtime($dataFile);
            $cacheTime = filemtime($cacheFile);

            if ($cacheTime >= $dataTime) {
                // Cache Hit
                return require $cacheFile;
            }
        }

        // 3. Cache Miss or Stale -> Parse
        $data = $this->parser->parse($dataFile);

        // 4. Write Cache
        $content = "<?php\n\nreturn " . var_export($data, true) . ";\n";
        file_put_contents($cacheFile, $content);

        // Invalidate Aggregates whenever a year is re-parsed (simplification)
        $this->invalidateAggregates();

        return $data;
    }

    public function invalidateFile(string $name): void
    {
        $cacheFile = $this->context->getCachePath($name . '.php');
        if (file_exists($cacheFile)) {
            unlink($cacheFile);
        }
        $this->invalidateAggregates();
    }

    public function invalidateAggregates(): void
    {
        $aggFile = $this->context->getCachePath('aggregates.php');
        if (file_exists($aggFile)) {
            unlink($aggFile);
        }
    }

    public function clearAll(): void
    {
        $cachePath = $this->context->getCachePath();
        $files = glob($cachePath . '/*.php');
        foreach ($files as $file) {
            if (is_file($file) && basename($file) !== 'settings.php') {
                unlink($file);
            }
        }
    }
}
