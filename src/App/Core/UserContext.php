<?php

namespace App\Core;

class UserContext
{
    private static ?UserContext $instance = null;
    private string $username;
    private string $baseDataPath;
    private string $baseCachePath;

    private function __construct(string $username)
    {
        $this->username = $username;
        // New Structure: /users/{username}/data and /users/{username}/cache
        $this->baseDataPath = __DIR__ . '/../../../users/' . $username . '/data';
        $this->baseCachePath = __DIR__ . '/../../../users/' . $username . '/cache';

        // Ensure directories exist
        if (!is_dir($this->baseDataPath)) {
            mkdir($this->baseDataPath, 0755, true);
        }
        if (!is_dir($this->baseCachePath)) {
            mkdir($this->baseCachePath, 0755, true);
        }
    }

    public static function create(string $username): self
    {
        if (self::$instance === null) {
            self::$instance = new self($username);
        }
        return self::$instance;
    }

    public static function get(): self
    {
        if (self::$instance === null) {
            throw new \RuntimeException("UserContext not initialized.");
        }
        return self::$instance;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function getDataPath(string $filename = ''): string
    {
        return $this->baseDataPath . ($filename ? '/' . $filename : '');
    }

    public function getCachePath(string $filename = ''): string
    {
        return $this->baseCachePath . ($filename ? '/' . $filename : '');
    }

    public function getSettings(): array
    {
        $settingsFile = $this->baseCachePath . '/settings.php';
        $settings = [];
        if (file_exists($settingsFile)) {
            $settings = require $settingsFile;
        } else {
            // No settings, run heuristics
            $settings = \App\Accounting\SettingsHeuristic::detect($this->baseDataPath);
        }

        // Apply defaults for new UI settings
        $settings['accent_color'] = $settings['accent_color'] ?? '#32e68f';
        $settings['theme'] = $settings['theme'] ?? 'system';

        return $settings;
    }

    public function saveSettings(array $settings): void
    {
        $settingsFile = $this->baseCachePath . '/settings.php';
        $content = "<?php\n\nreturn " . var_export($settings, true) . ";\n";
        file_put_contents($settingsFile, $content);
        
        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($settingsFile, true);
        }
        
        // Invalidate all caches when settings change
        $cache = new \App\Cache\CacheManager();
        $cache->clearAll();
    }
}
