<?php
class Auth {
    public static function checkHR() {
        if (!isset($_SESSION['hr']) || $_SESSION['hr'] !== true) {
            header('Location: ../../login.php');
            exit();
        }
    }

    public static function getUserId() {
        return $_SESSION['user_id'] ?? null;
    }
}
