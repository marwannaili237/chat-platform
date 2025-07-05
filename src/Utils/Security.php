<?php

namespace App\Utils;

use App\Utils\Config;

class Security
{
    /**
     * Generate a secure random token
     */
    public static function generateToken($length = 32)
    {
        return bin2hex(random_bytes($length));
    }

    /**
     * Hash a password securely
     */
    public static function hashPassword($password)
    {
        return password_hash($password, \PASSWORD_ARGON2ID);
    }

    /**
     * Verify a password against its hash
     */
    public static function verifyPassword($password, $hash)
    {
        return password_verify($password, $hash);
    }

    /**
     * Sanitize input to prevent XSS
     */
    public static function sanitizeInput($input)
    {
        if (is_array($input)) {
            return array_map([self::class, \'sanitizeInput\'], $input);
        }
        return htmlspecialchars(trim($input), ENT_QUOTES, \'UTF-8\');
    }

    /**
     * Validate CSRF token
     */
    public static function validateCSRF($token)
    {
        if (!isset($_SESSION[\'csrf_token\'])) {
            return false;
        }
        return hash_equals($_SESSION[\'csrf_token\'], $token);
    }

    /**
     * Generate CSRF token
     */
    public static function generateCSRF()
    {
        if (!isset($_SESSION[\'csrf_token\'])) {
            $_SESSION[\'csrf_token\'] = self::generateToken();
        }
        return $_SESSION[\'csrf_token\'];
    }

    /**
     * Encrypt data using AES-256-GCM
     */
    public static function encrypt($data, $key = null)
    {
        $key = $key ?: Config::get(\'ENCRYPTION_KEY\');
        if (strlen($key) !== 32) {
            throw new \InvalidArgumentException(\'Encryption key must be 32 characters long\');
        }

        $iv = random_bytes(12); // 96-bit IV for GCM
        $tag = \'\';
        $encrypted = openssl_encrypt($data, \'aes-256-gcm\', $key, OPENSSL_RAW_DATA, $iv, $tag);
        
        return base64_encode($iv . $tag . $encrypted);
    }

    /**
     * Decrypt data using AES-256-GCM
     */
    public static function decrypt($encryptedData, $key = null)
    {
        $key = $key ?: Config::get(\'ENCRYPTION_KEY\');
        if (strlen($key) !== 32) {
            throw new \InvalidArgumentException(\'Encryption key must be 32 characters long\');
        }

        $data = base64_decode($encryptedData);
        $iv = substr($data, 0, 12);
        $tag = substr($data, 12, 16);
        $encrypted = substr($data, 28);

        return openssl_decrypt($encrypted, \'aes-256-gcm\', $key, OPENSSL_RAW_DATA, $iv, $tag);
    }

    /**
     * Rate limiting check
     */
    public static function checkRateLimit($identifier, $limit = null, $window = null)
    {
        $limit = $limit ?: (int)Config::get(\'RATE_LIMIT_MESSAGES\', 60);
        $window = $window ?: (int)Config::get(\'RATE_LIMIT_WINDOW\', 60);
        
        $file = __DIR__ . \'/../../storage/rate_limit_\' . md5($identifier) . \'.json\';
        $now = time();
        
        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true);
            $data[\'requests\'] = array_filter($data[\'requests\'], function($timestamp) use ($now, $window) {
                return ($now - $timestamp) < $window;
            });
        } else {
            $data = [\'requests\' => []];
        }
        
        if (count($data[\'requests\']) >= $limit) {
            return false;
        }
        
        $data[\'requests\'][] = $now;
        file_put_contents($file, json_encode($data));
        
        return true;
    }
}
