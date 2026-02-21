<?php

namespace App\Core;

class Lang
{
    private static array $translations = [];
    private static string $locale = 'en';

    public static function init(string $locale)
    {
        self::$locale = $locale;
        $file = __DIR__ . '/../../../lang/' . $locale . '.php';
        
        $base = [];
        $fallback = __DIR__ . '/../../../lang/en.php';
        if (file_exists($fallback)) {
            $base = require $fallback;
        }

        if ($locale !== 'en' && file_exists($file)) {
            $langData = require $file;
            self::$translations = array_merge($base, $langData);
        } else {
            self::$translations = $base;
        }
    }

    public static function get(string $key, array $replace = []): string
    {
        $line = self::$translations[$key] ?? $key;

        if (empty($replace)) {
            return $line;
        }

        foreach ($replace as $k => $v) {
            $line = str_replace(':' . $k, $v, $line);
        }

        return $line;
    }
}
