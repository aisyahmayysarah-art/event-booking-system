<?php
/**
 * venues.php - Venues resource (RESTful API)
 *
 * Endpoints (all require a JWT: Authorization: Bearer <token>)
 *
 *   GET    /backend/venues.php              List venues (pagination, search, filter, sort)
 *   GET    /backend/venues.php?id=3         Get one venue
 *   GET    /backend/venues.php/3            Get one venue (path style)
 *   POST   /backend/venues.php              Create a venue            [Administrator]
 *   PUT    /backend/venues.php?id=3         Update a venue            [Administrator]
 *   PATCH  /backend/venues.php?id=3         Partial update            [Administrator]
 *   DELETE /backend/venues.php?id=3         Delete a venue            [Administrator]
 *
 * List query parameters:
 *   page=1  limit=10 (max 100)  search=text  status=Available|Unavailable
 *   min_capacity=500  sort=venue_id|venue_name|location|capacity|status|created_at
 *   order=asc|desc
 *
 * Roles:
 *   Read   : Administrator, Organiser, Staff, Customer
 *   Write  : Administrator only
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);

/* -------------------------------------------------------------
   Headers / CORS
------------------------------------------------------------- */
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

// Pre-flight must be answered BEFORE authentication
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

/* -------------------------------------------------------------
   Helpers
------------------------------------------------------------- */
const VENUE_STATUSES = ['Available', 'In Use', 'Unavailable'];
const SORTABLE_FIELDS = ['venue_id', 'venue_name', 'location', 'capacity', 'status', 'created_at'];

function respond(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

function respondError(int $status, string $message, array $errors = []): void
{
    $payload = ["success" => false, "message" => $message];
    if (!empty($errors)) {
        $payload["errors"] = $errors;
    }
    respond($status, $payload);
}

/** Logging middleware: one line per request in backend/logs/api.log */
function registerRequestLogger(): void
{
    $start = microtime(true);
    register_shutdown_function(function () use ($start) {
        global $currentUser;
        $dir = __DIR__ . '/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $line = sprintf(
            "[%s] %s %s | user=%s role=%s | status=%d | %.1fms | ip=%s\n",
            date('Y-m-d H:i:s'),
            $_SERVER['REQUEST_METHOD'] ?? '-',
            $_SERVER['REQUEST_URI'] ?? '-',
            $currentUser->user_id ?? 'anonymous',
            $currentUser->role ?? '-',
            http_response_code(),
            (microtime(true) - $start) * 1000,
            $_SERVER['REMOTE_ADDR'] ?? '-'
        );
        @file_put_contents($dir . '/api.log', $line, FILE_APPEND | LOCK_EX);
    });
}

/** Centralised error handling: nothing leaks to the client */
set_exception_handler(function (Throwable $e) {
    error_log('venues.php error: ' . $e->getMessage());
    respondError(500, "Internal server error.");
});

function readJsonBody(): array
{
    $raw = file_get_contents("php://input");
    if ($raw === false || trim($raw) === '') {
        respondError(400, "Request body is required.");
    }
    $data = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
        respondError(400, "Request body must be valid JSON.");
    }
    return $data;
}

/** Get the venue id from ?id=, /venues.php/3 or the JSON body (venue_id / id) */
function resolveVenueId(?array $body = null): ?int
{
    $candidate = $_GET['id'] ?? null;

    if ($candidate === null && !empty($_SERVER['PATH_INFO'])) {
        $candidate = trim($_SERVER['PATH_INFO'], '/');
    }
    if ($candidate === null && $body !== null) {
        $candidate = $body['venue_id'] ?? $body['id'] ?? null;
    }
    if ($candidate === null || $candidate === '') {
        return null;
    }
    if (is_int($candidate)) {
        $candidate = (string)$candidate;
    }
    if (!is_string($candidate) || !ctype_digit($candidate) || (int)$candidate < 1) {
        respondError(400, "Venue ID must be a positive integer.");
    }
    return (int)$candidate;
}

function isWholeNumber($value): bool
{
    return is_int($value) || (is_string($value) && ctype_digit($value));
}

/**
 * Validate venue input.
 * $partial = true  -> only validate fields that are present (PUT/PATCH)
 * $partial = false -> required fields must exist (POST)
 */
function validateVenue(array $data, bool $partial): array
{
    $errors = [];
    $clean = [];

    // venue_name
    if (array_key_exists('venue_name', $data)) {
        $name = $data['venue_name'];
        if (!is_string($name) || trim($name) === '') {
            $errors['venue_name'] = "Venue name is required.";
        } elseif (mb_strlen(trim($name)) > 100) {
            $errors['venue_name'] = "Venue name must not exceed 100 characters.";
        } else {
            $clean['venue_name'] = trim($name);
        }
    } elseif (!$partial) {
        $errors['venue_name'] = "Venue name is required.";
    }

    // location
    if (array_key_exists('location', $data)) {
        $loc = $data['location'];
        if (!is_string($loc) || trim($loc) === '') {
            $errors['location'] = "Location is required.";
        } elseif (mb_strlen(trim($loc)) > 255) {
            $errors['location'] = "Location must not exceed 255 characters.";
        } else {
            $clean['location'] = trim($loc);
        }
    } elseif (!$partial) {
        $errors['location'] = "Location is required.";
    }

    // capacity
    if (array_key_exists('capacity', $data)) {
        $cap = $data['capacity'];
        if (!isWholeNumber($cap) || (int)$cap < 1) {
            $errors['capacity'] = "Capacity must be a whole number greater than 0.";
        } elseif ((int)$cap > 1000000) {
            $errors['capacity'] = "Capacity must not exceed 1,000,000.";
        } else {
            $clean['capacity'] = (int)$cap;
        }
    } elseif (!$partial) {
        $errors['capacity'] = "Capacity is required.";
    }

    // status
    if (array_key_exists('status', $data)) {
        if (!is_string($data['status']) || !in_array($data['status'], VENUE_STATUSES, true)) {
            $errors['status'] = "Status must be one of: " . implode(', ', VENUE_STATUSES) . ".";
        } else {
            $clean['status'] = $data['status'];
        }
    } elseif (!$partial) {
        $clean['status'] = 'Available';
    }

    return [$clean, $errors];
}

function findVenue(PDO $conn, int $id): ?array
{
    $stmt = $conn->prepare("SELECT * FROM venues WHERE venue_id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function formatVenue(array $row): array
{
    $row['venue_id'] = (int)$row['venue_id'];
    $row['capacity'] = (int)$row['capacity'];
    return $row;
}

/** Same name at the same location is treated as a duplicate */
function venueExists(PDO $conn, string $name, string $location, ?int $excludeId = null): bool
{
    $sql = "SELECT COUNT(*) FROM venues WHERE venue_name = ? AND location = ?";
    $params = [$name, $location];
    if ($excludeId !== null) {
        $sql .= " AND venue_id <> ?";
        $params[] = $excludeId;
    }
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    return (int)$stmt->fetchColumn() > 0;
}

/* -------------------------------------------------------------
   Authentication (middleware)
------------------------------------------------------------- */
$currentUser = null;
registerRequestLogger();

$method = $_SERVER['REQUEST_METHOD'];

if (!in_array($method, ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], true)) {
    header("Allow: GET, POST, PUT, PATCH, DELETE, OPTIONS");
    respondError(405, "Method not allowed.");
}

$currentUser = authenticateUser();   // exits with 401 if token is missing/invalid

/* -------------------------------------------------------------
   GET - list or single venue
------------------------------------------------------------- */
if ($method === 'GET') {
    requireRole($currentUser, ['Administrator', 'Organiser', 'Staff', 'Customer']);

    $id = resolveVenueId();

    // ---- single venue
    if ($id !== null) {
        $venue = findVenue($conn, $id);
        if (!$venue) {
            respondError(404, "Venue not found.");
        }
        respond(200, ["success" => true, "data" => formatVenue($venue)]);
    }

    // ---- list venues
    $errors = [];

    $page = 1;
    if (isset($_GET['page'])) {
        if (!isWholeNumber($_GET['page']) || (int)$_GET['page'] < 1) {
            $errors['page'] = "Page must be a positive integer.";
        } else {
            $page = (int)$_GET['page'];
        }
    }

    $limit = 10;
    if (isset($_GET['limit'])) {
        if (!isWholeNumber($_GET['limit']) || (int)$_GET['limit'] < 1 || (int)$_GET['limit'] > 100) {
            $errors['limit'] = "Limit must be between 1 and 100.";
        } else {
            $limit = (int)$_GET['limit'];
        }
    }

    $status = $_GET['status'] ?? null;
    if ($status !== null && $status !== '' && !in_array($status, VENUE_STATUSES, true)) {
        $errors['status'] = "Status must be one of: " . implode(', ', VENUE_STATUSES) . ".";
    }

    $minCapacity = null;
    if (isset($_GET['min_capacity']) && $_GET['min_capacity'] !== '') {
        if (!isWholeNumber($_GET['min_capacity'])) {
            $errors['min_capacity'] = "min_capacity must be a whole number.";
        } else {
            $minCapacity = (int)$_GET['min_capacity'];
        }
    }

    $sort = $_GET['sort'] ?? 'venue_id';
    if (!in_array($sort, SORTABLE_FIELDS, true)) {
        $errors['sort'] = "Sort must be one of: " . implode(', ', SORTABLE_FIELDS) . ".";
    }

    $order = strtolower($_GET['order'] ?? 'asc');
    if (!in_array($order, ['asc', 'desc'], true)) {
        $errors['order'] = "Order must be 'asc' or 'desc'.";
    }

    if (!empty($errors)) {
        respondError(400, "Invalid query parameters.", $errors);
    }

    $where = [];
    $params = [];

    $search = trim((string)($_GET['search'] ?? ''));
    if ($search !== '') {
        $like = '%' . addcslashes($search, '%_\\') . '%';
        $where[] = "(venue_name LIKE :search_name OR location LIKE :search_location)";
        $params[':search_name'] = $like;
        $params[':search_location'] = $like;
    }
    if ($status !== null && $status !== '') {
        $where[] = "status = :status";
        $params[':status'] = $status;
    }
    if ($minCapacity !== null) {
        $where[] = "capacity >= :min_capacity";
        $params[':min_capacity'] = $minCapacity;
    }

    $whereSql = $where ? ("WHERE " . implode(" AND ", $where)) : "";

    // Total count
    $countStmt = $conn->prepare("SELECT COUNT(*) FROM venues $whereSql");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    // Page of results ($sort/$order are whitelisted above, so safe to interpolate)
    $offset = ($page - 1) * $limit;
    $sql = "SELECT * FROM venues $whereSql ORDER BY $sort " . strtoupper($order) . ", venue_id ASC LIMIT :limit OFFSET :offset";
    $stmt = $conn->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $rows = array_map('formatVenue', $stmt->fetchAll(PDO::FETCH_ASSOC));

    respond(200, [
        "success" => true,
        "data" => $rows,
        "pagination" => [
            "page" => $page,
            "limit" => $limit,
            "total" => $total,
            "total_pages" => (int)ceil($total / $limit)
        ]
    ]);
}

/* -------------------------------------------------------------
   Write operations: Administrator only
------------------------------------------------------------- */
requireRole($currentUser, ['Administrator']);

/* -------------------------------------------------------------
   POST - create venue
------------------------------------------------------------- */
if ($method === 'POST') {
    $data = readJsonBody();
    [$clean, $errors] = validateVenue($data, false);

    if (!empty($errors)) {
        respondError(422, "Validation failed.", $errors);
    }
    if (venueExists($conn, $clean['venue_name'], $clean['location'])) {
        respondError(409, "A venue with this name already exists at this location.");
    }

    $stmt = $conn->prepare(
        "INSERT INTO venues (venue_name, location, capacity, status) VALUES (?, ?, ?, ?)"
    );
    $stmt->execute([$clean['venue_name'], $clean['location'], $clean['capacity'], $clean['status']]);
    $newId = (int)$conn->lastInsertId();

    header("Location: venues.php?id=" . $newId);
    respond(201, [
        "success" => true,
        "message" => "Venue created successfully.",
        "data" => formatVenue(findVenue($conn, $newId))
    ]);
}

/* -------------------------------------------------------------
   PUT / PATCH - update venue
------------------------------------------------------------- */
if ($method === 'PUT' || $method === 'PATCH') {
    $data = readJsonBody();
    $id = resolveVenueId($data);

    if ($id === null) {
        respondError(400, "Venue ID is required (use ?id=, /venues.php/{id} or venue_id in the body).");
    }

    $existing = findVenue($conn, $id);
    if (!$existing) {
        respondError(404, "Venue not found.");
    }

    [$clean, $errors] = validateVenue($data, true);
    if (!empty($errors)) {
        respondError(422, "Validation failed.", $errors);
    }
    if (empty($clean)) {
        respondError(422, "No valid fields supplied. Allowed: venue_name, location, capacity, status.");
    }
    if (array_key_exists('status', $clean)) {
        $activeStmt = $conn->prepare(
            "SELECT COUNT(*) FROM events
             WHERE venue_id = ? AND status NOT IN ('cancelled', 'completed')"
        );
        $activeStmt->execute([$id]);
        if ((int) $activeStmt->fetchColumn() > 0 && $clean['status'] !== 'In Use') {
            respondError(409, "This venue is assigned to an active event and must remain In Use.");
        }
    }

    $newName = $clean['venue_name'] ?? $existing['venue_name'];
    $newLocation = $clean['location'] ?? $existing['location'];
    if (venueExists($conn, $newName, $newLocation, $id)) {
        respondError(409, "A venue with this name already exists at this location.");
    }

    $setParts = [];
    $values = [];
    foreach ($clean as $column => $value) {      // $column keys are fixed by validateVenue()
        $setParts[] = "$column = ?";
        $values[] = $value;
    }
    $values[] = $id;

    $stmt = $conn->prepare("UPDATE venues SET " . implode(", ", $setParts) . " WHERE venue_id = ?");
    $stmt->execute($values);

    respond(200, [
        "success" => true,
        "message" => "Venue updated successfully.",
        "data" => formatVenue(findVenue($conn, $id))
    ]);
}

/* -------------------------------------------------------------
   DELETE - remove venue
------------------------------------------------------------- */
if ($method === 'DELETE') {
    $id = resolveVenueId();

    if ($id === null) {
        respondError(400, "Venue ID is required (use ?id= or /venues.php/{id}).");
    }
    if (!findVenue($conn, $id)) {
        respondError(404, "Venue not found.");
    }

    // Business rule: a venue that still has events cannot be deleted
    $check = $conn->prepare("SELECT COUNT(*) FROM events WHERE venue_id = ?");
    $check->execute([$id]);
    if ((int)$check->fetchColumn() > 0) {
        respondError(409, "Cannot delete this venue because events are still assigned to it. Reassign or remove those events first, or set the venue to Unavailable.");
    }

    try {
        $stmt = $conn->prepare("DELETE FROM venues WHERE venue_id = ?");
        $stmt->execute([$id]);
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {         // foreign key constraint
            respondError(409, "Cannot delete this venue because it is referenced by other records.");
        }
        throw $e;
    }

    respond(200, [
        "success" => true,
        "message" => "Venue #{$id} deleted successfully."
    ]);
}
