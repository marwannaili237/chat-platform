<?php

namespace App\Models;

use App\Utils\Security;

class User
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function create($username, $password, $isAdmin = false)
    {
        $passwordHash = Security::hashPassword($password);
        
        $sql = "INSERT INTO users (username, password_hash, is_admin) VALUES (?, ?, ?)";
        $stmt = $this->db->query($sql, [$username, $passwordHash, $isAdmin ? 1 : 0]);
        
        return $this->db->lastInsertId();
    }

    public function authenticate($username, $password)
    {
        $sql = "SELECT * FROM users WHERE username = ? AND is_banned = 0";
        $stmt = $this->db->query($sql, [$username]);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($user && Security::verifyPassword($password, $user['password_hash'])) {
            $this->updateLastSeen($user['id']);
            return $user;
        }

        return false;
    }

    public function findById($id)
    {
        $sql = "SELECT * FROM users WHERE id = ?";
        $stmt = $this->db->query($sql, [$id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    public function findByUsername($username)
    {
        $sql = "SELECT * FROM users WHERE username = ?";
        $stmt = $this->db->query($sql, [$username]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    public function updateLastSeen($userId)
    {
        $sql = "UPDATE users SET last_seen = CURRENT_TIMESTAMP WHERE id = ?";
        $this->db->query($sql, [$userId]);
    }

    public function banUser($userId, $adminId)
    {
        $sql = "UPDATE users SET is_banned = 1 WHERE id = ?";
        $this->db->query($sql, [$userId]);
        
        $this->logAdminAction($adminId, 'ban_user', $userId, 'User banned');
    }

    public function unbanUser($userId, $adminId)
    {
        $sql = "UPDATE users SET is_banned = 0 WHERE id = ?";
        $this->db->query($sql, [$userId]);
        
        $this->logAdminAction($adminId, 'unban_user', $userId, 'User unbanned');
    }

    public function getAllUsers($limit = 50, $offset = 0)
    {
        $sql = "SELECT id, username, is_admin, is_banned, created_at, last_seen 
                FROM users ORDER BY created_at DESC LIMIT ? OFFSET ?";
        $stmt = $this->db->query($sql, [$limit, $offset]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getOnlineUsers($minutes = 5)
    {
        $sql = "SELECT id, username FROM users 
                WHERE last_seen > datetime('now', '-{$minutes} minutes') 
                AND is_banned = 0";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function logAdminAction($adminId, $action, $targetUserId = null, $details = null)
    {
        $sql = "INSERT INTO admin_logs (admin_id, action, target_user_id, details) 
                VALUES (?, ?, ?, ?)";
        $this->db->query($sql, [$adminId, $action, $targetUserId, $details]);
    }
}

