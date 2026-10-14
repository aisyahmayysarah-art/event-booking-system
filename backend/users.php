<?php

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit;
}

require_once "db.php";
require_once "auth.php";

function sendUserJson($status, $body)
{
    http_response_code($status);
    echo json_encode($body);
    exit;
}

function publicUser($user)
{
    unset($user["password"]);
    return $user;
}

$currentUser = authenticateUser();
$method = $_SERVER["REQUEST_METHOD"];

// Get user ID from URL, e.g. users.php?id=2
$rawUserId = $_GET["id"] ?? null;

$userId = $rawUserId === null
    ? null
    : filter_var(
        $rawUserId,
        FILTER_VALIDATE_INT,
        ["options" => ["min_range" => 1]]
    );

try {

    // ========================================
    // GET - ALL USERS / USER BY ID
    // ========================================
    if ($method === "GET") {

        // Administrator: GET specific user by ID
        if (
            $currentUser->role === "Administrator" &&
            $rawUserId !== null
        ) {

            if ($userId === false) {
                sendUserJson(400, [
                    "success" => false,
                    "message" => "A valid user id is required."
                ]);
            }

            $stmt = $conn->prepare(
                "SELECT user_id, name, email, role
                 FROM users
                 WHERE user_id = ?"
            );

            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                sendUserJson(404, [
                    "success" => false,
                    "message" => "User not found"
                ]);
            }

            sendUserJson(200, [
                "success" => true,
                "data" => publicUser($user)
            ]);
        }

        // Administrator: GET all users
        if ($currentUser->role === "Administrator") {

            $stmt = $conn->query(
                "SELECT user_id, name, email, role
                 FROM users
                 ORDER BY user_id ASC"
            );

            sendUserJson(200, [
                "success" => true,
                "data" => array_map(
                    "publicUser",
                    $stmt->fetchAll(PDO::FETCH_ASSOC)
                )
            ]);
        }

        // Other roles: GET own profile only
        $stmt = $conn->prepare(
            "SELECT user_id, name, email, role
             FROM users
             WHERE user_id = ?"
        );

        $stmt->execute([(int) $currentUser->user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            sendUserJson(404, [
                "success" => false,
                "message" => "User not found"
            ]);
        }

        sendUserJson(200, [
            "success" => true,
            "data" => publicUser($user)
        ]);
    }

    // ========================================
    // ADMINISTRATOR AUTHORIZATION
    // ========================================
    requireRole($currentUser, ["Administrator"]);

    // Reject unsupported methods
    if (!in_array($method, ["POST", "PUT", "DELETE"], true)) {
        sendUserJson(405, [
            "success" => false,
            "message" => "Method not allowed"
        ]);
    }

    // Read JSON body for POST and PUT
    if (in_array($method, ["POST", "PUT"], true)) {

        $data = json_decode(
            file_get_contents("php://input"),
            true
        );

        if (!is_array($data)) {
            sendUserJson(400, [
                "success" => false,
                "message" => "Request body must be valid JSON."
            ]);
        }
    }

    // ========================================
    // POST - ADD USER
    // ========================================
    if ($method === "POST") {

        $name = trim((string) ($data["name"] ?? ""));
        $email = trim((string) ($data["email"] ?? ""));
        $password = (string) ($data["password"] ?? "");
        $role = (string) ($data["role"] ?? "Customer");

        if (
            $name === "" ||
            mb_strlen($name) > 100 ||
            !filter_var($email, FILTER_VALIDATE_EMAIL) ||
            strlen($password) < 6 ||
            !in_array(
                $role,
                ["Administrator", "Organiser", "Staff", "Customer"],
                true
            )
        ) {
            sendUserJson(400, [
                "success" => false,
                "message" =>
                    "Name, valid email, password (minimum 6 characters), and valid role are required."
            ]);
        }

        $stmt = $conn->prepare(
            "INSERT INTO users (name, email, password, role)
             VALUES (?, ?, ?, ?)"
        );

        $stmt->execute([
            $name,
            $email,
            password_hash($password, PASSWORD_DEFAULT),
            $role
        ]);

        $newId = $conn->lastInsertId();

        $stmt = $conn->prepare(
            "SELECT user_id, name, email, role
             FROM users
             WHERE user_id = ?"
        );

        $stmt->execute([$newId]);

        sendUserJson(201, [
            "success" => true,
            "message" => "User created",
            "data" => $stmt->fetch(PDO::FETCH_ASSOC)
        ]);
    }

    // ========================================
    // VALIDATE USER ID FOR PUT / DELETE
    // ========================================
    if ($userId === false || $userId === null) {
        sendUserJson(400, [
            "success" => false,
            "message" => "A valid user id is required."
        ]);
    }

    // ========================================
    // PUT - UPDATE USER
    // ========================================
    if ($method === "PUT") {

        $fields = [];
        $params = [];

        // Update name
        if (array_key_exists("name", $data)) {

            $name = trim((string) $data["name"]);

            if ($name === "" || mb_strlen($name) > 100) {
                sendUserJson(400, [
                    "success" => false,
                    "message" => "Name must be 1-100 characters."
                ]);
            }

            $fields[] = "name = ?";
            $params[] = $name;
        }

        // Update email
        if (array_key_exists("email", $data)) {

            $email = trim((string) $data["email"]);

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                sendUserJson(400, [
                    "success" => false,
                    "message" => "A valid email is required."
                ]);
            }

            $fields[] = "email = ?";
            $params[] = $email;
        }

        // Update role
        if (array_key_exists("role", $data)) {

            if (
                !in_array(
                    $data["role"],
                    ["Administrator", "Organiser", "Staff", "Customer"],
                    true
                )
            ) {
                sendUserJson(400, [
                    "success" => false,
                    "message" => "Invalid role."
                ]);
            }

            $ownerStmt = $conn->prepare(
                "SELECT COUNT(*)
                 FROM events
                 WHERE organiser_id = ?"
            );

            $ownerStmt->execute([$userId]);

            if (
                (int) $ownerStmt->fetchColumn() > 0 &&
                !in_array(
                    $data["role"],
                    ["Organiser", "Staff"],
                    true
                )
            ) {
                sendUserJson(409, [
                    "success" => false,
                    "message" =>
                        "This user owns events. Reassign or remove those events before changing the role from Organiser or Staff."
                ]);
            }

            $fields[] = "role = ?";
            $params[] = $data["role"];
        }

        // Update password
        if (
            array_key_exists("password", $data) &&
            $data["password"] !== ""
        ) {

            if (strlen((string) $data["password"]) < 6) {
                sendUserJson(400, [
                    "success" => false,
                    "message" =>
                        "Password must be at least 6 characters."
                ]);
            }

            $fields[] = "password = ?";
            $params[] = password_hash(
                $data["password"],
                PASSWORD_DEFAULT
            );
        }

        if (!$fields) {
            sendUserJson(400, [
                "success" => false,
                "message" => "No valid fields to update."
            ]);
        }

        $params[] = $userId;

        $stmt = $conn->prepare(
            "UPDATE users SET " .
            implode(", ", $fields) .
            " WHERE user_id = ?"
        );

        $stmt->execute($params);

        $stmt = $conn->prepare(
            "SELECT user_id, name, email, role
             FROM users
             WHERE user_id = ?"
        );

        $stmt->execute([$userId]);

        $updatedUser = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$updatedUser) {
            sendUserJson(404, [
                "success" => false,
                "message" => "User not found."
            ]);
        }

        sendUserJson(200, [
            "success" => true,
            "message" => "User updated",
            "data" => publicUser($updatedUser)
        ]);
    }

    // ========================================
    // DELETE - REMOVE USER
    // ========================================
    if ($method === "DELETE") {

        // Prevent administrator from deleting own account
        if ((int) $userId === (int) $currentUser->user_id) {
            sendUserJson(409, [
                "success" => false,
                "message" => "You cannot delete your own account."
            ]);
        }

        $stmt = $conn->prepare(
            "DELETE FROM users WHERE user_id = ?"
        );

        $stmt->execute([$userId]);

        if ($stmt->rowCount() === 0) {
            sendUserJson(404, [
                "success" => false,
                "message" => "User not found."
            ]);
        }

        sendUserJson(200, [
            "success" => true,
            "message" => "User deleted"
        ]);
    }

} catch (PDOException $e) {

    // Duplicate email
    if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
        sendUserJson(409, [
            "success" => false,
            "message" => "That email address is already in use."
        ]);
    }

    // Related records prevent deletion
    if ((int) ($e->errorInfo[1] ?? 0) === 1451) {
        sendUserJson(409, [
            "success" => false,
            "message" =>
                "This user has related records and cannot be deleted."
        ]);
    }

    error_log("User API failed: " . $e->getMessage());

    sendUserJson(500, [
        "success" => false,
        "message" => "Unable to complete the user request."
    ]);
}

