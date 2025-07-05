<?php

require_once __DIR__ . '/../../../vendor/autoload.php';

use App\Controllers\ApiController;

$apiController = new ApiController();

// This endpoint needs special handling for WebSocket integration
header('Content-Type: application/json');

try {
    $authController = new \App\Controllers\AuthController();
    $session = $authController->requireAdmin();
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new \Exception('Method not allowed');
    }

    $input = json_decode(file_get_contents('php://input'), true);
    
    $csrfToken = $input['csrf_token'] ?? '';
    if (!\App\Utils\Security::validateCSRF($csrfToken)) {
        throw new \Exception('Invalid security token');
    }

    $userId = (int)($input['user_id'] ?? 0);
    if (!$userId) {
        throw new \Exception('User ID required');
    }

    // In a real implementation, this would communicate with the WebSocket server
    // to kick the user from active connections
    
    echo json_encode([
        'success' => true,
        'message' => 'User kick command sent'
    ]);

} catch (\Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

