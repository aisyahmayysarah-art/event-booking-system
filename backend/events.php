<?php

header("Content-Type: application/json");

require_once "db.php";
require_once "auth.php";

/* ---------- helpers ---------- */

function sendJson($code, $body)
{
    http_response_code($code);
    echo json_encode($body);
    exit;
}

function fetchEvent($conn, $id)
{
    $stmt = $conn->prepare(
        "SELECT e.*, v.venue_name, v.location
         FROM events e
         JOIN venues v ON e.venue_id = v.venue_id
         WHERE e.event_id = ?"
    );
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function fetchVenue($conn, $venueId)
{
    $stmt = $conn->prepare(
        "SELECT venue_id, venue_name, capacity, status
         FROM venues WHERE venue_id = ?"
    );
    $stmt->execute([$venueId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function setVenueStatus($conn, $venueId, $status)
{
    $stmt = $conn->prepare("UPDATE venues SET status = ? WHERE venue_id = ?");
    $stmt->execute([$status, $venueId]);
}

function releaseVenueIfUnused($conn, $venueId, $exceptEventId = null)
{
    $sql = "SELECT COUNT(*) FROM events
            WHERE venue_id = ?
              AND status NOT IN ('cancelled', 'completed')";
    $params = [$venueId];
    if ($exceptEventId !== null) {
        $sql .= " AND event_id <> ?";
        $params[] = $exceptEventId;
    }
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    if ((int) $stmt->fetchColumn() === 0) {
        setVenueStatus($conn, $venueId, "Available");
    }
}

function syncVenueStatusForEvent($conn, $eventId, $venueId, $eventStatus)
{
    if (in_array($eventStatus, ["cancelled", "completed"], true)) {
        releaseVenueIfUnused($conn, $venueId, $eventId);
    } else {
        setVenueStatus($conn, $venueId, "In Use");
    }
}

// Tickets held by Pending/Confirmed bookings (Cancelled ones are released)
function activeBookedTickets($conn, $eventId)
{
    $stmt = $conn->prepare(
        "SELECT COALESCE(SUM(quantity), 0)
         FROM bookings
         WHERE event_id = ?
         AND booking_status IN ('Pending', 'Confirmed')"
    );
    $stmt->execute([$eventId]);
    return (int) $stmt->fetchColumn();
}

function validateEventData($data, $isUpdate, $existingEventDate = null)
{
    $errors = [];
    $statuses = ["draft", "published", "cancelled", "completed"];

    // On create every required field must exist. On update only the fields sent are checked.
    $check = function ($field) use ($data, $isUpdate) {
        return !$isUpdate || array_key_exists($field, $data);
    };

    if ($check("title")) {
        $title = $data["title"] ?? null;
        if (
            !is_string($title) ||
            mb_strlen(trim($title)) < 3 ||
            mb_strlen(trim($title)) > 150
        ) {
            $errors[] = ["field" => "title", "message" => "Title must be 3-150 characters"];
        }
    }

    if (isset($data["description"]) && !is_string($data["description"])) {
        $errors[] = ["field" => "description", "message" => "Description must be text"];
    }

    if ($check("venue_id")) {
        $venueId = filter_var(
            $data["venue_id"] ?? null,
            FILTER_VALIDATE_INT,
            ["options" => ["min_range" => 1]]
        );
        if ($venueId === false) {
            $errors[] = ["field" => "venue_id", "message" => "venue_id must be a positive integer"];
        }
    }

    if ($check("event_date")) {
        $date = $data["event_date"] ?? null;
        $ts = is_string($date) ? strtotime($date) : false;
        $isUnchangedExistingDate = $isUpdate &&
            $existingEventDate !== null &&
            $ts !== false &&
            strtotime($existingEventDate) === $ts;
        if ($ts === false || ($ts <= time() && !$isUnchangedExistingDate)) {
            $errors[] = [
                "field" => "event_date",
                "message" => "event_date must be a valid future date (YYYY-MM-DD HH:MM:SS)"
            ];
        }
    }

    if ($check("ticket_price")) {
        $price = $data["ticket_price"] ?? null;
        if (!is_numeric($price) || $price < 0) {
            $errors[] = ["field" => "ticket_price", "message" => "ticket_price must be a number >= 0"];
        }
    }

    if ($check("total_tickets")) {
        $total = filter_var(
            $data["total_tickets"] ?? null,
            FILTER_VALIDATE_INT,
            ["options" => ["min_range" => 1]]
        );
        if ($total === false) {
            $errors[] = ["field" => "total_tickets", "message" => "total_tickets must be an integer > 0"];
        }
    }

    if (isset($data["status"]) && !in_array($data["status"], $statuses, true)) {
        $errors[] = [
            "field" => "status",
            "message" => "status must be one of: " . implode(", ", $statuses)
        ];
    }

    return $errors;
}

/* ---------- main ---------- */

$currentUser = authenticateUser();   // 401 if token missing/invalid

$method = $_SERVER["REQUEST_METHOD"];

$id = isset($_GET["id"]) ? intval($_GET["id"]) : null;

$manageRoles = ["Organiser", "Staff"];

try {

    /* =====================================
       GET - ONE EVENT / LIST EVENTS
    ===================================== */

    if ($method === "GET") {

        // ---- one event ----
        if (isset($_GET["id"])) {

            $event = $id ? fetchEvent($conn, $id) : false;

            if (
                !$event ||
                ($currentUser->role === "Customer" && $event["status"] !== "published")
            ) {
                sendJson(404, ["success" => false, "message" => "Event not found"]);
            }

            sendJson(200, [
                "success" => true,
                "message" => "Event retrieved",
                "data" => $event
            ]);
        }

        // ---- list ----
        $errors = [];
        $where = [];
        $params = [];

        // Customers only see published events
        if ($currentUser->role === "Customer") {
            $where[] = "e.status = 'published'";
        }

        // search (title or description)
        if (isset($_GET["search"]) && trim($_GET["search"]) !== "") {
            $like = "%" . trim($_GET["search"]) . "%";
            $where[] = "(e.title LIKE ? OR e.description LIKE ?)";
            $params[] = $like;
            $params[] = $like;
        }

        // filter: status
        if (isset($_GET["status"])) {
            if (!in_array($_GET["status"], ["draft", "published", "cancelled", "completed"], true)) {
                $errors[] = ["field" => "status", "message" => "Invalid status"];
            } else {
                $where[] = "e.status = ?";
                $params[] = $_GET["status"];
            }
        }

        // filter: venue
        if (isset($_GET["venue_id"])) {
            if (filter_var($_GET["venue_id"], FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]]) === false) {
                $errors[] = ["field" => "venue_id", "message" => "venue_id must be a positive integer"];
            } else {
                $where[] = "e.venue_id = ?";
                $params[] = intval($_GET["venue_id"]);
            }
        }

        // filter: date range
        if (isset($_GET["date_from"])) {
            if (strtotime($_GET["date_from"]) === false) {
                $errors[] = ["field" => "date_from", "message" => "date_from must be a valid date"];
            } else {
                $where[] = "e.event_date >= ?";
                $params[] = date("Y-m-d H:i:s", strtotime($_GET["date_from"]));
            }
        }

        if (isset($_GET["date_to"])) {
            $dateTo = $_GET["date_to"];
            if (strtotime($dateTo) === false) {
                $errors[] = ["field" => "date_to", "message" => "date_to must be a valid date"];
            } else {
                // a plain date (2026-12-31) includes the whole day
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
                    $dateTo .= " 23:59:59";
                }
                $where[] = "e.event_date <= ?";
                $params[] = date("Y-m-d H:i:s", strtotime($dateTo));
            }
        }

        // filter: price range
        foreach (["min_price" => ">=", "max_price" => "<="] as $field => $op) {
            if (isset($_GET[$field])) {
                if (!is_numeric($_GET[$field]) || $_GET[$field] < 0) {
                    $errors[] = ["field" => $field, "message" => "$field must be a number >= 0"];
                } else {
                    $where[] = "e.ticket_price $op ?";
                    $params[] = $_GET[$field];
                }
            }
        }

        // sorting (whitelist, so user input never goes straight into SQL)
        $sortColumns = [
            "event_date" => "e.event_date",
            "ticket_price" => "e.ticket_price",
            "title" => "e.title"
        ];
        $sort = $_GET["sort"] ?? "event_date";
        $order = strtolower($_GET["order"] ?? "asc");

        if (!isset($sortColumns[$sort])) {
            $errors[] = ["field" => "sort", "message" => "sort must be event_date, ticket_price or title"];
        }
        if (!in_array($order, ["asc", "desc"], true)) {
            $errors[] = ["field" => "order", "message" => "order must be asc or desc"];
        }

        // pagination
        $page = 1;
        $limit = 10;

        if (isset($_GET["page"])) {
            $page = filter_var($_GET["page"], FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]]);
            if ($page === false) {
                $errors[] = ["field" => "page", "message" => "page must be an integer >= 1"];
                $page = 1;
            }
        }
        if (isset($_GET["limit"])) {
            $limit = filter_var($_GET["limit"], FILTER_VALIDATE_INT, ["options" => ["min_range" => 1, "max_range" => 100]]);
            if ($limit === false) {
                $errors[] = ["field" => "limit", "message" => "limit must be an integer from 1 to 100"];
                $limit = 10;
            }
        }

        if (!empty($errors)) {
            sendJson(400, ["success" => false, "message" => "Validation failed", "errors" => $errors]);
        }

        $whereSql = $where ? "WHERE " . implode(" AND ", $where) : "";
        $offset = ($page - 1) * $limit;

        // total for pagination
        $countStmt = $conn->prepare("SELECT COUNT(*) FROM events e $whereSql");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        // $sort, $order, $limit, $offset are validated above, so they are safe to insert
        $sql = "SELECT e.event_id, e.title, e.description, e.venue_id, v.venue_name,
                       e.organiser_id, e.event_date, e.ticket_price,
                       e.total_tickets, e.available_tickets, e.status
                FROM events e
                JOIN venues v ON e.venue_id = v.venue_id
                $whereSql
                ORDER BY {$sortColumns[$sort]} " . strtoupper($order) . ", e.event_id ASC
                LIMIT $limit OFFSET $offset";

        $stmt = $conn->prepare($sql);
        $stmt->execute($params);

        sendJson(200, [
            "success" => true,
            "message" => "Events retrieved",
            "data" => $stmt->fetchAll(PDO::FETCH_ASSOC),
            "pagination" => [
                "page" => (int) $page,
                "limit" => (int) $limit,
                "total" => $total,
                "totalPages" => (int) ceil($total / $limit)
            ]
        ]);
    }

    /* =====================================
       POST - CREATE EVENT
    ===================================== */

    if ($method === "POST") {

        requireRole($currentUser, $manageRoles);   // 403 for Administrator and Customer

        $data = json_decode(file_get_contents("php://input"), true);

        if (!is_array($data)) {
            sendJson(400, ["success" => false, "message" => "Invalid JSON in request body"]);
        }

        $errors = validateEventData($data, false);

        if (!empty($errors)) {
            sendJson(400, ["success" => false, "message" => "Validation failed", "errors" => $errors]);
        }

        // Venue must exist, be available, and be big enough
        $venue = fetchVenue($conn, intval($data["venue_id"]));

        if (!$venue) {
            sendJson(404, ["success" => false, "message" => "Venue not found"]);
        }
        if ($venue["status"] !== "Available") {
            sendJson(409, ["success" => false, "message" => "Venue is not available"]);
        }
        if (intval($data["total_tickets"]) > intval($venue["capacity"])) {
            sendJson(400, [
                "success" => false,
                "message" => "Validation failed",
                "errors" => [[
                    "field" => "total_tickets",
                    "message" => "total_tickets cannot exceed venue capacity (" . $venue["capacity"] . ")"
                ]]
            ]);
        }

        $stmt = $conn->prepare(
            "INSERT INTO events
             (title, description, venue_id, organiser_id, event_date,
              ticket_price, total_tickets, available_tickets, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );

        $totalTickets = intval($data["total_tickets"]);

        // organiser_id always comes from the JWT, never from the request body
        
$stmt->execute([
    trim($data["title"]),
    $data["description"] ?? null,
    intval($data["venue_id"]),
    $currentUser->user_id,
    date("Y-m-d H:i:s", strtotime($data["event_date"])),
    $data["ticket_price"],
    $totalTickets,
    $totalTickets,
    $data["status"] ?? "draft"
]);

// Save the new event ID immediately
$newEventId = (int) $conn->lastInsertId();

// Synchronize venue status
syncVenueStatusForEvent(
    $conn,
    $newEventId,
    intval($data["venue_id"]),
    $data["status"] ?? "draft"
);

// Return the newly created event
sendJson(201, [
    "success" => true,
    "message" => "Event created",
    "data" => fetchEvent($conn, $newEventId)
]);
    }

    /* =====================================
       PUT - UPDATE EVENT
    ===================================== */

    if ($method === "PUT") {

        requireRole($currentUser, $manageRoles);

        if (!$id) {
            sendJson(400, ["success" => false, "message" => "Event id is required"]);
        }

        $event = fetchEvent($conn, $id);

        if (!$event) {
            sendJson(404, ["success" => false, "message" => "Event not found"]);
        }

        // Organisers manage their own events; staff can manage all events.
        if (
            $currentUser->role === "Organiser" &&
            intval($event["organiser_id"]) !== intval($currentUser->user_id)
        ) {
            sendJson(403, ["success" => false, "message" => "You can only modify your own events"]);
        }

        $data = json_decode(file_get_contents("php://input"), true);

        if (!is_array($data)) {
            sendJson(400, ["success" => false, "message" => "Invalid JSON in request body"]);
        }

        $errors = validateEventData($data, true, $event["event_date"]);

        if (!empty($errors)) {
            sendJson(400, ["success" => false, "message" => "Validation failed", "errors" => $errors]);
        }

        $sets = [];
        $params = [];

        // venue and capacity checks
        $newVenueId = array_key_exists("venue_id", $data) ? intval($data["venue_id"]) : intval($event["venue_id"]);
        $newTotal = array_key_exists("total_tickets", $data) ? intval($data["total_tickets"]) : intval($event["total_tickets"]);

        if (array_key_exists("venue_id", $data) || array_key_exists("total_tickets", $data)) {

            $venue = fetchVenue($conn, $newVenueId);

            if (!$venue) {
                sendJson(404, ["success" => false, "message" => "Venue not found"]);
            }
            $venueChanged = array_key_exists("venue_id", $data) &&
                intval($event["venue_id"]) !== $newVenueId;
            if ($venueChanged && $venue["status"] !== "Available") {
                sendJson(409, ["success" => false, "message" => "Venue is not available"]);
            }
            if ($newTotal > intval($venue["capacity"])) {
                sendJson(400, [
                    "success" => false,
                    "message" => "Validation failed",
                    "errors" => [[
                        "field" => "total_tickets",
                        "message" => "total_tickets cannot exceed venue capacity (" . $venue["capacity"] . ")"
                    ]]
                ]);
            }
        }

        // total_tickets cannot go below tickets already booked
        if (array_key_exists("total_tickets", $data)) {

            $booked = activeBookedTickets($conn, $id);

            if ($newTotal < $booked) {
                sendJson(409, [
                    "success" => false,
                    "message" => "total_tickets cannot be lower than tickets already booked ($booked)"
                ]);
            }

            $sets[] = "total_tickets = ?";
            $params[] = $newTotal;
            $sets[] = "available_tickets = ?";
            $params[] = $newTotal - $booked;
        }

        if (array_key_exists("title", $data)) {
            $sets[] = "title = ?";
            $params[] = trim($data["title"]);
        }
        if (array_key_exists("description", $data)) {
            $sets[] = "description = ?";
            $params[] = $data["description"];
        }
        if (array_key_exists("venue_id", $data)) {
            $sets[] = "venue_id = ?";
            $params[] = $newVenueId;
        }
        if (array_key_exists("event_date", $data)) {
            $sets[] = "event_date = ?";
            $params[] = date("Y-m-d H:i:s", strtotime($data["event_date"]));
        }
        if (array_key_exists("ticket_price", $data)) {
            $sets[] = "ticket_price = ?";
            $params[] = $data["ticket_price"];
        }
        if (array_key_exists("status", $data)) {
            $sets[] = "status = ?";
            $params[] = $data["status"];
        }

        if (empty($sets)) {
            sendJson(400, ["success" => false, "message" => "No valid fields to update"]);
        }

        $params[] = $id;

        $stmt = $conn->prepare("UPDATE events SET " . implode(", ", $sets) . " WHERE event_id = ?");
        $stmt->execute($params);

        $updatedEvent = fetchEvent($conn, $id);
        if (array_key_exists("venue_id", $data) && intval($event["venue_id"]) !== intval($newVenueId)) {
            releaseVenueIfUnused($conn, intval($event["venue_id"]), $id);
        }
        syncVenueStatusForEvent($conn, $id, intval($updatedEvent["venue_id"]), $updatedEvent["status"]);

        sendJson(200, [
            "success" => true,
            "message" => "Event updated",
            "data" => $updatedEvent
        ]);
    }

    /* =====================================
       DELETE - DELETE EVENT
    ===================================== */

    if ($method === "DELETE") {

        requireRole($currentUser, $manageRoles);

        if (!$id) {
            sendJson(400, ["success" => false, "message" => "Event id is required"]);
        }

        $event = fetchEvent($conn, $id);

        if (!$event) {
            sendJson(404, ["success" => false, "message" => "Event not found"]);
        }

        if (
            $currentUser->role === "Organiser" &&
            intval($event["organiser_id"]) !== intval($currentUser->user_id)
        ) {
            sendJson(403, ["success" => false, "message" => "You can only delete your own events"]);
        }

        // Any booking record (even cancelled) blocks delete because of the foreign key
        $count = $conn->prepare("SELECT COUNT(*) FROM bookings WHERE event_id = ?");
        $count->execute([$id]);

        if ((int) $count->fetchColumn() > 0) {
            sendJson(409, [
                "success" => false,
                "message" => "Event has booking records and cannot be deleted. Set its status to 'cancelled' instead."
            ]);
        }

        $stmt = $conn->prepare("DELETE FROM events WHERE event_id = ?");
        $stmt->execute([$id]);
        releaseVenueIfUnused($conn, intval($event["venue_id"]));

        sendJson(200, ["success" => true, "message" => "Event deleted"]);
    }

    /* ---------- any other method ---------- */

    sendJson(405, ["success" => false, "message" => "Method not allowed"]);

} catch (PDOException $e) {

    // Do not show database details to the client
    sendJson(500, ["success" => false, "message" => "Internal server error"]);
}

?>
