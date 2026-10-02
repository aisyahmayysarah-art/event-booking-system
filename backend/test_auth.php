<?php

header("Content-Type: application/json");

require_once "auth.php";

$user = authenticateUser();

echo json_encode([
    "success" => true,
    "message" => "Token is valid",

    "user" => [
        "user_id" => $user->user_id,
        "name" => $user->name,
        "email" => $user->email,
        "role" => $user->role
    ]
]);

?>