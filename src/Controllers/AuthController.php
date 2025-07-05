<?php

namespace App\Controllers;

use App\Models\User;
use App\Services\SessionManager;
use App\Utils\Security;
use App\Utils\Config;

class AuthController
{
    private $userModel;
    private $sessionManager;

    public function __construct()
    {
        $this->userModel = new User();
        $this->sessionManager = new SessionManager();
    }

    public function login()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            return $this->handleLogin();
        }
        
        // Check if already logged in
        $session = $this->sessionManager->validateSession();
        if ($session) {
            header('Location: /');
            exit;
        }

        $this->renderLoginForm();
    }

    private function handleLogin()
    {
        $username = Security::sanitizeInput($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $csrfToken = $_POST['csrf_token'] ?? '';

        // Validate CSRF token
        if (!Security::validateCSRF($csrfToken)) {
            $this->renderLoginForm('Invalid security token');
            return;
        }

        // Rate limiting
        $clientIp = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        if (!Security::checkRateLimit($clientIp . '_login', 5, 300)) {
            $this->renderLoginForm('Too many login attempts. Please try again later.');
            return;
        }

        // Validate input
        if (empty($username) || empty($password)) {
            $this->renderLoginForm('Username and password are required');
            return;
        }

        // Authenticate user
        $user = $this->userModel->authenticate($username, $password);
        if (!$user) {
            $this->renderLoginForm('Invalid username or password');
            return;
        }

        // Create session
        $sessionId = $this->sessionManager->createSession($user['id']);
        
        // Set secure cookie
        $cookieOptions = [
            'expires' => time() + Config::get('SESSION_LIFETIME', 3600),
            'path' => '/',
            'domain' => '',
            'secure' => isset($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Strict'
        ];
        
        setcookie('session_token', $sessionId, $cookieOptions);

        header('Location: /');
        exit;
    }

    public function logout()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $input = json_decode(file_get_contents('php://input'), true);
            $csrfToken = $input['csrf_token'] ?? '';

            if (Security::validateCSRF($csrfToken)) {
                $this->sessionManager->destroySession();
                
                // Clear cookie
                setcookie('session_token', '', [
                    'expires' => time() - 3600,
                    'path' => '/',
                    'httponly' => true,
                    'samesite' => 'Strict'
                ]);
            }

            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
            exit;
        }

        header('Location: /login.php');
        exit;
    }

    public function register()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            return $this->handleRegister();
        }

        $this->renderRegisterForm();
    }

    private function handleRegister()
    {
        $username = Security::sanitizeInput($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        $csrfToken = $_POST['csrf_token'] ?? '';

        // Validate CSRF token
        if (!Security::validateCSRF($csrfToken)) {
            $this->renderRegisterForm('Invalid security token');
            return;
        }

        // Rate limiting
        $clientIp = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        if (!Security::checkRateLimit($clientIp . '_register', 3, 3600)) {
            $this->renderRegisterForm('Too many registration attempts. Please try again later.');
            return;
        }

        // Validate input
        if (empty($username) || empty($password)) {
            $this->renderRegisterForm('Username and password are required');
            return;
        }

        if (strlen($username) < 3 || strlen($username) > 50) {
            $this->renderRegisterForm('Username must be between 3 and 50 characters');
            return;
        }

        if (strlen($password) < 8) {
            $this->renderRegisterForm('Password must be at least 8 characters long');
            return;
        }

        if ($password !== $confirmPassword) {
            $this->renderRegisterForm('Passwords do not match');
            return;
        }

        // Check if username already exists
        if ($this->userModel->findByUsername($username)) {
            $this->renderRegisterForm('Username already exists');
            return;
        }

        // Create user
        try {
            $userId = $this->userModel->create($username, $password);
            
            // Auto-login after registration
            $sessionId = $this->sessionManager->createSession($userId);
            
            $cookieOptions = [
                'expires' => time() + Config::get('SESSION_LIFETIME', 3600),
                'path' => '/',
                'domain' => '',
                'secure' => isset($_SERVER['HTTPS']),
                'httponly' => true,
                'samesite' => 'Strict'
            ];
            
            setcookie('session_token', $sessionId, $cookieOptions);

            header('Location: /');
            exit;
        } catch (\Exception $e) {
            $this->renderRegisterForm('Registration failed. Please try again.');
        }
    }

    private function renderLoginForm($error = null)
    {
        $csrfToken = Security::generateCSRF();
        include __DIR__ . '/../Views/login.php';
    }

    private function renderRegisterForm($error = null)
    {
        $csrfToken = Security::generateCSRF();
        include __DIR__ . '/../Views/register.php';
    }

    public function requireAuth()
    {
        $session = $this->sessionManager->validateSession();
        if (!$session) {
            header('Location: /login.php');
            exit;
        }
        return $session;
    }

    public function requireAdmin()
    {
        $session = $this->requireAuth();
        if (!$session['is_admin']) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Admin access required']);
            exit;
        }
        return $session;
    }
}

