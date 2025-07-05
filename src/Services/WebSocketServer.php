<?php

namespace App\Services;

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use App\Models\User;
use App\Models\Message;
use App\Utils\Security;
use App\Utils\Config;

class WebSocketServer implements MessageComponentInterface
{
    protected $clients;
    protected $users;
    private $userModel;
    private $messageModel;

    public function __construct()
    {
        $this->clients = new \SplObjectStorage;
        $this->users = [];
        $this->userModel = new User();
        $this->messageModel = new Message();
    }

    public function onOpen(ConnectionInterface $conn)
    {
        $this->clients->attach($conn);
        echo "New connection! ({$conn->resourceId})\n";
    }

    public function onMessage(ConnectionInterface $from, $msg)
    {
        $data = json_decode($msg, true);
        
        if (!$data || !isset($data['type'])) {
            $this->sendError($from, 'Invalid message format');
            return;
        }

        switch ($data['type']) {
            case 'auth':
                $this->handleAuth($from, $data);
                break;
            case 'message':
                $this->handleMessage($from, $data);
                break;
            case 'typing':
                $this->handleTyping($from, $data);
                break;
            case 'ping':
                $this->handlePing($from);
                break;
            default:
                $this->sendError($from, 'Unknown message type');
        }
    }

    public function onClose(ConnectionInterface $conn)
    {
        $this->clients->detach($conn);
        
        // Remove user from online list
        if (isset($this->users[$conn->resourceId])) {
            $user = $this->users[$conn->resourceId];
            unset($this->users[$conn->resourceId]);
            
            // Broadcast user left
            $this->broadcast([
                'type' => 'user_left',
                'user' => $user,
                'timestamp' => date('c')
            ], $conn);
        }
        
        echo "Connection {$conn->resourceId} has disconnected\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e)
    {
        echo "An error has occurred: {$e->getMessage()}\n";
        $conn->close();
    }

    private function handleAuth(ConnectionInterface $conn, $data)
    {
        if (!isset($data['token'])) {
            $this->sendError($conn, 'Authentication token required');
            return;
        }

        // Validate session token (simplified - in real implementation, validate against database)
        $sessionData = $this->validateSessionToken($data['token']);
        if (!$sessionData) {
            $this->sendError($conn, 'Invalid authentication token');
            return;
        }

        // Check rate limiting
        $clientIp = $this->getClientIp($conn);
        if (!Security::checkRateLimit($clientIp . '_auth', 10, 60)) {
            $this->sendError($conn, 'Too many authentication attempts');
            return;
        }

        $user = $this->userModel->findById($sessionData['user_id']);
        if (!$user || $user['is_banned']) {
            $this->sendError($conn, 'User not found or banned');
            return;
        }

        // Store user connection
        $this->users[$conn->resourceId] = [
            'id' => $user['id'],
            'username' => $user['username'],
            'is_admin' => $user['is_admin'],
            'connection' => $conn
        ];

        // Send authentication success
        $conn->send(json_encode([
            'type' => 'auth_success',
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'is_admin' => $user['is_admin']
            ]
        ]));

        // Send recent messages
        $recentMessages = $this->messageModel->getMessages(50);
        $conn->send(json_encode([
            'type' => 'message_history',
            'messages' => $recentMessages
        ]));

        // Broadcast user joined
        $this->broadcast([
            'type' => 'user_joined',
            'user' => [
                'id' => $user['id'],
                'username' => $user['username']
            ],
            'timestamp' => date('c')
        ], $conn);

        // Send online users list
        $onlineUsers = array_map(function($u) {
            return ['id' => $u['id'], 'username' => $u['username']];
        }, $this->users);
        
        $conn->send(json_encode([
            'type' => 'online_users',
            'users' => array_values($onlineUsers)
        ]));
    }

    private function handleMessage(ConnectionInterface $from, $data)
    {
        if (!isset($this->users[$from->resourceId])) {
            $this->sendError($from, 'Not authenticated');
            return;
        }

        $user = $this->users[$from->resourceId];
        
        // Rate limiting for messages
        $clientIp = $this->getClientIp($from);
        if (!Security::checkRateLimit($clientIp . '_message', null, null)) {
            $this->sendError($from, 'Rate limit exceeded');
            return;
        }

        if (!isset($data['content']) || empty(trim($data['content']))) {
            $this->sendError($from, 'Message content required');
            return;
        }

        $content = Security::sanitizeInput($data['content']);
        $messageType = $data['message_type'] ?? 'text';

        // Save message to database
        try {
            $messageId = $this->messageModel->create($user['id'], $content, $messageType);
            
            $message = [
                'id' => $messageId,
                'user_id' => $user['id'],
                'username' => $user['username'],
                'content' => $content,
                'message_type' => $messageType,
                'created_at' => date('c')
            ];

            // Broadcast message to all connected clients
            $this->broadcast([
                'type' => 'new_message',
                'message' => $message
            ]);

        } catch (\Exception $e) {
            $this->sendError($from, 'Failed to save message');
        }
    }

    private function handleTyping(ConnectionInterface $from, $data)
    {
        if (!isset($this->users[$from->resourceId])) {
            return;
        }

        $user = $this->users[$from->resourceId];
        $isTyping = $data['is_typing'] ?? false;

        // Broadcast typing status to other users
        $this->broadcast([
            'type' => 'typing',
            'user' => [
                'id' => $user['id'],
                'username' => $user['username']
            ],
            'is_typing' => $isTyping
        ], $from);
    }

    private function handlePing(ConnectionInterface $from)
    {
        $from->send(json_encode(['type' => 'pong']));
    }

    private function broadcast($data, ConnectionInterface $exclude = null)
    {
        $message = json_encode($data);
        
        foreach ($this->clients as $client) {
            if ($client !== $exclude) {
                $client->send($message);
            }
        }
    }

    private function sendError(ConnectionInterface $conn, $message)
    {
        $conn->send(json_encode([
            'type' => 'error',
            'message' => $message
        ]));
    }

    private function validateSessionToken($token)
    {
        // Simplified session validation
        // In a real implementation, this would validate against the sessions table
        try {
            $sessionManager = new SessionManager();
            // This is a simplified approach - you'd need to implement proper token validation
            return ['user_id' => 1]; // Placeholder
        } catch (\Exception $e) {
            return false;
        }
    }

    private function getClientIp(ConnectionInterface $conn)
    {
        // Get client IP from connection
        $remoteAddress = $conn->remoteAddress;
        return parse_url($remoteAddress, PHP_URL_HOST) ?: '127.0.0.1';
    }

    public function broadcastAdminMessage($message)
    {
        $this->broadcast([
            'type' => 'admin_message',
            'message' => $message,
            'timestamp' => date('c')
        ]);
    }

    public function kickUser($userId)
    {
        foreach ($this->users as $resourceId => $user) {
            if ($user['id'] == $userId) {
                $conn = $user['connection'];
                $conn->send(json_encode([
                    'type' => 'kicked',
                    'message' => 'You have been kicked from the chat'
                ]));
                $conn->close();
                break;
            }
        }
    }
}

