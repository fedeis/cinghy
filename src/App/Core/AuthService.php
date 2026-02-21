<?php

namespace App\Core;

class AuthService
{
    private string $usersFile;
    private array $users = [];

    // Max tentativi di login falliti prima del blocco temporaneo
    private const MAX_ATTEMPTS    = 10;
    // Secondi di blocco dopo MAX_ATTEMPTS tentativi falliti
    private const LOCKOUT_SECONDS = 900; // 15 minuti

    public function __construct()
    {
        $this->usersFile = __DIR__ . '/../../../config/users.json';
        $this->loadUsers();
    }

    private function loadUsers(): void
    {
        if (file_exists($this->usersFile)) {
            $json = file_get_contents($this->usersFile);
            $this->users = json_decode($json, true) ?? [];
        }
    }

    private function saveUsers(): void
    {
        file_put_contents($this->usersFile, json_encode($this->users, JSON_PRETTY_PRINT));
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

    public function login(string $username, string $password): bool
    {
        // --- Brute force protection ---
        $attempts    = $_SESSION['login_attempts']    ?? 0;
        $lastAttempt = $_SESSION['login_last_attempt'] ?? 0;

        // Reset contatore se il lockout è scaduto
        if (time() - $lastAttempt > self::LOCKOUT_SECONDS) {
            $attempts = 0;
        }

        if ($attempts >= self::MAX_ATTEMPTS) {
            return false; // ancora in lockout
        }
        // --- Fine brute force protection ---

        if (!isset($this->users[$username]) ||
            !password_verify($password, $this->users[$username]['password'])) {
            $_SESSION['login_attempts']    = $attempts + 1;
            $_SESSION['login_last_attempt'] = time();
            return false;
        }

        // Login riuscito: resetta il contatore e avvia la sessione
        $_SESSION['login_attempts']    = 0;
        $_SESSION['login_last_attempt'] = 0;
        $_SESSION['user']              = $username;
        return true;
    }

    /**
     * Restituisce i secondi rimanenti al termine del lockout, oppure 0 se non in lockout.
     */
    public function getLockoutRemaining(): int
    {
        $attempts    = $_SESSION['login_attempts']    ?? 0;
        $lastAttempt = $_SESSION['login_last_attempt'] ?? 0;

        if ($attempts < self::MAX_ATTEMPTS) return 0;

        $elapsed   = time() - $lastAttempt;
        $remaining = self::LOCKOUT_SECONDS - $elapsed;
        return max(0, (int)$remaining);
    }

    public function logout(): void
    {
        unset($_SESSION['user']);
        session_destroy();
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
        if (!isset($this->users[$username])) {
            return false;
        }

        $this->users[$username]['password'] = password_hash($newPassword, PASSWORD_BCRYPT);
        $this->saveUsers();
        return true;
    }

    public function deleteUser(string $username): bool
    {
        if (!isset($this->users[$username])) {
            return false;
        }

        // Non eliminare l'ultimo superadmin
        if ($this->users[$username]['role'] === 'superadmin') {
            $superAdminCount = 0;
            foreach ($this->users as $u) {
                if ($u['role'] === 'superadmin') $superAdminCount++;
            }
            if ($superAdminCount <= 1) {
                return false;
            }
        }

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
}
