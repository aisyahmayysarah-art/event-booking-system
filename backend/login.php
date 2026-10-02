<?php

header("Content-Type: application/json");

require_once "db.php";
require_once "../vendor/autoload.php";

use Firebase\JWT\JWT;

if ($_SERVER["REQUEST_METHOD"] !== "POST") {

    http_response_code(405);

    echo json_encode([
        "success" => false,
        "message" => "Method not allowed"
    ]);

    exit;
}

$data = json_decode(
    file_get_contents("php://input"),
    true
);

$email = trim($data["email"] ?? "");
$password = $data["password"] ?? "";

if ($email === "" || $password === "") {

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "Email and password are required"
    ]);

    exit;
}

$stmt = $conn->prepare(
    "SELECT * FROM users WHERE email = ?"
);

$stmt->execute([$email]);

$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (
    !$user ||
    !password_verify($password, $user["password"])
) {

    http_response_code(401);

    echo json_encode([
        "success" => false,
        "message" => "Invalid email or password"
    ]);

    exit;
}


/* JWT */

$secretKey = "event_booking_system_2026_super_secure_jwt_secret_key_123456789";

$issuedAt = time();

$expire = $issuedAt + 3600;


$payload = [

    "iat" => $issuedAt,

    "exp" => $expire,

    "user_id" => $user["user_id"],

    "name" => $user["name"],

    "email" => $user["email"],

    "role" => $user["role"]

];


$jwt = JWT::encode(
    $payload,
    $secretKey,
    "HS256"
);


http_response_code(200);

echo json_encode([

    "success" => true,

    "message" => "Login successful",

    "token" => $jwt,

    "user" => [

        "user_id" => $user["user_id"],

        "name" => $user["name"],

        "email" => $user["email"],

        "role" => $user["role"]

    ]

]);

?>