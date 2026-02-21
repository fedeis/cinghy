<?php

namespace App\Core;

class Router
{
    private array $routes = [];

    public function get(string $path, callable $handler): void
    {
        $this->routes['GET'][$path] = $handler;
    }

    public function post(string $path, callable $handler): void
    {
        $this->routes['POST'][$path] = $handler;
    }

    public function dispatch(): void
    {
        $method = $_SERVER['REQUEST_METHOD'];
        $uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

        // Normalize URI: rimuovi slash finale a meno che non sia "/"
        if ($uri !== '/' && str_ends_with($uri, '/')) {
            $uri = rtrim($uri, '/');
        }

        if (!isset($this->routes[$method][$uri])) {
            http_response_code(404);
            echo json_encode(['error' => 'Not Found']);
            return;
        }

        // Verifica CSRF su tutte le richieste POST
        if ($method === 'POST') {
            $this->verifyCsrf();
        }

        call_user_func($this->routes[$method][$uri]);
    }

    /**
     * Genera (se non esiste) e restituisce il token CSRF della sessione corrente.
     * Da chiamare nel template per inserire il campo hidden nel form:
     *
     *   <input type="hidden" name="csrf_token" value="<?= App\Core\Router::csrfToken() ?>">
     */
    public static function csrfToken(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * Verifica che il token CSRF nel POST corrisponda a quello in sessione.
     * In caso di mismatch termina con 403.
     */
    private function verifyCsrf(): void
    {
        $token        = $_POST['csrf_token'] ?? '';
        $sessionToken = $_SESSION['csrf_token'] ?? '';

        if (empty($sessionToken) || !hash_equals($sessionToken, $token)) {
            http_response_code(403);
            echo json_encode(['error' => 'Invalid CSRF token']);
            exit;
        }
    }
}
