<?php
/**
 * Auth - Sistema de autenticação do Admin Panel
 */

namespace App;

class Auth
{
    private array $config;
    private ?Database $db;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * Verifica se o PIN está correto.
     */
    public function validatePin(string $pin): bool
    {
        return hash_equals($this->config['admin']['pin'], $pin);
    }

    /**
     * Inicia sessão de admin.
     */
    public function login(string $pin): bool
    {
        if (!$this->validatePin($pin)) {
            return false;
        }

        // Verificar IP se configurado
        if (!empty($this->config['admin']['allowed_ips'])) {
            $clientIp = $_SERVER['REMOTE_ADDR'] ?? '';
            if (!in_array($clientIp, $this->config['admin']['allowed_ips'])) {
                return false;
            }
        }

        // Criar sessão
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $token = bin2hex(random_bytes(32));
        $_SESSION['admin_authenticated'] = true;
        $_SESSION['admin_token'] = $token;
        $_SESSION['admin_login_time'] = time();
        $_SESSION['admin_ip'] = $_SERVER['REMOTE_ADDR'] ?? '';

        // Guardar na BD se disponível
        $pdo = Database::getInstance();
        if ($pdo) {
            $expiresAt = date('Y-m-d H:i:s', time() + $this->config['admin']['session_lifetime']);
            Database::insert('admin_sessions', [
                'session_token' => $token,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                'expires_at' => $expiresAt,
            ]);
        }

        return true;
    }

    /**
     * Verifica se está autenticado.
     */
    public function isAuthenticated(): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_SESSION['admin_authenticated']) || $_SESSION['admin_authenticated'] !== true) {
            return false;
        }

        // Verificar tempo de sessão
        $loginTime = $_SESSION['admin_login_time'] ?? 0;
        if (time() - $loginTime > $this->config['admin']['session_lifetime']) {
            $this->logout();
            return false;
        }

        // Verificar IP (sessão fixada)
        $sessionIp = $_SESSION['admin_ip'] ?? '';
        $currentIp = $_SERVER['REMOTE_ADDR'] ?? '';
        if ($sessionIp !== $currentIp) {
            $this->logout();
            return false;
        }

        return true;
    }

    /**
     * Termina sessão.
     */
    public function logout(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Remover da BD
        if (!empty($_SESSION['admin_token'])) {
            Database::delete('admin_sessions', 'session_token = ?', [$_SESSION['admin_token']]);
        }

        $_SESSION = [];
        session_destroy();
    }

    /**
     * Requer autenticação ou redireciona.
     */
    public function requireAuth(string $loginUrl = '/admin/login.php'): void
    {
        if (!$this->isAuthenticated()) {
            header('Location: ' . $loginUrl);
            exit;
        }
    }
}
