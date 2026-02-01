<?php
/**
 * SubscriptionService - Gestão de subscrições via API Node.js
 */

namespace App\Services;

use App\ApiClient;

class SubscriptionService
{
    private ApiClient $api;
    private int $tenantId;

    public function __construct(ApiClient $api, int $tenantId)
    {
        $this->api = $api;
        $this->tenantId = $tenantId;
    }

    /**
     * Obtém a subscrição ativa do tenant.
     */
    public function getCurrentSubscription(): ?array
    {
        $response = $this->api->get("/billing/subscriptions/tenant/{$this->tenantId}");
        
        if ($response['ok'] && isset($response['data']['data']['subscription'])) {
            return $response['data']['data']['subscription'];
        }
        
        return null;
    }

    /**
     * Lista planos disponíveis.
     */
    public function getAvailablePlans(): array
    {
        $response = $this->api->get('/catalog/plans');
        
        if ($response['ok'] && isset($response['data']['data']['plans'])) {
            return $response['data']['data']['plans'];
        }
        
        return [];
    }

    /**
     * Cria uma nova subscrição.
     */
    public function createSubscription(
        int $planId,
        string $paymentMethod,
        array $customer,
        ?array $sddMandate = null
    ): array {
        $payload = [
            'tenantId' => $this->tenantId,
            'planId' => $planId,
            'paymentMethod' => $paymentMethod,
            'customer' => $customer,
        ];

        if ($sddMandate) {
            $payload['sddMandate'] = $sddMandate;
        }

        $response = $this->api->post('/billing/subscriptions', $payload);

        if ($response['ok']) {
            return [
                'success' => true,
                'data' => $response['data']['data'] ?? [],
            ];
        }

        return [
            'success' => false,
            'error' => $response['data']['error'] ?? 'Erro ao criar subscrição',
        ];
    }

    /**
     * Cancela a subscrição.
     */
    public function cancelSubscription(int $subscriptionId): array
    {
        $response = $this->api->post("/billing/subscriptions/{$subscriptionId}/cancel", []);

        if ($response['ok']) {
            return [
                'success' => true,
                'data' => $response['data']['data'] ?? [],
            ];
        }

        return [
            'success' => false,
            'error' => $response['data']['error'] ?? 'Erro ao cancelar subscrição',
        ];
    }

    /**
     * Verifica se o tenant tem uma subscrição ativa.
     */
    public function hasActiveSubscription(): bool
    {
        $subscription = $this->getCurrentSubscription();
        return $subscription && $subscription['status'] === 'active';
    }

    /**
     * Verifica se um módulo está incluído na subscrição atual.
     */
    public function hasModule(string $moduleSlug, array $plans): bool
    {
        $subscription = $this->getCurrentSubscription();
        if (!$subscription || $subscription['status'] !== 'active') {
            return false;
        }

        $planId = $subscription['planId'] ?? null;
        if (!$planId) {
            return false;
        }

        // Encontrar o plano e verificar módulos
        foreach ($plans as $plan) {
            if ((int)$plan['id'] === (int)$planId) {
                $modules = $plan['modules'] ?? [];
                foreach ($modules as $module) {
                    if ($module['slug'] === $moduleSlug) {
                        return true;
                    }
                }
                break;
            }
        }

        return false;
    }
}
