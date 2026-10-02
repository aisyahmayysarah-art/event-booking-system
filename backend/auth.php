<?php

require_once "../vendor/autoload.php";

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

function authenticateUser()
{
    $headers = getallheaders();

    $authHeader = $headers["Authorization"] ?? "";

    if (empty($authHeader)) {

        http_response_code(401);

        echo json_encode([
            "success" => false,
            "message" => "Authorization token is required"
        ]);

        exit;
    }

    if (!preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {

        http_response_code(401);

        echo json_encode([
            "success" => false,
            "message" => "Invalid authorization format"
        ]);

        exit;
    }

    $token = $matches[1];

    // MUST be the same key used in login.php
    $secretKey =
        "event_booking_system_2026_super_secure_jwt_secret_key_123456789";

    try {

        $decoded = JWT::decode(
            $token,
            new Key($secretKey, "HS256")
        );

        return $decoded;

    } catch (Exception $e) {

        http_response_code(401);

        echo json_encode([
            "success" => false,
            "message" => "Invalid or expired token"
        ]);

        exit;
    }
}

function requireRole($user, $allowedRoles)
{
    if (!in_array($user->role, $allowedRoles)) {

        http_response_code(403);

        echo json_encode([
            "success" => false,
            "message" => "Access denied. Insufficient permission."
        ]);

        exit;
    }
}
?>