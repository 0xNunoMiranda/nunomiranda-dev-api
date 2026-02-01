<?php
/**
 * API Client - centraliza comunicação com a API Node.js
 */

namespace App;

class ApiClient
{
    private string $baseUrl;
    private ?string $apiKey;
    private int $timeout;

    public function __construct(string $baseUrl, ?string $apiKey = null, int $timeout = 15)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->apiKey = $apiKey;
        $this->timeout = $timeout;
    }

    /**
     * GET request
     * @param string $path
     * @param array $query
     * @return array{ok: bool, data?: array, error?: string, status?: int}
     */
    public function get(string $path, array $query = []): array
    {
        $url = $this->baseUrl . $path;
        if ($query) {
            $url .= '?' . http_build_query($query);
        }
        return $this->request('GET', $url);
    }

    /**
     * POST request
     * @param string $path
     * @param array $body
     * @return array{ok: bool, data?: array, error?: string, status?: int}
     */
    public function post(string $path, array $body = []): array
    {
        $url = $this->baseUrl . $path;
        return $this->request('POST', $url, $body);
    }

    /**
     * PATCH request
     * @param string $path
     * @param array $body
     * @return array{ok: bool, data?: array, error?: string, status?: int}
     */
    public function patch(string $path, array $body = []): array
    {
        $url = $this->baseUrl . $path;
        return $this->request('PATCH', $url, $body);
    }

    /**
     * DELETE request
     * @param string $path
     * @return array{ok: bool, data?: array, error?: string, status?: int}
     */
    public function delete(string $path): array
    {
        $url = $this->baseUrl . $path;
        return $this->request('DELETE', $url);
    }

    /**
     * Executa a requisição HTTP
     */
    private function request(string $method, string $url, ?array $body = null): array
    {
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        if ($this->apiKey) {
            $headers[] = 'Authorization: Bearer ' . $this->apiKey;
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CUSTOMREQUEST => $method,
        ]);

        if ($body !== null && in_array($method, ['POST', 'PATCH', 'PUT'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            return [
                'ok' => false,
                'error' => 'Erro de conexão: ' . $curlError,
                'status' => 0,
            ];
        }

        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'ok' => false,
                'error' => 'Resposta inválida da API',
                'status' => $httpCode,
            ];
        }

        $isOk = ($decoded['ok'] ?? false) === true && $httpCode >= 200 && $httpCode < 300;
        return [
            'ok' => $isOk,
            'data' => $decoded,
            'status' => $httpCode,
            'error' => !$isOk ? ($decoded['message'] ?? 'Erro desconhecido') : null,
        ];
    }
}
