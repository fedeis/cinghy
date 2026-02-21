<?php

namespace App\Core;

class GitHubSyncService
{
    private UserContext $context;

    public function __construct()
    {
        $this->context = UserContext::get();
    }

    public function syncFile(string $filename, string $content, string $message = ''): void
    {
        $settings = $this->context->getSettings();
        if (empty($settings['github_sync_enabled']) || empty($settings['github_token']) || empty($settings['github_repo'])) {
            return;
        }

        $token = escapeshellarg($settings['github_token']);
        $repo = escapeshellcmd($settings['github_repo']);
        $branch = escapeshellcmd($settings['github_branch'] ?? 'main');
        $message = escapeshellcmd($message ?: "Auto save {$filename} via Cinghy");
        $filename = escapeshellcmd($filename);

        $url = "https://api.github.com/repos/{$repo}/contents/{$filename}";
        
        // Timeout curl quickly to get SHA if it exists, so we don't totally stall
        $sha = $this->getFileSha($repo, $filename, $branch, $settings['github_token']);
        
        $data = [
            'message' => $message,
            'content' => base64_encode($content),
            'branch' => $branch
        ];
        
        if ($sha) {
            $data['sha'] = $sha;
        }

        $jsonPayload = escapeshellarg(json_encode($data));
        
        // Execute the PUT request via shell cURL and send it to the background
        $command = "curl -s -o /dev/null -w \"%{http_code}\" -X PUT -H \"Authorization: Bearer \"$token -H \"User-Agent: Cinghy-App\" -H \"Accept: application/vnd.github.v3+json\" -H \"Content-Type: application/json\" -d $jsonPayload \"$url\" > /dev/null 2>&1 &";
        
        exec($command);
    }

    private function getFileSha(string $repo, string $filename, string $branch, string $token): ?string
    {
        $url = "https://api.github.com/repos/{$repo}/contents/{$filename}?ref={$branch}";
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 3); // 3 seconds timeout
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $token,
            'User-Agent: Cinghy-App',
            'Accept: application/vnd.github.v3+json'
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
