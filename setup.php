<?php

/**
 * Setup script for Secure Chat Platform
 * Run this script once to initialize the application
 */

require_once __DIR__ . '/vendor/autoload.php';

use App\Models\Database;
use App\Models\User;
use App\Utils\Config;
use App\Utils\Security;

echo "=== Secure Chat Platform Setup ===\n\n";

// Load configuration
Config::load();

try {
    // Initialize database
    echo "Initializing database...\n";
    $db = Database::getInstance();
    echo "✓ Database initialized successfully\n\n";

    // Create admin user
    echo "Creating admin user...\n";
    $userModel = new User();
    
    // Check if admin already exists
    $existingAdmin = $userModel->findByUsername('admin');
    if ($existingAdmin) {
        echo "⚠ Admin user already exists\n";
    } else {
        $adminPassword = bin2hex(random_bytes(8)); // Generate random password
        $adminId = $userModel->create('admin', $adminPassword, true);
        echo "✓ Admin user created successfully\n";
        echo "  Username: admin\n";
        echo "  Password: {$adminPassword}\n";
        echo "  ⚠ Please change this password after first login!\n";
    }

    // Create storage directories
    echo "\nCreating storage directories...\n";
    $directories = [
        'storage/cache',
        'storage/logs',
        'public/uploads',
        'public/uploads/thumbs'
    ];

    foreach ($directories as $dir) {
        $fullPath = __DIR__ . '/' . $dir;
        if (!is_dir($fullPath)) {
            mkdir($fullPath, 0755, true);
            echo "✓ Created directory: {$dir}\n";
        } else {
            echo "✓ Directory exists: {$dir}\n";
        }
    }

    // Set proper permissions
    echo "\nSetting permissions...\n";
    chmod(__DIR__ . '/storage', 0755);
    chmod(__DIR__ . '/public/uploads', 0755);
    echo "✓ Permissions set\n";

    // Generate encryption key if not exists
    echo "\nChecking encryption configuration...\n";
    $encryptionKey = Config::get('ENCRYPTION_KEY');
    if (!$encryptionKey || $encryptionKey === 'your_32_character_encryption_key_here') {
        $newKey = bin2hex(random_bytes(16)); // 32 character key
        
        // Update .env file
        $envFile = __DIR__ . '/.env';
        $envContent = file_get_contents($envFile);
        $envContent = str_replace(
            'ENCRYPTION_KEY=your_32_character_encryption_key_here',
            "ENCRYPTION_KEY={$newKey}",
            $envContent
        );
        file_put_contents($envFile, $envContent);
        
        echo "✓ Generated new encryption key\n";
        echo "  Key: {$newKey}\n";
    } else {
        echo "✓ Encryption key already configured\n";
    }

    // Test WebSocket server requirements
    echo "\nChecking WebSocket server requirements...\n";
    if (extension_loaded('sockets')) {
        echo "✓ Sockets extension available\n";
    } else {
        echo "⚠ Sockets extension not available - WebSocket server may not work\n";
    }

    if (class_exists('Ratchet\Server\IoServer')) {
        echo "✓ Ratchet library installed\n";
    } else {
        echo "✗ Ratchet library not found\n";
    }

    // Performance optimizations
    echo "\nApplying performance optimizations...\n";
    
    // Create performance log file
    $perfLogFile = __DIR__ . '/storage/performance.log';
    if (!file_exists($perfLogFile)) {
        touch($perfLogFile);
        echo "✓ Created performance log file\n";
    }

    // Create cache directory structure
    $cacheDir = __DIR__ . '/storage/cache';
    if (!is_dir($cacheDir)) {
        mkdir($cacheDir, 0755, true);
    }
    echo "✓ Cache directory ready\n";

    echo "\n=== Setup Complete ===\n";
    echo "Next steps:\n";
    echo "1. Start the WebSocket server: php websocket-server.php\n";
    echo "2. Configure your web server to serve the public/ directory\n";
    echo "3. Access the application in your browser\n";
    echo "4. Login with the admin credentials shown above\n";
    echo "5. Change the admin password immediately\n\n";

    echo "For production deployment:\n";
    echo "- Enable HTTPS\n";
    echo "- Configure proper database credentials\n";
    echo "- Set up process monitoring for the WebSocket server\n";
    echo "- Review and adjust security settings\n";

} catch (Exception $e) {
    echo "✗ Setup failed: " . $e->getMessage() . "\n";
    exit(1);
}

