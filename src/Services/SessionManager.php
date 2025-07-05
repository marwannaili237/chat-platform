<?php

namespace App\Services;

use App\Models\Database;
use App\Utils\Security;
use App\Utils\Config;

class SessionManager
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->configureSession();
    }

    private function configureSession()
    {
        // Secure session configuration
        ini_set('session.cookie_httponly', 1);
        ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) ? 1 : 0);
        ini_set('session.cookie_samesite', 'Strict');
        ini_set('session.use_strict_mode', 1);
        ini_set('session.gc_maxlifetime', Config::get('SESSION_LIFETIME', 3600));
        
        session_start();
    }

    public function createSession($userId)
    {
        $sessionId = Security::generateToken(64);
        $expiresAt = date('Y-m-d H:i:s', time() + Config::get('SESSION_LIFETIME', 3600));
        
        $sql = "INSERT INTO sessions (id, user_id, ip_address, user_agent, expires_at) 
                VALUES (?, ?, ?, ?, ?)";
        
        $this->db->query($sql, [
            $sessionId,
            $userId,
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? '',
            $expiresAt
        ]);

        $_SESSION['session_id'] = $sessionId;
        $_SESSION['user_id'] = $userId;
        $_SESSION['csrf_token'] = Security::generateCSRF();

        return $sessionId;
    }

    public function validateSession()
    {
        if (!isset($_SESSION['session_id']) || !isset($_SESSION['user_id'])) {
            return false;
        }

        $sql = "SELECT s.*, u.username, u.is_admin, u.is_banned 
                FROM sessions s 
                JOIN users u ON s.user_id = u.id 
                WHERE s.id = ? AND s.expires_at > CURRENT_TIMESTAMP";
        
        $stmt = $this->db->query($sql, [$_SESSION['session_id']]);
        $session = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$session || $session['is_banned']) {
            $this->destroySession();
            return false;
        }

        // Update last seen
        $userModel = new \App\Models\User();
        $userModel->updateLastSeen($session['user_id']);

        return $session;
    }

    public function destroySession()
    {
        if (isset($_SESSION['session_id'])) {
            $sql = "DELETE FROM sessions WHERE id = ?";
            $this->db->query($sql, [$_SESSION['session_id']]);
        }

        session_destroy();
        session_start();
        session_regenerate_id(true);
    }

    public function cleanupExpiredSessions()
    {
        $sql = "DELETE FROM sessions WHERE expires_at < CURRENT_TIMESTAMP";
        $this->db->query($sql);
    }

    public function getUserSessions($userId)
    {
        $sql = "SELECT id, ip_address, user_agent, created_at, expires_at 
                FROM sessions 
                WHERE user_id = ? AND expires_at > CURRENT_TIMESTAMP 
                ORDER BY created_at DESC";
        
        $stmt = $this->db->query($sql, [$userId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function revokeUserSessions($userId, $exceptSessionId = null)
    {
        $sql = "DELETE FROM sessions WHERE user_id = ?";
        $params = [$userId];
        
        if ($exceptSessionId) {
            $sql .= " AND id != ?";
            $params[] = $exceptSessionId;
        }
        
        $this->db->query($sql, $params);
    }
}

