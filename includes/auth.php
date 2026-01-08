<?php
session_start();

function isAuthenticated() {
    return isset($_SESSION['user_id']) || isset($_SESSION['authenticated']);
}

function requireLogin() {
    if (!isAuthenticated()) {
        header('Location: login.php');
        exit;
    }
}

function getCurrentUser() {
    return $_SESSION['user'] ?? ['username' => 'admin'];
}
