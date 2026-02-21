<?php

namespace App\Core;

class GitHubSyncService
{
    private UserContext $context;

    public function __construct()
    {
        $this->context = UserContext::get();
    }

    /**
     * Accoda il sync da eseguire in background dopo che la risposta
     * è già stata inviata al browser (via flushAndContinue).
     *
     * I dati vengono salvati in sessione e processati da flushAndContinue(),
     * che va chiamata una volta sola alla fine del ciclo di richiesta
     * (tipicamente in index.php, dopo il dispatch del router).
     */
    public function syncFile(string $filename, string $content, string $message = ''): void
    {
        $settings = $this->context->getSettings();
        if (empty($settings['github_sync_enabled']) ||
            empty($settings['github_token']) ||
            empty($settings['github_repo'])) {
            return;
        }

        // Accoda il job in sessione — verrà processato dopo il flush
        $_SESSION['github_sync_queue'][] = [
            'filename' => $filename,
            'content'  => $content,
            'message'  => $message ?: "Auto save {$filename} via Cinghy",
            'settings' => [
                'token'  => $settings['github_token'],
                'repo'   => $settings['github_repo'],
                'branch' => $settings['github_branch'] ?? 'main',
            ],
        ];
    }

    /**
     * Da chiamare una volta sola alla fine di index.php, dopo il dispatch:
     *
     *   \App\Core\GitHubSyncService::flushAndContinue();
     *
     * Invia la risposta al browser e poi processa la coda in background.
     */
    public static function flushAndContinue(): void
    {
        $queue = $_SESSION['github_sync_queue'] ?? [];
        if (empty($queue)) {
            return;
        }

        // Svuota la coda prima del flush, così non viene ri-processata
        unset($_SESSION['github_sync_queue']);

        // Invia la risposta al browser e chiude la connessione HTTP
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        } else {
            // Fallback per ambienti non FastCGI (es. Apache mod_php)
            ob_end_flush();
            flush();
        }

        // Da qui in poi il browser ha già ricevuto la risposta,
        // ma PHP continua ad eseguire in background
        ignore_user_abort(true);
        set_time_limit(30);

        foreach ($queue as $job) {
            self::pushToGitHub(
                $job['filename'],
                $job['content'],
                $job['message'],
                $job['settings']
            );
        }
    }

    private static function pushToGitHub(
        string $filename,
        string $content,
        string $message,
        array  $settings
    ): void {
        $token  = $settings['token'];
        $repo   = $settings['repo'];
        $branch = $settings['branch'];

        $sha = self::fetchFileSha($repo, $filename, $branch, $token);

        $data = [
            'message' => $message,
            'content' => base64_encode($content),
            'branch'  => $branch,
        ];
        if ($sha) {
            $data['sha'] = $sha;
        }

        $url = "https://api.github.com/repos/{$repo}/contents/{$filename}";

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => 'PUT',
            CURLOPT_POSTFIELDS     => json_encode($data),
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $token,
                'User-Agent: Cinghy-App',
                'Accept: application/vnd.github.v3+json',
                'Content-Type: application/json',
            ],
        ]);
        curl_exec($ch);
    }

    private static function fetchFileSha(
        string $repo,
        string $filename,
        string $branch,
        string $token
    ): ?string {
        $url = "https://api.github.com/repos/{$repo}/contents/{$filename}?ref={$branch}";

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 5,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $token,
                'User-Agent: Cinghy-App',
                'Accept: application/vnd.github.v3+json',
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($httpCode === 200) {
            $data = json_decode($response, true);
            return $data['sha'] ?? null;
        }
        return null;
    }
}
