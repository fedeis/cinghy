<?php

declare(strict_types=1);

namespace App\Core;

class FilesController
{
    public function index(): void
    {
        $ctx = UserContext::get();
        $dataPath = $ctx->getDataPath();
        $files = glob($dataPath . '/*.journal');
        
        $fileList = [];
        foreach ($files as $file) {
            $fileList[] = [
                'name' => basename($file),
                'path' => $file,
                'size' => filesize($file),
                'modified' => filemtime($file)
            ];
        }

        // Sort by modified time descending
        usort($fileList, fn($a, $b) => $b['modified'] <=> $a['modified']);

        render('files', [
            'title' => 'Cinghy - Files',
            'files' => $fileList
        ]);
    }

    public function create(): void
    {
        render('edit_file', [
            'title' => 'Cinghy - New Journal',
            'filename' => '',
            'content' => "; New Journal\n\n"
        ]);
    }

    public function edit(): void
    {
        $filename = $_GET['file'] ?? '';
        if (empty($filename)) {
            header('Location: /files');
            exit;
        }

        $ctx = UserContext::get();
        $dataPath = $ctx->getDataPath();
        $filePath = realpath($dataPath . '/' . basename($filename));

        if (!$filePath || !str_starts_with($filePath, realpath($dataPath))) {
            http_response_code(403);
            echo "Access denied.";
            exit;
        }

        if (!file_exists($filePath)) {
            http_response_code(404);
            echo "File not found.";
            exit;
        }

        $content = file_get_contents($filePath);

        render('edit_file', [
            'title' => 'Cinghy - Editing ' . $filename,
            'filename' => $filename,
            'content' => $content
        ]);
    }

    public function save(): void
    {
        $filename = $_POST['filename'] ?? '';
        $content = $_POST['content'] ?? '';

        if (empty($filename)) {
            header('Location: /files');
            exit;
        }

        $ctx = UserContext::get();
        $dataPath = realpath($ctx->getDataPath());
        $filePath = $dataPath . '/' . basename($filename);

        // Security check: ensure path is within data directory
        if (strpos(realpath(dirname($filePath)), $dataPath) !== 0) {
            http_response_code(403);
            echo "Access denied.";
            exit;
        }

        file_put_contents($filePath, $content);
        (new \App\Core\GitHubSyncService())->syncFile(basename($filename), $content, "Updated {$filename} via File Manager");

        // Clear cache for this file if necessary
        $cache = new \App\Cache\CacheManager();
        $cache->invalidateFile(basename($filename, '.journal'));

        header('Location: /files');
        exit;
    }

    public function delete(): void
    {
        $filename = $_POST['filename'] ?? '';
        if (empty($filename)) {
            header('Location: /files');
            exit;
        }

        $ctx = UserContext::get();
        $dataPath = realpath($ctx->getDataPath());
        $filePath = realpath($dataPath . '/' . basename($filename));

        if (!$filePath || strpos($filePath, $dataPath) !== 0) {
            http_response_code(403);
            echo "Access denied.";
            exit;
        }

        if (file_exists($filePath)) {
            unlink($filePath);
            $cache = new \App\Cache\CacheManager();
            $cache->invalidateFile(basename($filename, '.journal'));
        }

        header('Location: /files');
        exit;
    }
}
