<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$userId = $_SESSION["user_id"] ?? null;

if (
    !is_scalar($userId) ||
    filter_var($userId, FILTER_VALIDATE_INT) === false ||
    (int)$userId < 1
) {
    header("Location: /Llbrarymanagementsystem/login.php");
    exit();
}

?>
