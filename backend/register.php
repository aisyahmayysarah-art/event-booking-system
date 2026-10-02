<?php

header("Content-Type: application/json");

require_once "db.php";

// Only allow POST request
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);

    echo json_encode([
        "success" => false,
        "message" => "Method not allowed"
    ]);

    exit;
}

// Get JSON data
$data = json_decode(
    file_get_contents("php://input"),
    true
);

// Get input
$name = trim($data["name"] ?? "");
$email = trim($data["email"] ?? "");
$password = $data["password"] ?? "";
$role = $data["role"] ?? "Customer";

// Check empty fields
if ($name === "" || $email === "" || $password === "") {

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "Name, email and password are required"
    ]);

    exit;
}

// Check email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "Invalid email format"
    ]);

    exit;
}

// Allowed roles
$allowedRoles = [
    "Administrator",
    "Organiser",
    "Staff",
    "Customer"
];

if (!in_array($role, $allowedRoles)) {

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "Invalid role"
    ]);

    exit;
}

// Check existing email
$check = $conn->prepare(
    "SELECT user_id FROM users WHERE email = ?"
);

$check->execute([$email]);

if ($check->fetch()) {

    http_response_code(409);

    echo json_encode([
        "success" => false,
        "message" => "Email already exists"
    ]);

    exit;
}

// Hash password
$hashedPassword = password_hash(
    $password,
    PASSWORD_DEFAULT
);

// Insert user
$stmt = $conn->prepare(
    "INSERT INTO users (name, email, password, role)
     VALUES (?, ?, ?, ?)"
);

$stmt->execute([
    $name,
    $email,
    $hashedPassword,
    $role
]);

http_response_code(201);

echo json_encode([
    "success" => true,
    "message" => "User registered successfully",
    "user_id" => $conn->lastInsertId()
]);

?>