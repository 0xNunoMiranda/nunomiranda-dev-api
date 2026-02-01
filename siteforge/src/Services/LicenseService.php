<?php
/**
 * License Service
 * 
 * Serviço para comunicar com a API de licenças Node.js
 * e gerir créditos/módulos.
 */

namespace App\Services;

class LicenseService
{
    private string $apiUrl;
    private string $licenseKey;
    private int $timeout;
    private ?array $cachedLicense = null;
    private int $cacheExpiry = 0;
    private const CACHE_TTL = 300; // 5 minutos

    public function __construct()
    {
        $config = require __DIR__ . '/../../config/config.php';
        
        $this->apiUrl = rtrim($config['api_url'] ?? 'http://localhost:3000', '/');
        $this->licenseKey = $config['license_key'] ?? '';
        $this->timeout = $config['api']['timeout'] ?? 30;
    }

    /**
     * Validar licença
     */
    public function validate(): array
    {
        if (empty($this->licenseKey)) {
            return [
                'valid' => false,
                'error' => 'Chave de licença não configurada',
            ];
        }

        // Usar cache se disponível
        if ($this->cachedLicense && time() < $this->cacheExpiry) {
            return $this->cachedLicense;
        }

        $response = $this->request('GET', "/api/licenses/{$this->licenseKey}/validate");
        
        if ($response['success']) {
            $this->cachedLicense = $response;
            $this->cacheExpiry = time() + self::CACHE_TTL;
        }

        return $response;
    }

    /**
     * Obter créditos disponíveis
     */
    public function getCredits(): array
    {
        if (empty($this->licenseKey)) {
            return ['success' => false, 'error' => 'Chave de licença não configurada'];
        }

        return $this->request('GET', "/api/licenses/{$this->licenseKey}/credits");
    }

    /**
     * Verificar se tem créditos suficientes
     */
    public function hasCredits(string $type, int $amount = 1): bool
    {
        $credits = $this->getCredits();
        
        if (!$credits['success']) {
            return false;
        }

        $available = $credits['credits'][$type . '_remaining'] ?? 0;
        return $available >= $amount;
    }

    /**
     * Verificar se um módulo está ativo
     */
    public function moduleEnabled(string $module): bool
    {
        $license = $this->validate();
        
        if (!$license['valid']) {
            return false;
        }

        return $license['modules'][$module] ?? false;
    }

    /**
     * Obter estatísticas de uso
     */
    public function getUsageStats(int $days = 30): array
    {
        if (empty($this->licenseKey)) {
            return ['success' => false, 'error' => 'Chave de licença não configurada'];
        }

        return $this->request('GET', "/api/licenses/{$this->licenseKey}/usage?days={$days}");
    }

    /**
     * Enviar mensagem ao bot
     */
    public function sendBotMessage(string $message, string $sessionId = null): array
    {
        if (empty($this->licenseKey)) {
            return ['success' => false, 'error' => 'Chave de licença não configurada'];
        }

        $sessionId = $sessionId ?: $this->generateSessionId();

        return $this->request('POST', '/api/bot/message', [
            'licenseKey' => $this->licenseKey,
            'sessionId' => $sessionId,
            'message' => $message,
        ]);
    }

    /**
     * Gerar site com AI
     */
    public function generateSite(array $businessInfo): array
    {
        if (empty($this->licenseKey)) {
            return ['success' => false, 'error' => 'Chave de licença não configurada'];
        }

        return $this->request('POST', '/api/ai/generate-site', [
            'licenseKey' => $this->licenseKey,
            'businessInfo' => $businessInfo,
        ]);
    }

    /**
     * Gerar FAQs com AI
     */
    public function generateFaqs(array $businessInfo, int $count = 10): array
    {
        if (empty($this->licenseKey)) {
            return ['success' => false, 'error' => 'Chave de licença não configurada'];
        }

        return $this->request('POST', '/api/ai/generate-faqs', [
            'licenseKey' => $this->licenseKey,
            'businessInfo' => $businessInfo,
            'count' => $count,
        ]);
    }

    /**
     * Chat genérico com AI
     */
    public function aiChat(string $prompt, string $systemPrompt = null, array $context = []): array
    {
        if (empty($this->licenseKey)) {
            return ['success' => false, 'error' => 'Chave de licença não configurada'];
        }

        $data = [
            'licenseKey' => $this->licenseKey,
            'prompt' => $prompt,
        ];

        if ($systemPrompt) {
            $data['systemPrompt'] = $systemPrompt;
        }

        if (!empty($context)) {
            $data['context'] = $context;
        }

        return $this->request('POST', '/api/ai/chat', $data);
    }

    /**
     * Fazer pedido HTTP à API
     */
    private function request(string $method, string $endpoint, array $data = null): array
    {
        $url = $this->apiUrl . $endpoint;

        $ch = curl_init();
        
        $options = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
            ],
        ];

        if ($method === 'POST') {
            $options[CURLOPT_POST] = true;
            if ($data) {
                $options[CURLOPT_POSTFIELDS] = json_encode($data);
            }
        } elseif ($method === 'PUT') {
            $options[CURLOPT_CUSTOMREQUEST] = 'PUT';
            if ($data) {
                $options[CURLOPT_POSTFIELDS] = json_encode($data);
            }
        } elseif ($method === 'DELETE') {
            $options[CURLOPT_CUSTOMREQUEST] = 'DELETE';
        }

        curl_setopt_array($ch, $options);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        curl_close($ch);

        if ($error) {
            return [
                'success' => false,
                'error' => 'Erro de conexão: ' . $error,
            ];
        }

        $decoded = json_decode($response, true);

        if ($decoded === null) {
            return [
                'success' => false,
                'error' => 'Resposta inválida da API',
                'raw' => $response,
            ];
        }

        return $decoded;
    }

    /**
     * Gerar ID de sessão único
     */
    private function generateSessionId(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['bot_session_id'])) {
            $_SESSION['bot_session_id'] = 'sess_' . bin2hex(random_bytes(16));
        }
        
        return $_SESSION['bot_session_id'];
    }

    /**
     * Limpar cache
     */
    public function clearCache(): void
    {
        $this->cachedLicense = null;
        $this->cacheExpiry = 0;
    }

    /**
     * Obter chave de licença
     */
    public function getLicenseKey(): string
    {
        return $this->licenseKey;
    }

    /**
     * Verificar se tem licença configurada
     */
    public function hasLicense(): bool
    {
        return !empty($this->licenseKey);
    }
}
