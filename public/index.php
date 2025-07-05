<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Controllers\AuthController;
use App\Utils\Security;

// Start session and load configuration
$authController = new AuthController();

// Require authentication
$session = $authController->requireAuth();

// Get user data
$userModel = new \App\Models\User();
$user = $userModel->findById($session['user_id']);

if (!$user) {
    header('Location: /login.php');
    exit;
}

// Generate CSRF token
$csrfToken = Security::generateCSRF();

// Include the chat view
include __DIR__ . '/../src/Views/chat.php';

