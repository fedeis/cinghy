<?php

namespace App\Core;

class AuthService
{
    private string $usersFile;
    private string $tokensFile;
    private array $users  = [];
    private array $tokens = [];

    private const MAX_ATTEMPTS      = 10;
    private const LOCKOUT_SECONDS   = 900;
    private const REMEMBER_DAYS     = 90;
    private const COOKIE_NAME       = 'cinghy_remember';

    public function __construct()
    {
        $this->usersFile  = __DIR__ . '/../../../config/users.json';
        $this->tokensFile = __DIR__ . '/../../../config/remember_tokens.json';
        $this->loadUsers();
        $this->loadTokens();
    }

    private function loadUsers(): void
    {
        if (file_exists($this->usersFile)) {
            $this->users = json_decode(file_get_contents($this->usersFile), true) ?? [];
        }
    }

    private function saveUsers(): void
    {
        file_put_contents($this->usersFile, json_encode($this->users, JSON_PRETTY_PRINT));
    }

    private function loadTokens(): void
    {
        if (file_exists($this->tokensFile)) {
            $this->tokens = json_decode(file_get_contents($this->tokensFile), true) ?? [];
        }
    }

    private function saveTokens(): void
    {
        file_put_contents($this->tokensFile, json_encode($this->tokens, JSON_PRETTY_PRINT));
    }

    public function hasUsers(): bool
    {
        return count($this->users) > 0;
    }

    public function register(string $username, string $password, string $email, string $role = 'user'): bool
    {
        if (isset($this->users[$username])) {
            return false;
        }
        $this->users[$username] = [
            'password' => password_hash($password, PASSWORD_BCRYPT),
            'email'    => $email,
            'role'     => $role,
        ];
        $this->saveUsers();
        return true;
    }

    /**
     * Login con username e password.
     * Se $remember = true, imposta un cookie remember-me a 30 giorni.
     */
    public function login(string $username, string $password, bool $remember = false): bool
    {
        $attempts    = $_SESSION['login_attempts']    ?? 0;
        $lastAttempt = $_SESSION['login_last_attempt'] ?? 0;

        if (time() - $lastAttempt > self::LOCKOUT_SECONDS) {
            $attempts = 0;
        }
        if ($attempts >= self::MAX_ATTEMPTS) {
            return false;
        }

        if (!isset($this->users[$username]) ||
            !password_verify($password, $this->users[$username]['password'])) {
            $_SESSION['login_attempts']    = $attempts + 1;
            $_SESSION['login_last_attempt'] = time();
            return false;
        }

        $_SESSION['login_attempts']    = 0;
        $_SESSION['login_last_attempt'] = 0;
        $_SESSION['user']              = $username;

        if ($remember) {
            $this->setRememberCookie($username);
        }

        return true;
    }

    /**
     * Controlla il cookie remember-me e, se valido, fa il login automatico.
     * Da chiamare all'inizio di ogni richiesta prima del check isLoggedIn().
     */
    public function tryRememberLogin(): bool
    {
        if ($this->isLoggedIn()) {
            return false;
        }

        $cookie = $_COOKIE[self::COOKIE_NAME] ?? '';
        if (empty($cookie)) {
            return false;
        }

        $parts = explode(':', $cookie, 2);
        if (count($parts) !== 2) {
            $this->clearRememberCookie();
            return false;
        }

        [$username, $token] = $parts;

        $this->purgeExpiredTokens();

        if (!isset($this->users[$username])) {
            $this->clearRememberCookie();
            return false;
        }

        $tokenHash = hash('sha256', $token);
        $found     = false;

        foreach ($this->tokens as $i => $entry) {
            if ($entry['username'] === $username &&
                hash_equals($entry['token_hash'], $tokenHash) &&
                $entry['expires'] > time()) {
                $found = true;
                // Token rotation: ogni uso genera un nuovo token
                unset($this->tokens[$i]);
                $this->tokens = array_values($this->tokens);
                $this->saveTokens();
                break;
            }
        }

        if (!$found) {
            $this->clearRememberCookie();
            return false;
        }

        $_SESSION['user'] = $username;
        $this->setRememberCookie($username); // rinnova il cookie
        return true;
    }

    public function logout(): void
    {
        $cookie = $_COOKIE[self::COOKIE_NAME] ?? '';
        if (!empty($cookie)) {
            $parts = explode(':', $cookie, 2);
            if (count($parts) === 2) {
                [$username, $token] = $parts;
                $tokenHash = hash('sha256', $token);
                $this->tokens = array_values(array_filter(
                    $this->tokens,
                    fn($e) => !($e['username'] === $username && hash_equals($e['token_hash'], $tokenHash))
                ));
                $this->saveTokens();
            }
            $this->clearRememberCookie();
        }

        unset($_SESSION['user']);
        session_destroy();
    }

    public function getLockoutRemaining(): int
    {
        $attempts    = $_SESSION['login_attempts']    ?? 0;
        $lastAttempt = $_SESSION['login_last_attempt'] ?? 0;
        if ($attempts < self::MAX_ATTEMPTS) return 0;
        return max(0, (int)(self::LOCKOUT_SECONDS - (time() - $lastAttempt)));
    }

    public function getUser(): ?string
    {
        return $_SESSION['user'] ?? null;
    }

    public function getUserData(string $username): ?array
    {
        return $this->users[$username] ?? null;
    }

    public function getAllUsers(): array
    {
        return $this->users;
    }

    public function updatePassword(string $username, string $newPassword): bool
    {
        if (!isset($this->users[$username])) return false;
        $this->users[$username]['password'] = password_hash($newPassword, PASSWORD_BCRYPT);
        $this->saveUsers();
        return true;
    }

    public function deleteUser(string $username): bool
    {
        if (!isset($this->users[$username])) return false;

        if ($this->users[$username]['role'] === 'superadmin') {
            $count = count(array_filter($this->users, fn($u) => $u['role'] === 'superadmin'));
            if ($count <= 1) return false;
        }

        $this->tokens = array_values(array_filter(
            $this->tokens,
            fn($e) => $e['username'] !== $username
        ));
        $this->saveTokens();

        unset($this->users[$username]);
        $this->saveUsers();
        return true;
    }

    public function isLoggedIn(): bool
    {
        return isset($_SESSION['user']);
    }

    public function isSuperAdmin(): bool
    {
        if (!$this->isLoggedIn()) return false;
        $username = $this->getUser();
        return isset($this->users[$username]) && $this->users[$username]['role'] === 'superadmin';
    }

    private function setRememberCookie(string $username): void
    {
        $token   = bin2hex(random_bytes(32));
        $expires = time() + (self::REMEMBER_DAYS * 86400);

        $this->tokens[] = [
            'username'   => $username,
            'token_hash' => hash('sha256', $token),
            'expires'    => $expires,
        ];
        $this->saveTokens();

        setcookie(self::COOKIE_NAME, $username . ':' . $token, [
            'expires'  => $expires,
            'path'     => '/',
            'secure'   => true,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
    }

    private function clearRememberCookie(): void
    {
        setcookie(self::COOKIE_NAME, '', [
            'expires'  => time() - 3600,
            'path'     => '/',
            'secure'   => true,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
    }

    private function purgeExpiredTokens(): void
    {
        $before = count($this->tokens);
        $this->tokens = array_values(array_filter(
            $this->tokens,
            fn($e) => $e['expires'] > time()
        ));
        if (count($this->tokens) !== $before) {
            $this->saveTokens();
        }
    }
}
