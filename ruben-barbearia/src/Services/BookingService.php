<?php
/**
 * BookingService - Gestão de marcações
 */

namespace App\Services;

use App\Database;

class BookingService
{
    /**
     * Gera referência única de marcação.
     */
    private function generateBookingRef(): string
    {
        return 'BK' . date('ymd') . strtoupper(bin2hex(random_bytes(3)));
    }

    /**
     * Cria uma nova marcação.
     */
    public function createBooking(array $data): ?int
    {
        return Database::insert('bookings', [
            'booking_ref' => $this->generateBookingRef(),
            'customer_name' => $data['name'],
            'customer_email' => $data['email'] ?? null,
            'customer_phone' => $data['phone'],
            'service_id' => $data['service_id'] ?? null,
            'service_name' => $data['service_name'],
            'staff_id' => $data['staff_id'] ?? null,
            'staff_name' => $data['staff_name'] ?? null,
            'booking_date' => $data['date'],
            'booking_time' => $data['time'],
            'duration_minutes' => $data['duration'] ?? 30,
            'price_cents' => $data['price_cents'] ?? 0,
            'status' => 'pending',
            'notes' => $data['notes'] ?? null,
            'source' => $data['source'] ?? 'website',
        ]);
    }

    /**
     * Obtém marcação por ID.
     */
    public function getBookingById(int $id): ?array
    {
        return Database::fetchOne('SELECT * FROM bookings WHERE id = ?', [$id]);
    }

    /**
     * Obtém marcação por referência.
     */
    public function getBookingByRef(string $ref): ?array
    {
        return Database::fetchOne('SELECT * FROM bookings WHERE booking_ref = ?', [$ref]);
    }

    /**
     * Lista marcações por data.
     */
    public function getBookingsByDate(string $date): array
    {
        return Database::fetchAll(
            'SELECT * FROM bookings WHERE booking_date = ? ORDER BY booking_time ASC',
            [$date]
        );
    }

    /**
     * Lista marcações por telefone do cliente.
     */
    public function getBookingsByPhone(string $phone): array
    {
        return Database::fetchAll(
            'SELECT * FROM bookings WHERE customer_phone = ? ORDER BY booking_date DESC, booking_time DESC LIMIT 10',
            [$phone]
        );
    }

    /**
     * Lista marcações futuras (para admin).
     */
    public function getUpcomingBookings(int $limit = 20): array
    {
        return Database::fetchAll(
            'SELECT * FROM bookings 
             WHERE booking_date >= CURDATE() 
               AND status IN ("pending", "confirmed")
             ORDER BY booking_date ASC, booking_time ASC
             LIMIT ?',
            [$limit]
        );
    }

    /**
     * Atualiza estado da marcação.
     */
    public function updateBookingStatus(int $bookingId, string $status): bool
    {
        return Database::update('bookings', ['status' => $status], 'id = ?', [$bookingId]) > 0;
    }

    /**
     * Confirma marcação.
     */
    public function confirmBooking(int $bookingId): bool
    {
        return $this->updateBookingStatus($bookingId, 'confirmed');
    }

    /**
     * Cancela marcação.
     */
    public function cancelBooking(int $bookingId): bool
    {
        return $this->updateBookingStatus($bookingId, 'cancelled');
    }

    /**
     * Verifica disponibilidade de um slot.
     */
    public function isSlotAvailable(string $date, string $time, int $durationMinutes = 30, ?int $staffId = null): bool
    {
        $sql = 'SELECT COUNT(*) as count FROM bookings 
                WHERE booking_date = ? 
                  AND status IN ("pending", "confirmed")
                  AND (
                    (booking_time <= ? AND ADDTIME(booking_time, SEC_TO_TIME(duration_minutes * 60)) > ?)
                    OR
                    (booking_time < ADDTIME(?, SEC_TO_TIME(? * 60)) AND booking_time >= ?)
                  )';
        
        $params = [$date, $time, $time, $time, $durationMinutes, $time];

        if ($staffId) {
            $sql .= ' AND staff_id = ?';
            $params[] = $staffId;
        }

        $result = Database::fetchOne($sql, $params);
        return ($result['count'] ?? 0) === 0;
    }

    /**
     * Obtém slots disponíveis para uma data.
     */
    public function getAvailableSlots(string $date, ?int $staffId = null): array
    {
        // Obter horário de funcionamento do dia
        $dayOfWeek = date('w', strtotime($date));
        $hours = Database::fetchOne(
            'SELECT * FROM business_hours WHERE day_of_week = ?',
            [$dayOfWeek]
        );

        if (!$hours || !$hours['is_open']) {
            return [];
        }

        $slots = [];
        $openTime = strtotime($hours['open_time']);
        $closeTime = strtotime($hours['close_time']);
        $breakStart = $hours['break_start'] ? strtotime($hours['break_start']) : null;
        $breakEnd = $hours['break_end'] ? strtotime($hours['break_end']) : null;
        $interval = 30 * 60; // 30 minutos

        for ($time = $openTime; $time < $closeTime; $time += $interval) {
            // Verificar se está no intervalo de pausa
            if ($breakStart && $breakEnd && $time >= $breakStart && $time < $breakEnd) {
                continue;
            }

            $timeStr = date('H:i:s', $time);
            
            if ($this->isSlotAvailable($date, $timeStr, 30, $staffId)) {
                $slots[] = [
                    'time' => date('H:i', $time),
                    'available' => true,
                ];
            }
        }

        return $slots;
    }

    /**
     * Estatísticas de marcações (para dashboard).
     */
    public function getBookingStats(): array
    {
        $today = date('Y-m-d');
        
        $todayCount = Database::fetchOne(
            'SELECT COUNT(*) as count FROM bookings WHERE booking_date = ?',
            [$today]
        );

        $weekCount = Database::fetchOne(
            'SELECT COUNT(*) as count FROM bookings 
             WHERE booking_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)',
            []
        );

        $pendingCount = Database::fetchOne(
            'SELECT COUNT(*) as count FROM bookings WHERE status = "pending"',
            []
        );

        return [
            'today' => (int) ($todayCount['count'] ?? 0),
            'this_week' => (int) ($weekCount['count'] ?? 0),
            'pending' => (int) ($pendingCount['count'] ?? 0),
        ];
    }

    /**
     * Lista todos os serviços disponíveis.
     */
    public function getServices(): array
    {
        return Database::fetchAll(
            'SELECT * FROM services WHERE is_active = 1 ORDER BY sort_order ASC'
        );
    }

    /**
     * Lista staff disponível.
     */
    public function getStaff(): array
    {
        return Database::fetchAll(
            'SELECT * FROM staff WHERE is_active = 1 ORDER BY name ASC'
        );
    }

    /**
     * Obtém horários de funcionamento.
     */
    public function getBusinessHours(): array
    {
        return Database::fetchAll(
            'SELECT * FROM business_hours ORDER BY day_of_week ASC'
        );
    }
}
