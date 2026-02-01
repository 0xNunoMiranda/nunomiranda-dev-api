<?php
/**
 * SupportService - Gestão de tickets de suporte
 */

namespace App\Services;

use App\Database;

class SupportService
{
    /**
     * Gera número único de ticket.
     */
    private function generateTicketNumber(): string
    {
        return 'TKT-' . strtoupper(bin2hex(random_bytes(4)));
    }

    /**
     * Cria um novo ticket de suporte.
     */
    public function createTicket(array $data): ?int
    {
        return Database::insert('support_tickets', [
            'ticket_number' => $this->generateTicketNumber(),
            'customer_name' => $data['name'],
            'customer_email' => $data['email'],
            'customer_phone' => $data['phone'] ?? null,
            'subject' => $data['subject'],
            'category' => $data['category'] ?? 'general',
            'priority' => $data['priority'] ?? 'normal',
            'status' => 'open',
        ]);
    }

    /**
     * Obtém ticket por ID.
     */
    public function getTicketById(int $id): ?array
    {
        return Database::fetchOne(
            'SELECT * FROM support_tickets WHERE id = ?',
            [$id]
        );
    }

    /**
     * Obtém ticket por número.
     */
    public function getTicketByNumber(string $ticketNumber): ?array
    {
        return Database::fetchOne(
            'SELECT * FROM support_tickets WHERE ticket_number = ?',
            [$ticketNumber]
        );
    }

    /**
     * Lista tickets por email do cliente.
     */
    public function getTicketsByEmail(string $email): array
    {
        return Database::fetchAll(
            'SELECT * FROM support_tickets WHERE customer_email = ? ORDER BY created_at DESC',
            [$email]
        );
    }

    /**
     * Lista todos os tickets (para admin).
     */
    public function getAllTickets(string $status = null, int $limit = 50, int $offset = 0): array
    {
        $sql = 'SELECT * FROM support_tickets';
        $params = [];

        if ($status) {
            $sql .= ' WHERE status = ?';
            $params[] = $status;
        }

        $sql .= ' ORDER BY 
            CASE priority 
                WHEN "urgent" THEN 1 
                WHEN "high" THEN 2 
                WHEN "normal" THEN 3 
                ELSE 4 
            END,
            created_at DESC
            LIMIT ? OFFSET ?';
        
        $params[] = $limit;
        $params[] = $offset;

        return Database::fetchAll($sql, $params);
    }

    /**
     * Atualiza estado do ticket.
     */
    public function updateTicketStatus(int $ticketId, string $status): bool
    {
        $data = ['status' => $status];
        
        if ($status === 'resolved' || $status === 'closed') {
            $data['resolved_at'] = date('Y-m-d H:i:s');
        }

        return Database::update('support_tickets', $data, 'id = ?', [$ticketId]) > 0;
    }

    /**
     * Adiciona mensagem ao ticket.
     */
    public function addMessage(int $ticketId, string $senderType, string $message, ?string $senderName = null): ?int
    {
        return Database::insert('support_messages', [
            'ticket_id' => $ticketId,
            'sender_type' => $senderType,
            'sender_name' => $senderName,
            'message' => $message,
        ]);
    }

    /**
     * Obtém mensagens de um ticket.
     */
    public function getTicketMessages(int $ticketId): array
    {
        return Database::fetchAll(
            'SELECT * FROM support_messages WHERE ticket_id = ? ORDER BY created_at ASC',
            [$ticketId]
        );
    }

    /**
     * Conta tickets por estado (para dashboard).
     */
    public function getTicketStats(): array
    {
        $stats = Database::fetchAll(
            'SELECT status, COUNT(*) as count FROM support_tickets GROUP BY status'
        );

        $result = [
            'open' => 0,
            'in_progress' => 0,
            'waiting_customer' => 0,
            'resolved' => 0,
            'closed' => 0,
            'total' => 0,
        ];

        foreach ($stats as $row) {
            $result[$row['status']] = (int) $row['count'];
            $result['total'] += (int) $row['count'];
        }

        return $result;
    }
}
