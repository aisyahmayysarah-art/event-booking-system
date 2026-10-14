<?php

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: POST, OPTIONS");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit;
}

require_once __DIR__ . "/db.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);

    echo json_encode([
        "success" => false,
        "message" => "Method not allowed. Please use POST."
    ]);
    exit;
}

/*
|--------------------------------------------------------------------------
| Read JSON Request
|--------------------------------------------------------------------------
*/

$data = json_decode(
    file_get_contents("php://input"),
    true
);

if (!is_array($data)) {
    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "Invalid JSON request body."
    ]);
    exit;
}

/*
|--------------------------------------------------------------------------
| Get Registration Data
|--------------------------------------------------------------------------
*/

$name = trim((string) ($data["name"] ?? ""));
$email = trim((string) ($data["email"] ?? ""));
$password = (string) ($data["password"] ?? "");

/*
|--------------------------------------------------------------------------
| Validate Required Fields
|--------------------------------------------------------------------------
*/

if ($name === "" || $email === "" || $password === "") {
    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "Name, email and password are required."
    ]);
    exit;
}

/*
|--------------------------------------------------------------------------
| Validate Name
|--------------------------------------------------------------------------
*/

if (strlen($name) < 2 || strlen($name) > 100) {
    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "Name must be between 2 and 100 characters."
    ]);
    exit;
}

/*
|--------------------------------------------------------------------------
| Validate Email
|--------------------------------------------------------------------------
*/

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "Please enter a valid email address."
    ]);
    exit;
}

/*
|--------------------------------------------------------------------------
| Validate Password
|--------------------------------------------------------------------------
*/

if (strlen($password) < 6) {
    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "Password must contain at least 6 characters."
    ]);
    exit;
}

try {

    /*
    |--------------------------------------------------------------------------
    | Check Duplicate Email
    |--------------------------------------------------------------------------
    */

    $checkStmt = $conn->prepare(
        "SELECT user_id
         FROM users
         WHERE email = ?
         LIMIT 1"
    );

    $checkStmt->execute([$email]);

    if ($checkStmt->fetch(PDO::FETCH_ASSOC)) {
        http_response_code(409);

        echo json_encode([
            "success" => false,
            "message" => "An account with this email already exists."
        ]);
        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | Hash Password
    |--------------------------------------------------------------------------
    */

    $hashedPassword = password_hash(
        $password,
        PASSWORD_DEFAULT
    );

    if ($hashedPassword === false) {
        throw new RuntimeException(
            "Unable to hash password."
        );
    }

    /*
    |--------------------------------------------------------------------------
    | IMPORTANT:
    | Public registration ALWAYS creates a Customer.
    |
    | Do not accept role from the frontend because a public user
    | must not be able to register as Administrator, Organiser or Staff.
    |--------------------------------------------------------------------------
    */

    $role = "Customer";

    /*
    |--------------------------------------------------------------------------
    | Insert Customer
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare(
        "INSERT INTO users
            (name, email, password, role)
         VALUES
            (?, ?, ?, ?)"
    );

    $stmt->execute([
        $name,
        $email,
        $hashedPassword,
        $role
    ]);

    $userId = (int) $conn->lastInsertId();

    /*
    |--------------------------------------------------------------------------
    | Success
    |--------------------------------------------------------------------------
    */

    http_response_code(201);

    echo json_encode([
        "success" => true,
        "message" => "Registration successful. You can now log in.",
        "user" => [
            "user_id" => $userId,
            "name" => $name,
            "email" => $email,
            "role" => $role
        ]
    ]);

} catch (PDOException $e) {

    error_log(
        "Customer registration database error: " .
        $e->getMessage()
    );

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Unable to register account."
    ]);

} catch (Throwable $e) {

    error_log(
        "Customer registration error: " .
        $e->getMessage()
    );

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Unable to register account."
    ]);
}

exit;
?>