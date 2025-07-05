<?php

namespace App\Controllers;

use App\Models\User;
use App\Models\Message;
use App\Services\FileManager;
use App\Utils\Security;

class ApiController
{
    private $userModel;
    private $messageModel;
    private $fileManager;
    private $authController;

    public function __construct()
    {
        $this->userModel = new User();
        $this->messageModel = new Message();
        $this->fileManager = new FileManager();
        $this->authController = new AuthController();
    }

    public function upload()
    {
        header('Content-Type: application/json');
        
        try {
            $session = $this->authController->requireAuth();
            
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new \Exception('Method not allowed');
            }

            $csrfToken = $_POST['csrf_token'] ?? '';
            if (!Security::validateCSRF($csrfToken)) {
                throw new \Exception('Invalid security token');
            }

            if (!isset($_FILES['file'])) {
                throw new \Exception('No file uploaded');
            }

            $filePath = $this->fileManager->uploadFile($_FILES['file'], $session['user_id']);
            
            echo json_encode([
                'success' => true,
                'file_path' => $filePath,
                'message' => 'File uploaded successfully'
            ]);

        } catch (\Exception $e) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    public function getMessages()
    {
        header('Content-Type: application/json');
        
        try {
            $this->authController->requireAuth();
            
            $limit = min((int)($_GET['limit'] ?? 50), 100);
            $offset = max((int)($_GET['offset'] ?? 0), 0);
            
            $messages = $this->messageModel->getMessages($limit, $offset);
            
            echo json_encode([
                'success' => true,
                'messages' => $messages
            ]);

        } catch (\Exception $e) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    public function sendMessage()
    {
        header('Content-Type: application/json');
        
        try {
            $session = $this->authController->requireAuth();
            
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new \Exception('Method not allowed');
            }

            $input = json_decode(file_get_contents('php://input'), true);
            
            $csrfToken = $input['csrf_token'] ?? '';
            if (!Security::validateCSRF($csrfToken)) {
                throw new \Exception('Invalid security token');
            }

            $content = Security::sanitizeInput($input['content'] ?? '');
            $messageType = $input['message_type'] ?? 'text';
            $filePath = $input['file_path'] ?? null;

            if (empty($content) && empty($filePath)) {
                throw new \Exception('Message content or file required');
            }

            // Rate limiting
            $clientIp = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            if (!Security::checkRateLimit($clientIp . '_message', null, null)) {
                throw new \Exception('Rate limit exceeded');
            }

            $messageId = $this->messageModel->create(
                $session['user_id'],
                $content,
                $messageType,
                $filePath
            );

            echo json_encode([
                'success' => true,
                'message_id' => $messageId,
                'message' => 'Message sent successfully'
            ]);

        } catch (\Exception $e) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    // Admin API endpoints
    public function adminUsers()
    {
        header('Content-Type: application/json');
        
        try {
            $this->authController->requireAdmin();
            
            $users = $this->userModel->getAllUsers();
            
            echo json_encode([
                'success' => true,
                'users' => $users
            ]);

        } catch (\Exception $e) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    public function adminStats()
    {
        header('Content-Type: application/json');
        
        try {
            $this->authController->requireAdmin();
            
            $stats = [
                'total_users' => $this->getTotalUsers(),
                'online_users' => count($this->userModel->getOnlineUsers()),
                'total_messages' => $this->messageModel->getMessageCount(),
                'messages_today' => $this->getMessagesToday(),
                'banned_users' => $this->getBannedUsersCount()
            ];
            
            echo json_encode([
                'success' => true,
                'stats' => $stats
            ]);

        } catch (\Exception $e) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    public function adminBanUser()
    {
        header('Content-Type: application/json');
        
        try {
            $session = $this->authController->requireAdmin();
            
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new \Exception('Method not allowed');
            }

            $input = json_decode(file_get_contents('php://input'), true);
            
            $csrfToken = $input['csrf_token'] ?? '';
            if (!Security::validateCSRF($csrfToken)) {
                throw new \Exception('Invalid security token');
            }

            $userId = (int)($input['user_id'] ?? 0);
            if (!$userId) {
                throw new \Exception('User ID required');
            }

            $this->userModel->banUser($userId, $session['user_id']);
            
            echo json_encode([
                'success' => true,
                'message' => 'User banned successfully'
            ]);

        } catch (\Exception $e) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    public function adminUnbanUser()
    {
        header('Content-Type: application/json');
        
        try {
            $session = $this->authController->requireAdmin();
            
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new \Exception('Method not allowed');
            }

            $input = json_decode(file_get_contents('php://input'), true);
            
            $csrfToken = $input['csrf_token'] ?? '';
            if (!Security::validateCSRF($csrfToken)) {
                throw new \Exception('Invalid security token');
            }

            $userId = (int)($input['user_id'] ?? 0);
            if (!$userId) {
                throw new \Exception('User ID required');
            }

            $this->userModel->unbanUser($userId, $session['user_id']);
            
            echo json_encode([
                'success' => true,
                'message' => 'User unbanned successfully'
            ]);

        } catch (\Exception $e) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    public function adminDeleteMessage()
    {
        header('Content-Type: application/json');
        
        try {
            $session = $this->authController->requireAdmin();
            
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new \Exception('Method not allowed');
            }

            $input = json_decode(file_get_contents('php://input'), true);
            
            $csrfToken = $input['csrf_token'] ?? '';
            if (!Security::validateCSRF($csrfToken)) {
                throw new \Exception('Invalid security token');
            }

            $messageId = (int)($input['message_id'] ?? 0);
            if (!$messageId) {
                throw new \Exception('Message ID required');
            }

            $this->messageModel->deleteMessage($messageId, $session['user_id']);
            
            echo json_encode([
                'success' => true,
                'message' => 'Message deleted successfully'
            ]);

        } catch (\Exception $e) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    public function adminBroadcast()
    {
        header('Content-Type: application/json');
        
        try {
            $session = $this->authController->requireAdmin();
            
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new \Exception('Method not allowed');
            }

            $input = json_decode(file_get_contents('php://input'), true);
            
            $csrfToken = $input['csrf_token'] ?? '';
            if (!Security::validateCSRF($csrfToken)) {
                throw new \Exception('Invalid security token');
            }

            $message = Security::sanitizeInput($input['message'] ?? '');
            if (empty($message)) {
                throw new \Exception('Message content required');
            }

            // Store as system message
            $this->messageModel->create(
                $session['user_id'],
                "[ADMIN BROADCAST] " . $message,
                'system',
                null,
                false
            );
            
            echo json_encode([
                'success' => true,
                'message' => 'Broadcast sent successfully'
            ]);

        } catch (\Exception $e) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    private function getTotalUsers()
    {
        $stmt = $this->userModel->db->query("SELECT COUNT(*) as count FROM users");
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result['count'];
    }

    private function getMessagesToday()
    {
        $stmt = $this->messageModel->db->query(
            "SELECT COUNT(*) as count FROM messages WHERE DATE(created_at) = DATE('now')"
        );
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result['count'];
    }

    private function getBannedUsersCount()
    {
        $stmt = $this->userModel->db->query("SELECT COUNT(*) as count FROM users WHERE is_banned = 1");
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result['count'];
    }
}

