<?php
/**
 * Local Booking API
 * Handles booking AJAX requests from the public site
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/../../src/bootstrap.php';

use App\Database;
use App\Services\BookingService;

$db = Database::getInstance();
$bookingService = $GLOBALS['bookingService'] ?? new BookingService($db);

$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

try {
    // GET /api/booking/slots?date=YYYY-MM-DD
    if ($method === 'GET' && strpos($path, '/slots') !== false) {
        $date = $_GET['date'] ?? null;
        
        if (!$date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid date format. Use YYYY-MM-DD']);
            exit;
        }
        
        $slots = $bookingService->getAvailableSlots($date);
        echo json_encode(['slots' => $slots, 'date' => $date]);
        exit;
    }
    
    // GET /api/booking/services
    if ($method === 'GET' && strpos($path, '/services') !== false) {
        $services = $bookingService->getServices();
        echo json_encode(['services' => $services]);
        exit;
    }
    
    // GET /api/booking/staff
    if ($method === 'GET' && strpos($path, '/staff') !== false) {
        $staff = $bookingService->getStaff();
        echo json_encode(['staff' => $staff]);
        exit;
    }
    
    // POST /api/booking - Create a new booking
    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        // Validate required fields
        $required = ['customer_name', 'customer_phone', 'service_id', 'booking_date', 'booking_time'];
        foreach ($required as $field) {
            if (empty($input[$field])) {
                http_response_code(400);
                echo json_encode(['error' => "Missing required field: $field"]);
                exit;
            }
        }
        
        // Check availability
        if (!$bookingService->isSlotAvailable($input['booking_date'], $input['booking_time'], $input['staff_id'] ?? null)) {
            http_response_code(409);
            echo json_encode(['error' => 'This time slot is no longer available']);
            exit;
        }
        
        // Create booking
        $bookingId = $bookingService->createBooking([
            'customer_name' => trim($input['customer_name']),
            'customer_phone' => trim($input['customer_phone']),
            'customer_email' => $input['customer_email'] ?? null,
            'service_id' => (int)$input['service_id'],
            'staff_id' => $input['staff_id'] ? (int)$input['staff_id'] : null,
            'booking_date' => $input['booking_date'],
            'booking_time' => $input['booking_time'],
            'notes' => $input['notes'] ?? '',
            'source' => 'website',
        ]);
        
        $booking = $bookingService->getBookingById($bookingId);
        
        http_response_code(201);
        echo json_encode([
            'success' => true,
            'message' => 'Booking created successfully',
            'booking' => [
                'id' => $booking['id'],
                'booking_number' => $booking['booking_number'],
                'date' => $booking['booking_date'],
                'time' => $booking['booking_time'],
            ]
        ]);
        exit;
    }
    
    // Default: Method not allowed
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    
} catch (Exception $e) {
    error_log('Booking API Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}
