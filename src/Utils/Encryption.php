<?php

namespace App\Utils;

class Encryption
{
    private static $algorithm = 'aes-256-gcm';
    private static $keyDerivationIterations = 10000;

    /**
     * Generate a cryptographically secure key
     */
    public static function generateKey($length = 32)
    {
        return random_bytes($length);
    }

    /**
     * Derive a key from a password using PBKDF2
     */
    public static function deriveKey($password, $salt, $length = 32)
    {
        return hash_pbkdf2('sha256', $password, $salt, self::$keyDerivationIterations, $length, true);
    }

    /**
     * Encrypt data with authenticated encryption (AES-256-GCM)
     */
    public static function encrypt($plaintext, $key, $associatedData = '')
    {
        if (strlen($key) !== 32) {
            throw new \InvalidArgumentException('Key must be exactly 32 bytes');
        }

        $iv = random_bytes(12); // 96-bit IV for GCM
        $tag = '';
        
        $ciphertext = openssl_encrypt(
            $plaintext,
            self::$algorithm,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            $associatedData
        );

        if ($ciphertext === false) {
            throw new \RuntimeException('Encryption failed');
        }

        // Return IV + tag + ciphertext as base64
        return base64_encode($iv . $tag . $ciphertext);
    }

    /**
     * Decrypt data with authenticated encryption (AES-256-GCM)
     */
    public static function decrypt($encryptedData, $key, $associatedData = '')
    {
        if (strlen($key) !== 32) {
            throw new \InvalidArgumentException('Key must be exactly 32 bytes');
        }

        $data = base64_decode($encryptedData);
        if ($data === false || strlen($data) < 28) {
            throw new \InvalidArgumentException('Invalid encrypted data');
        }

        $iv = substr($data, 0, 12);
        $tag = substr($data, 12, 16);
        $ciphertext = substr($data, 28);

        $plaintext = openssl_decrypt(
            $ciphertext,
            self::$algorithm,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            $associatedData
        );

        if ($plaintext === false) {
            throw new \RuntimeException('Decryption failed or data tampered');
        }

        return $plaintext;
    }

    /**
     * End-to-end encryption for messages
     */
    public static function encryptMessage($message, $userKey, $roomId = '')
    {
        $timestamp = time();
        $metadata = json_encode([
            'timestamp' => $timestamp,
            'room_id' => $roomId,
            'version' => 1
        ]);

        return self::encrypt($message, $userKey, $metadata);
    }

    /**
     * End-to-end decryption for messages
     */
    public static function decryptMessage($encryptedMessage, $userKey, $roomId = '')
    {
        $metadata = json_encode([
            'room_id' => $roomId,
            'version' => 1
        ]);

        return self::decrypt($encryptedMessage, $userKey, $metadata);
    }

    /**
     * Generate a user-specific encryption key from password
     */
    public static function generateUserKey($userId, $password, $serverSalt = null)
    {
        $serverSalt = $serverSalt ?: Config::get('ENCRYPTION_KEY', 'default_server_salt_change_this');
        $userSalt = hash('sha256', $userId . $serverSalt);
        
        return self::deriveKey($password, $userSalt);
    }

    /**
     * Secure key exchange for client-side encryption
     */
    public static function generateKeyExchange()
    {
        // Generate ephemeral key pair for ECDH
        $privateKey = openssl_pkey_new([
            'curve_name' => 'prime256v1',
            'private_key_type' => OPENSSL_KEYTYPE_EC,
        ]);

        $publicKeyDetails = openssl_pkey_get_details($privateKey);
        $publicKey = $publicKeyDetails['key'];

        return [
            'private_key' => $privateKey,
            'public_key' => $publicKey
        ];
    }

    /**
     * Compute shared secret from key exchange
     */
    public static function computeSharedSecret($privateKey, $peerPublicKey)
    {
        $sharedSecret = '';
        
        if (openssl_pkey_derive($sharedSecret, $peerPublicKey, $privateKey) === false) {
            throw new \RuntimeException('Key derivation failed');
        }

        // Use HKDF to derive a proper encryption key
        return hash_hkdf('sha256', $sharedSecret, 32, 'chat-encryption-key');
    }

    /**
     * Secure file encryption
     */
    public static function encryptFile($filePath, $key)
    {
        if (!file_exists($filePath)) {
            throw new \InvalidArgumentException('File does not exist');
        }

        $plaintext = file_get_contents($filePath);
        $encrypted = self::encrypt($plaintext, $key);
        
        $encryptedPath = $filePath . '.enc';
        file_put_contents($encryptedPath, $encrypted);
        
        // Securely delete original file
        self::secureDelete($filePath);
        
        return $encryptedPath;
    }

    /**
     * Secure file decryption
     */
    public static function decryptFile($encryptedFilePath, $key, $outputPath = null)
    {
        if (!file_exists($encryptedFilePath)) {
            throw new \InvalidArgumentException('Encrypted file does not exist');
        }

        $encryptedData = file_get_contents($encryptedFilePath);
        $plaintext = self::decrypt($encryptedData, $key);
        
        $outputPath = $outputPath ?: str_replace('.enc', '', $encryptedFilePath);
        file_put_contents($outputPath, $plaintext);
        
        return $outputPath;
    }

    /**
     * Secure deletion of sensitive data
     */
    public static function secureDelete($filePath)
    {
        if (!file_exists($filePath)) {
            return true;
        }

        $fileSize = filesize($filePath);
        $handle = fopen($filePath, 'r+');
        
        if ($handle) {
            // Overwrite with random data multiple times
            for ($i = 0; $i < 3; $i++) {
                fseek($handle, 0);
                fwrite($handle, random_bytes($fileSize));
                fflush($handle);
            }
            
            fclose($handle);
        }
        
        return unlink($filePath);
    }

    /**
     * Generate a secure session token
     */
    public static function generateSessionToken()
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Hash a session token for storage
     */
    public static function hashSessionToken($token)
    {
        return hash('sha256', $token);
    }

    /**
     * Verify a session token against its hash
     */
    public static function verifySessionToken($token, $hash)
    {
        return hash_equals($hash, hash('sha256', $token));
    }

    /**
     * Generate a time-based one-time password (TOTP) secret
     */
    public static function generateTOTPSecret()
    {
        return base32_encode(random_bytes(20));
    }

    /**
     * Verify a TOTP code
     */
    public static function verifyTOTP($secret, $code, $window = 1)
    {
        $timeStep = floor(time() / 30);
        
        for ($i = -$window; $i <= $window; $i++) {
            $calculatedCode = self::calculateTOTP($secret, $timeStep + $i);
            if (hash_equals($code, $calculatedCode)) {
                return true;
            }
        }
        
        return false;
    }

    private static function calculateTOTP($secret, $timeStep)
    {
        $key = base32_decode($secret);
        $time = pack('N*', 0) . pack('N*', $timeStep);
        $hash = hash_hmac('sha1', $time, $key, true);
        $offset = ord($hash[19]) & 0xf;
        $code = (
            ((ord($hash[$offset + 0]) & 0x7f) << 24) |
            ((ord($hash[$offset + 1]) & 0xff) << 16) |
            ((ord($hash[$offset + 2]) & 0xff) << 8) |
            (ord($hash[$offset + 3]) & 0xff)
        ) % 1000000;
        
        return str_pad($code, 6, '0', STR_PAD_LEFT);
    }
}

// Helper functions for base32 encoding/decoding
function base32_encode($data)
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $output = '';
    $v = 0;
    $vbits = 0;
    
    for ($i = 0; $i < strlen($data); $i++) {
        $v = ($v << 8) | ord($data[$i]);
        $vbits += 8;
        
        while ($vbits >= 5) {
            $output .= $alphabet[($v >> ($vbits - 5)) & 31];
            $vbits -= 5;
        }
    }
    
    if ($vbits > 0) {
        $output .= $alphabet[($v << (5 - $vbits)) & 31];
    }
    
    return $output;
}

function base32_decode($data)
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $output = '';
    $v = 0;
    $vbits = 0;
    
    for ($i = 0; $i < strlen($data); $i++) {
        $c = $data[$i];
        $pos = strpos($alphabet, $c);
        if ($pos === false) continue;
        
        $v = ($v << 5) | $pos;
        $vbits += 5;
        
        if ($vbits >= 8) {
            $output .= chr(($v >> ($vbits - 8)) & 255);
            $vbits -= 8;
        }
    }
    
    return $output;
}

