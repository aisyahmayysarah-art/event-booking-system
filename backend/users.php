<?php

header("Content-Type: application/json");

require_once "db.php";
require_once "auth.php";

// Check JWT token
$currentUser = authenticateUser();

// Only Administrator can manage users

requireRole($currentUser, ["Administrator"]);

$method = $_SERVER["REQUEST_METHOD"];

$id = isset($_GET["id"])
    ? intval($_GET["id"])
    : null;


/* =====================================
   GET - VIEW USER / ALL USERS
===================================== */

if ($method === "GET") {

    // GET ONE USER
    if ($id) {

        $stmt = $conn->prepare(
            "SELECT user_id, name, email, role
             FROM users
             WHERE user_id = ?"
        );

        $stmt->execute([$id]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {

            http_response_code(404);

            echo json_encode([
                "success" => false,
                "message" => "User not found"
            ]);

            exit;
        }

        echo json_encode([
            "success" => true,
            "data" => $user
        ]);

        exit;
    }


    // GET ALL USERS
    $stmt = $conn->query(
        "SELECT user_id, name, email, role
         FROM users
         ORDER BY user_id ASC"
    );

    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "success" => true,
        "data" => $users
    ]);

    exit;
}


/* =====================================
   POST - ADD USER
===================================== */

if ($method === "POST") {

    $data = json_decode(
        file_get_contents("php://input"),
        true
    );

    $name = trim($data["name"] ?? "");
    $email = trim($data["email"] ?? "");
    $password = $data["password"] ?? "";
    $role = $data["role"] ?? "Customer";


    // Required fields
    if ($name === "" || $email === "" || $password === "") {

        http_response_code(400);

        echo json_encode([
            "success" => false,
            "message" => "Name, email and password are required"
        ]);

        exit;
    }


    // Email validation
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        http_response_code(400);

        echo json_encode([
            "success" => false,
            "message" => "Invalid email format"
        ]);

        exit;
    }


    // Role validation
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


    // Check duplicate email
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


    // Insert
    $stmt = $conn->prepare(
        "INSERT INTO users
         (name, email, password, role)
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
        "message" => "User added successfully",
        "user_id" => $conn->lastInsertId()
    ]);

    exit;
}


/* =====================================
   PUT - UPDATE USER
===================================== */

if ($method === "PUT") {

    if (!$id) {

        http_response_code(400);

        echo json_encode([
            "success" => false,
            "message" => "User ID is required"
        ]);

        exit;
    }


    // Check user exists
    $check = $conn->prepare(
        "SELECT user_id FROM users WHERE user_id = ?"
    );

    $check->execute([$id]);

    if (!$check->fetch()) {

        http_response_code(404);

        echo json_encode([
            "success" => false,
            "message" => "User not found"
        ]);

        exit;
    }


    $data = json_decode(
        file_get_contents("php://input"),
        true
    );


    $name = trim($data["name"] ?? "");
    $email = trim($data["email"] ?? "");
    $role = $data["role"] ?? "";


    if ($name === "" || $email === "" || $role === "") {

        http_response_code(400);

        echo json_encode([
            "success" => false,
            "message" => "Name, email and role are required"
        ]);

        exit;
    }


    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        http_response_code(400);

        echo json_encode([
            "success" => false,
            "message" => "Invalid email format"
        ]);

        exit;
    }


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


    // Prevent duplicate email belonging to another user
    $emailCheck = $conn->prepare(
        "SELECT user_id
         FROM users
         WHERE email = ?
         AND user_id != ?"
    );

    $emailCheck->execute([
        $email,
        $id
    ]);

    if ($emailCheck->fetch()) {

        http_response_code(409);

        echo json_encode([
            "success" => false,
            "message" => "Email already exists"
        ]);

        exit;
    }


    $stmt = $conn->prepare(
        "UPDATE users
         SET name = ?,
             email = ?,
             role = ?
         WHERE user_id = ?"
    );

    $stmt->execute([
        $name,
        $email,
        $role,
        $id
    ]);


    echo json_encode([
        "success" => true,
        "message" => "User updated successfully"
    ]);

    exit;
}


/* =====================================
   DELETE - DELETE USER
===================================== */

if ($method === "DELETE") {

    if (!$id) {

        http_response_code(400);

        echo json_encode([
            "success" => false,
            "message" => "User ID is required"
        ]);

        exit;
    }


    // Check user exists
    $check = $conn->prepare(
        "SELECT user_id FROM users WHERE user_id = ?"
    );

    $check->execute([$id]);

    if (!$check->fetch()) {

        http_response_code(404);

        echo json_encode([
            "success" => false,
            "message" => "User not found"
        ]);

        exit;
    }


    $stmt = $conn->prepare(
        "DELETE FROM users WHERE user_id = ?"
    );

    $stmt->execute([$id]);


    echo json_encode([
        "success" => true,
        "message" => "User deleted successfully"
    ]);

    exit;
}


/* =====================================
   INVALID METHOD
===================================== */

http_response_code(405);

echo json_encode([
    "success" => false,
    "message" => "Method not allowed"
]);

?>