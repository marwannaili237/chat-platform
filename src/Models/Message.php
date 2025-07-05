<?php

namespace App\Models;

use App\Utils\Security;

class Message
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function create($userId, $content, $messageType = 'text', $filePath = null, $encrypt = true)
    {
        $encryptedContent = null;
        
        if ($encrypt && $messageType === 'text') {
            $encryptedContent = Security::encrypt($content);
        }

        $sql = "INSERT INTO messages (user_id, content, encrypted_content, message_type, file_path) 
                VALUES (?, ?, ?, ?, ?)";
        
        $stmt = $this->db->query($sql, [
            $userId, 
            $encrypt ? '' : $content, 
            $encryptedContent, 
            $messageType, 
            $filePath
        ]);
        
        return $this->db->lastInsertId();
    }

    public function getMessages($limit = 50, $offset = 0, $decrypt = true)
    {
        $sql = "SELECT m.*, u.username 
                FROM messages m 
                JOIN users u ON m.user_id = u.id 
                ORDER BY m.created_at DESC 
                LIMIT ? OFFSET ?";
        
        $stmt = $this->db->query($sql, [$limit, $offset]);
        $messages = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if ($decrypt) {
            foreach ($messages as &$message) {
                if ($message['encrypted_content']) {
                    try {
                        $message['content'] = Security::decrypt($message['encrypted_content']);
                    } catch (\Exception $e) {
                        $message['content'] = '[Decryption failed]';
                    }
                }
            }
        }

        return array_reverse($messages); // Return in chronological order
    }

    public function getMessagesSince($timestamp, $decrypt = true)
    {
        $sql = "SELECT m.*, u.username 
                FROM messages m 
                JOIN users u ON m.user_id = u.id 
                WHERE m.created_at > ? 
                ORDER BY m.created_at ASC";
        
        $stmt = $this->db->query($sql, [$timestamp]);
        $messages = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if ($decrypt) {
            foreach ($messages as &$message) {
                if ($message['encrypted_content']) {
                    try {
                        $message['content'] = Security::decrypt($message['encrypted_content']);
                    } catch (\Exception $e) {
                        $message['content'] = '[Decryption failed]';
                    }
                }
            }
        }

        return $messages;
    }

    public function deleteMessage($messageId, $adminId)
    {
        $sql = "DELETE FROM messages WHERE id = ?";
        $this->db->query($sql, [$messageId]);
        
        $this->logAdminAction($adminId, 'delete_message', null, "Deleted message ID: {$messageId}");
    }

    public function getMessageById($messageId, $decrypt = true)
    {
        $sql = "SELECT m.*, u.username 
                FROM messages m 
                JOIN users u ON m.user_id = u.id 
                WHERE m.id = ?";
        
        $stmt = $this->db->query($sql, [$messageId]);
        $message = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($message && $decrypt && $message['encrypted_content']) {
            try {
                $message['content'] = Security::decrypt($message['encrypted_content']);
            } catch (\Exception $e) {
                $message['content'] = '[Decryption failed]';
            }
        }

        return $message;
    }

    public function getMessageCount()
    {
        $sql = "SELECT COUNT(*) as count FROM messages";
        $stmt = $this->db->query($sql);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result['count'];
    }

    private function logAdminAction($adminId, $action, $targetUserId = null, $details = null)
    {
        $sql = "INSERT INTO admin_logs (admin_id, action, target_user_id, details) 
                VALUES (?, ?, ?, ?)";
        $this->db->query($sql, [$adminId, $action, $targetUserId, $details]);
    }
}

