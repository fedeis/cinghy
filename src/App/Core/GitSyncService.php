<?php

namespace App\Core;

class GitSyncService
{
    private UserContext $context;

    public function __construct()
    {
        $this->context = UserContext::get();
    }

    /**
     * Queues a sync job to be executed in the background.
     */
    public function syncFile(string $filename, string $content, string $message = ''): void
    {
        $settings = $this->context->getSettings();
        if (empty($settings['git_sync_enabled'])) {
            return;
        }

        $service = $settings['git_service'] ?? 'github';
        $token = $settings['git_token'] ?? '';
        $repo = $settings['git_repo'] ?? '';
        $branch = $settings['git_branch'] ?? 'main';
        $baseUrl = $settings['git_base_url'] ?? 'https://codeberg.org';

        if (empty($token) || empty($repo)) {
            return;
        }

        $_SESSION['git_sync_queue'][] = [
            'filename' => $filename,
            'content'  => $content,
            'message'  => $message ?: "Auto save {$filename} via Cinghy",
            'settings' => [
                'service'  => $service,
                'token'    => $token,
                'repo'     => $repo,
                'branch'   => $branch,
                'base_url' => $baseUrl,
            ],
        ];
    }

    /**
     * Flushes the queue and executes sync jobs in the background.
     */
    public static function flushAndContinue(): void
    {
        $queue = $_SESSION['git_sync_queue'] ?? [];
        if (empty($queue)) {
            return;
        }

        unset($_SESSION['git_sync_queue']);

        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        } else {
            ob_end_flush();
            flush();
        }

        ignore_user_abort(true);
        set_time_limit(60);

        foreach ($queue as $job) {
            self::pushToGit(
                $job['filename'],
                $job['content'],
                $job['message'],
                $job['settings']
            );
        }
    }

    private static function pushToGit(
        string $filename,
        string $content,
        string $message,
        array  $settings
    ): void {
        $service = $settings['service'];
        $token   = $settings['token'];
        $repo    = $settings['repo'];
        $branch  = $settings['branch'];
        $baseUrl = $settings['base_url'];

        if ($service === 'github') {
            $apiUrl = "https://api.github.com/repos/{$repo}/contents/{$filename}";
            $headers = [
                'Authorization: Bearer ' . $token,
                'User-Agent: Cinghy-App',
                'Accept: application/vnd.github.v3+json',
                'Content-Type: application/json',
            ];
        } else {
            // Codeberg / Gitea API
            $base = rtrim($baseUrl ?: 'https://codeberg.org', '/') . '/api/v1';
            $apiUrl = "{$base}/repos/{$repo}/contents/{$filename}";
            $headers = [
                'Authorization: token ' . $token,
                'User-Agent: Cinghy-App',
                'Accept: application/json',
                'Content-Type: application/json',
            ];
        }

        $sha = self::fetchFileSha($apiUrl, $branch, $headers);

        $data = [
            'message' => $message,
            'content' => base64_encode($content),
            'branch'  => $branch,
        ];
        if ($sha) {
            $data['sha'] = $sha;
        }

        $ch = curl_init($apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => 'PUT',
            CURLOPT_POSTFIELDS     => json_encode($data),
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_HTTPHEADER     => $headers,
        ]);
        curl_exec($ch);
        curl_close($ch);
    }

    private static function fetchFileSha(
        string $apiUrl,
        string $branch,
        array  $headers
    ): ?string {
        $url = $apiUrl . "?ref=" . urlencode($branch);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_HTTPHEADER     => $headers,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            $data = json_decode($response, true);
            return $data['sha'] ?? null;
        }
        return null;
    }
}
