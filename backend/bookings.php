<?php

error_reporting(E_ALL);
ini_set('display_errors', 0);

// Set CORS and Response Headers
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, GET, PUT, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");


if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}


require_once 'db.php'; 
require_once 'auth.php';

function sendBookingJson($statusCode, $body)
{
    http_response_code($statusCode);
    echo json_encode($body);
    exit;
}


function sendSendGridEmail($toEmail, $subject, $htmlContent) {
   $apiKey = getenv('SENDGRID_API_KEY');
    if (!$apiKey && isset($_SERVER['SENDGRID_API_KEY'])) {
        $apiKey = $_SERVER['SENDGRID_API_KEY'];
    }
    $url = 'https://api.sendgrid.com/v3/mail/send';

    if (!$apiKey) {
        error_log('SendGrid email was not sent: SENDGRID_API_KEY is not configured.');
        return false;
    }

    $plainTextContent = trim(preg_replace('/\s+/', ' ', strip_tags($htmlContent)));
    $category = strpos($subject, 'Cancellation') !== false
        ? 'booking-cancellation'
        : 'booking-confirmation';

    $data = [
        "personalizations" => [[
            "to" => [["email" => $toEmail]]
        ]],
        "from" => [
            "email" => "aisyahmayysarah@gmail.com",
            "name" => "Event Booking System"
        ],
        "subject" => $subject,
        "content" => [[
            "type" => "text/plain",
            "value" => $plainTextContent
        ], [
            "type" => "text/html",
            "value" => $htmlContent
        ]],
        "categories" => [$category]
    ];

    $lastError = 'Unknown SendGrid error.';
    for ($attempt = 1; $attempt <= 2; $attempt++) {
        $ch = curl_init($url);
        if ($ch === false) {
            $lastError = 'Unable to initialize cURL.';
            break;
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json'
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 15
        ]);

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response !== false && $httpCode === 202) {
            return true;
        }

        $lastError = $curlError ?: "SendGrid returned HTTP {$httpCode}: {$response}";
        if ($response !== false && $httpCode >= 400 && $httpCode < 600) {
            break;
        }
    }

    error_log("SendGrid email was not sent after {$attempt} attempt(s): {$lastError}");
    return false;
}

function sendResponseThenEmail($response, $toEmail, $subject, $htmlContent) {
    $emailSent = sendSendGridEmail($toEmail, $subject, $htmlContent);
    $response['email_sent'] = $emailSent;
    $response['email_message'] = $emailSent
        ? "Email sent to {$toEmail}."
        : "Email could not be sent after retrying. Check the SendGrid configuration and server error log.";

    echo json_encode($response);

    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    }
}

$method = $_SERVER['REQUEST_METHOD'];


if ($method === 'GET') {
    try {
        $currentUser = authenticateUser();
        $userId = (int) $currentUser->user_id;
        $role = (string) $currentUser->role;
        requireRole($currentUser, ['Customer', 'Organiser', 'Staff', 'Administrator']);

        $page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1]
        ]);
        $limit = filter_var($_GET['limit'] ?? 10, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 50]
        ]);
        if ($page === false || $limit === false) {
            sendBookingJson(400, [
                'success' => false,
                'message' => 'page must be at least 1 and limit must be between 1 and 50.'
            ]);
        }

        $search = trim((string) ($_GET['search'] ?? ''));
        $status = trim((string) ($_GET['status'] ?? ''));
        $allowedStatuses = ['Pending', 'Confirmed', 'Cancelled'];
        if ($status !== '' && !in_array($status, $allowedStatuses, true)) {
            sendBookingJson(400, [
                'success' => false,
                'message' => 'status must be Pending, Confirmed, or Cancelled.'
            ]);
        }

        if ($role === 'Customer') {
            $where = ['b.user_id = ?'];
            $params = [$userId];
        } elseif ($role === 'Organiser') {
            $where = ['e.organiser_id = ?'];
            $params = [$userId];
        } else {
            $where = ['1 = 1'];
            $params = [];
        }
        if ($search !== '') {
            $where[] = '(e.title LIKE ? OR CAST(b.booking_id AS CHAR) LIKE ?)';
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }
        if ($status !== '') {
            $where[] = 'b.booking_status = ?';
            $params[] = $status;
        }
        $whereSql = implode(' AND ', $where);

        $countStmt = $conn->prepare(
            "SELECT COUNT(*) FROM bookings b
             LEFT JOIN events e ON b.event_id = e.event_id
             WHERE {$whereSql}"
        );
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();
        $offset = ($page - 1) * $limit;

        $stmt = $conn->prepare("
            SELECT b.*, e.title AS event_name, e.event_date,
                   u.name AS customer_name, u.email AS customer_email
            FROM bookings b 
            LEFT JOIN events e ON b.event_id = e.event_id 
            LEFT JOIN users u ON b.user_id = u.user_id 
            WHERE {$whereSql}
            ORDER BY b.booking_id DESC
            LIMIT {$limit} OFFSET {$offset}
        ");
        $stmt->execute($params);
        $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $userStmt = $conn->prepare(
            "SELECT user_id, name, email, role FROM users WHERE user_id = ?"
        );
        $userStmt->execute([$userId]);
        $user = $userStmt->fetch(PDO::FETCH_ASSOC) ?: [
            "user_id" => $userId,
            "name" => $currentUser->name ?? "",
            "email" => $currentUser->email ?? "",
            "role" => $role
        ];

        sendBookingJson(200, [
            "success" => true,
            "data" => $bookings,
            "user" => $user,
            "pagination" => [
                "page" => (int) $page,
                "limit" => (int) $limit,
                "total" => $total,
                "totalPages" => (int) ceil($total / $limit),
                "search" => $search,
                "status" => $status
            ]
        ]);
    } catch (Throwable $e) {
        error_log('Booking GET failed: ' . $e->getMessage());
        sendBookingJson(500, [
            "success" => false,
            "message" => "Unable to load bookings from the database."
        ]);
    }
    exit;
}


if ($method === 'POST') {
    $currentUser = authenticateUser();
    requireRole($currentUser, ['Customer']);
    $rawInput = file_get_contents("php://input");
    $data = json_decode($rawInput, true);

    if (!is_array($data)) {
        sendBookingJson(400, [
            "success" => false,
            "message" => "Request body must be valid JSON."
        ]);
    }

    $eventId = filter_var($data['event_id'] ?? null, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1]
    ]);
    $quantity = filter_var($data['quantity'] ?? null, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1, 'max_range' => 10]
    ]);
    $userId = (int) $currentUser->user_id;

    if ($eventId === false || $quantity === false) {
        sendBookingJson(400, [
            "success" => false,
            "message" => "event_id must be a positive integer and quantity must be between 1 and 10."
        ]);
    }

    try {
        $eventStmt = $conn->prepare(
            "SELECT * FROM events
             WHERE event_id = ? AND status = 'published' AND available_tickets >= ?"
        );
        $eventStmt->execute([$eventId, $quantity]);
        $event = $eventStmt->fetch(PDO::FETCH_ASSOC);

        if (!$event) {
            sendBookingJson(409, [
                "success" => false,
                "message" => "Event is unavailable or does not have enough tickets."
            ]);
        }

        $eventName = $event['title'];
        $ticketPrice = (float) $event['ticket_price'];
        $totalPrice = $ticketPrice * $quantity;

        $userStmt = $conn->prepare("SELECT email FROM users WHERE user_id = ?");
        $userStmt->execute([$userId]);
        $user = $userStmt->fetch(PDO::FETCH_ASSOC);
        if (!$user) {
            throw new RuntimeException("Authenticated user was not found.");
        }

       
        try {
            $insertStmt = $conn->prepare("INSERT INTO bookings (user_id, event_id, quantity, total_price, booking_status) VALUES (?, ?, ?, ?, 'Confirmed')");
            $insertStmt->execute([$userId, $eventId, $quantity, $totalPrice]);
            $bookingId = $conn->lastInsertId();
        } catch (Exception $ex) {
            $insertStmt = $conn->prepare("INSERT INTO bookings (user_id, event_id, quantity, total_price, status) VALUES (?, ?, ?, ?, 'Confirmed')");
            $insertStmt->execute([$userId, $eventId, $quantity, $totalPrice]);
            $bookingId = $conn->lastInsertId();
        }

        
        try {
            $updateStmt = $conn->prepare("UPDATE events SET available_tickets = available_tickets - ? WHERE event_id = ?");
            $updateStmt->execute([$quantity, $eventId]);
        } catch (Exception $ex) {}

        $userEmail = $user['email'];

       
        $subject = "Ticket Booking Confirmation - #" . $bookingId;
        $htmlContent = "
            <div style='font-family: Arial, sans-serif; padding: 20px; border: 1px solid #e2e8f0; border-radius: 8px;'>
                <h2 style='color: #2563eb;'>Booking Confirmed!</h2>
                <p>Thank you for your purchase. Here is your ticket pass summary:</p>
                <ul>
                    <li><strong>Booking Ref:</strong> BKG-2026-".str_pad($bookingId, 4, '0', STR_PAD_LEFT)."</li>
                    <li><strong>Event:</strong> {$eventName}</li>
                    <li><strong>Quantity:</strong> {$quantity} Ticket(s)</li>
                    <li><strong>Total Paid:</strong> RM ".number_format($totalPrice, 2)."</li>
                </ul>
                <p>Status: <strong style='color: #16a34a;'>Confirmed</strong></p>
            </div>
        ";
        $response = [
            "success" => true,
            "message" => "Booking successful. Confirmation email queued.",
            "booking_id" => $bookingId,
            "total_price" => $totalPrice,
            "email_type" => "booking_confirmation",
            "email_subject" => $subject
        ];
        sendResponseThenEmail($response, $userEmail, $subject, $htmlContent);

    } catch (Throwable $e) {
        error_log('Booking POST failed: ' . $e->getMessage());
        sendBookingJson(500, [
            "success" => false,
            "message" => "Unable to create booking."
        ]);
    }
    exit;
}


if ($method === 'PUT') {
    $currentUser = authenticateUser();
    requireRole($currentUser, ['Customer']);
    $userId = (int) $currentUser->user_id;
    $rawInput = file_get_contents("php://input");
    $data = json_decode($rawInput, true);

    if (!is_array($data)) {
        sendBookingJson(400, [
            "success" => false,
            "message" => "Request body must be valid JSON."
        ]);
    }

    $bookingId = $data['booking_id'] ?? null;
    $bookingStatus = $data['booking_status'] ?? 'Cancelled';

    if (filter_var($bookingId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false
        || $bookingStatus !== 'Cancelled') {
        sendBookingJson(400, [
            "success" => false,
            "message" => "booking_id must be a positive integer and booking_status must be Cancelled."
        ]);
    }

    try {
        $booking = null;
        if ($bookingId) {
            $stmt = $conn->prepare("SELECT * FROM bookings WHERE booking_id = ? AND user_id = ?");
            $stmt->execute([$bookingId, $userId]);
            $booking = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        if (!$booking) {
            $stmt = $conn->prepare("SELECT * FROM bookings WHERE user_id = ? ORDER BY booking_id DESC LIMIT 1");
            $stmt->execute([$userId]);
            $booking = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($booking) {
                $bookingId = $booking['booking_id'];
            }
        }

        if (!$booking) {
            sendBookingJson(404, [
                "success" => false,
                "message" => "Booking not found for the authenticated user."
            ]);
        }

        if (strtolower($booking['booking_status']) === 'cancelled') {
            sendBookingJson(409, [
                "success" => false,
                "message" => "This booking has already been cancelled."
            ]);
        }

        
        try {
            $updateStmt = $conn->prepare("UPDATE bookings SET booking_status = ? WHERE booking_id = ?");
            $updateStmt->execute([$bookingStatus, $bookingId]);
        } catch (Exception $ex) {
            $updateStmt = $conn->prepare("UPDATE bookings SET status = ? WHERE booking_id = ?");
            $updateStmt->execute([$bookingStatus, $bookingId]);
        }

        // Restore stock
        if (strtolower($bookingStatus) === 'cancelled' && !empty($booking['event_id'])) {
            try {
                $restoreStmt = $conn->prepare("UPDATE events SET available_tickets = available_tickets + ? WHERE event_id = ?");
                $restoreStmt->execute([$booking['quantity'], $booking['event_id']]);
            } catch (Exception $ex) {}
        }

        $userStmt = $conn->prepare("SELECT email FROM users WHERE user_id = ?");
        $userStmt->execute([$userId]);
        $user = $userStmt->fetch(PDO::FETCH_ASSOC);
        if (!$user) {
            throw new RuntimeException("Authenticated user was not found.");
        }
        $userEmail = $user['email'];
        $eventStmt = $conn->prepare("SELECT title FROM events WHERE event_id = ?");
        $eventStmt->execute([$booking['event_id']]);
        $eventName = $eventStmt->fetchColumn() ?: 'Event';

        
        $subject = "Booking Cancellation Notice - #" . $bookingId;
        $htmlContent = "
            <div style='font-family: Arial, sans-serif; padding: 20px; border: 1px solid #e2e8f0; border-radius: 8px;'>
                <h2 style='color: #e11d48;'>Booking Cancellation Confirmed</h2>
                <p>Hello,</p>
                <p>Your booking <strong>#{$bookingId}</strong> for <strong>{$eventName}</strong> has been <strong style='color: #e11d48;'>CANCELLED</strong>.</p>
                <hr style='border: none; border-top: 1px solid #e2e8f0;'>
                <p><strong>Cancelled Quantity:</strong> {$booking['quantity']} Ticket(s)</p>
                <p><strong>Refundable Total:</strong> RM " . number_format($booking['total_price'], 2) . "</p>
                <br>
                <p style='font-size: 12px; color: #64748b;'>Event Ticketing Portal - Automated Notification</p>
            </div>
        ";
        
        $response = [
            "success" => true,
            "message" => "Booking #{$bookingId} updated to {$bookingStatus}.",
            "booking_id" => $bookingId,
            "email_type" => "booking_cancellation",
            "email_subject" => $subject
        ];
        sendResponseThenEmail($response, $userEmail, $subject, $htmlContent);

    } catch (Throwable $e) {
        error_log('Booking PUT failed: ' . $e->getMessage());
        sendBookingJson(500, [
            "success" => false,
            "message" => "Unable to cancel booking."
        ]);
    }
    exit;
}