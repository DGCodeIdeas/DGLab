<?php
/**
 * DGLab PWA - API Key Manager
 * 
 * Securely manages user API keys for AI services.
 * 
 * @package DGLab\Tools\NovelToManga
 * @author DGLab Team
 * @version 1.0.0
 */

namespace DGLab\Tools\NovelToManga;

use DGLab\Core\Database;

/**
 * ApiKeyManager Class
 * 
 * Manages API keys with secure storage using encryption.
 */
class ApiKeyManager
{
    /**
     * @var string $keysFile Path to keys file (fallback storage)
     */
    private string $keysFile;
    
    /**
     * @var string|null $encryptionKey Encryption key
     */
    private ?string $encryptionKey = null;
    
    /**
     * @var Database|null $db Database instance
     */
    private ?Database $db = null;
    
    /**
     * @var bool $useDatabase Whether to use database storage
     */
    private bool $useDatabase = false;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->keysFile = STORAGE_PATH . '/api-keys.dat';
        
        // Get or generate encryption key
        $this->encryptionKey = $this->getEncryptionKey();
        
        // Try to use database if available
        try {
            $this->db = Database::getInstance();
            $this->useDatabase = true;
            $this->initializeDatabase();
        } catch (\Exception $e) {
            // Fall back to file-based storage
            $this->useDatabase = false;
        }
    }

    /**
     * Save API key for user
     * 
     * @param string $userId User identifier
     * @param string $provider Provider name
     * @param string $apiKey API key to store
     * @return bool Success status
     */
    public function saveKey(string $userId, string $provider, string $apiKey): bool
    {
        if (empty($apiKey)) {
            return false;
        }
        
        // Validate key format
        if (!$this->validateKeyFormat($apiKey, $provider)) {
            return false;
        }
        
        // Encrypt the key
        $encryptedKey = $this->encrypt($apiKey);
        
        if ($this->useDatabase) {
            return $this->saveToDatabase($userId, $provider, $encryptedKey);
        } else {
            return $this->saveToFile($userId, $provider, $encryptedKey);
        }
    }

    /**
     * Get API key for user
     * 
     * @param string $userId User identifier
     * @param string $provider Provider name
     * @return string|null API key or null
     */
    public function getKey(string $userId, string $provider): ?string
    {
        $encryptedKey = null;
        
        if ($this->useDatabase) {
            $encryptedKey = $this->getFromDatabase($userId, $provider);
        } else {
            $encryptedKey = $this->getFromFile($userId, $provider);
        }
        
        if ($encryptedKey === null) {
            return null;
        }
        
        return $this->decrypt($encryptedKey);
    }

    /**
     * Delete API key for user
     * 
     * @param string $userId User identifier
     * @param string $provider Provider name
     * @return bool Success status
     */
    public function deleteKey(string $userId, string $provider): bool
    {
        if ($this->useDatabase) {
            return $this->deleteFromDatabase($userId, $provider);
        } else {
            return $this->deleteFromFile($userId, $provider);
        }
    }

    /**
     * Check if user has API key
     * 
     * @param string $userId User identifier
     * @param string $provider Provider name
     * @return bool True if key exists
     */
    public function hasKey(string $userId, string $provider): bool
    {
        return $this->getKey($userId, $provider) !== null;
    }

    /**
     * Get all keys for user
     * 
     * @param string $userId User identifier
     * @return array Provider => key pairs
     */
    public function getAllKeys(string $userId): array
    {
        $keys = [];
        
        if ($this->useDatabase) {
            $encryptedKeys = $this->getAllFromDatabase($userId);
        } else {
            $encryptedKeys = $this->getAllFromFile($userId);
        }
        
        foreach ($encryptedKeys as $provider => $encryptedKey) {
            $keys[$provider] = $this->decrypt($encryptedKey);
        }
        
        return $keys;
    }

    /**
     * Clear all keys for user
     * 
     * @param string $userId User identifier
     * @return bool Success status
     */
    public function clearAllKeys(string $userId): bool
    {
        if ($this->useDatabase) {
            return $this->clearAllFromDatabase($userId);
        } else {
            return $this->clearAllFromFile($userId);
        }
    }

    // =============================================================================
    // PRIVATE METHODS - Database Storage
    // =============================================================================

    /**
     * Initialize database table
     * 
     * @return void
     */
    private function initializeDatabase(): void
    {
        if ($this->db === null) {
            return;
        }
        
        $sql = "CREATE TABLE IF NOT EXISTS user_api_keys (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id VARCHAR(255) NOT NULL,
            provider VARCHAR(50) NOT NULL,
            api_key TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_user_provider (user_id, provider)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        try {
            $this->db->query($sql);
        } catch (\Exception $e) {
            // Table may already exist
        }
    }

    /**
     * Save key to database
     * 
     * @param string $userId User ID
     * @param string $provider Provider
     * @param string $encryptedKey Encrypted key
     * @return bool Success
     */
    private function saveToDatabase(string $userId, string $provider, string $encryptedKey): bool
    {
        try {
            // Use INSERT ... ON DUPLICATE KEY UPDATE
            $sql = "INSERT INTO user_api_keys (user_id, provider, api_key) 
                    VALUES (?, ?, ?) 
                    ON DUPLICATE KEY UPDATE api_key = ?, updated_at = NOW()";
            
            $this->db->query($sql, [$userId, $provider, $encryptedKey, $encryptedKey]);
            return true;
        } catch (\Exception $e) {
            error_log('Failed to save API key: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get key from database
     * 
     * @param string $userId User ID
     * @param string $provider Provider
     * @return string|null Encrypted key
     */
    private function getFromDatabase(string $userId, string $provider): ?string
    {
        try {
            $result = $this->db->fetchOne(
                "SELECT api_key FROM user_api_keys WHERE user_id = ? AND provider = ?",
                [$userId, $provider]
            );
            
            return $result['api_key'] ?? null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Delete key from database
     * 
     * @param string $userId User ID
     * @param string $provider Provider
     * @return bool Success
     */
    private function deleteFromDatabase(string $userId, string $provider): bool
    {
        try {
            $this->db->query(
                "DELETE FROM user_api_keys WHERE user_id = ? AND provider = ?",
                [$userId, $provider]
            );
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get all keys from database
     * 
     * @param string $userId User ID
     * @return array Keys
     */
    private function getAllFromDatabase(string $userId): array
    {
        try {
            $results = $this->db->fetchAll(
                "SELECT provider, api_key FROM user_api_keys WHERE user_id = ?",
                [$userId]
            );
            
            $keys = [];
            foreach ($results as $row) {
                $keys[$row['provider']] = $row['api_key'];
            }
            
            return $keys;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Clear all keys from database
     * 
     * @param string $userId User ID
     * @return bool Success
     */
    private function clearAllFromDatabase(string $userId): bool
    {
        try {
            $this->db->query(
                "DELETE FROM user_api_keys WHERE user_id = ?",
                [$userId]
            );
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    // =============================================================================
    // PRIVATE METHODS - File Storage
    // =============================================================================

    /**
     * Save key to file
     * 
     * @param string $userId User ID
     * @param string $provider Provider
     * @param string $encryptedKey Encrypted key
     * @return bool Success
     */
    private function saveToFile(string $userId, string $provider, string $encryptedKey): bool
    {
        $keys = $this->loadKeysFromFile();
        
        if (!isset($keys[$userId])) {
            $keys[$userId] = [];
        }
        
        $keys[$userId][$provider] = $encryptedKey;
        
        return $this->saveKeysToFile($keys);
    }

    /**
     * Get key from file
     * 
     * @param string $userId User ID
     * @param string $provider Provider
     * @return string|null Encrypted key
     */
    private function getFromFile(string $userId, string $provider): ?string
    {
        $keys = $this->loadKeysFromFile();
        
        return $keys[$userId][$provider] ?? null;
    }

    /**
     * Delete key from file
     * 
     * @param string $userId User ID
     * @param string $provider Provider
     * @return bool Success
     */
    private function deleteFromFile(string $userId, string $provider): bool
    {
        $keys = $this->loadKeysFromFile();
        
        if (isset($keys[$userId][$provider])) {
            unset($keys[$userId][$provider]);
            
            // Clean up empty user entries
            if (empty($keys[$userId])) {
                unset($keys[$userId]);
            }
            
            return $this->saveKeysToFile($keys);
        }
        
        return true;
    }

    /**
     * Get all keys from file
     * 
     * @param string $userId User ID
     * @return array Keys
     */
    private function getAllFromFile(string $userId): array
    {
        $keys = $this->loadKeysFromFile();
        
        return $keys[$userId] ?? [];
    }

    /**
     * Clear all keys from file
     * 
     * @param string $userId User ID
     * @return bool Success
     */
    private function clearAllFromFile(string $userId): bool
    {
        $keys = $this->loadKeysFromFile();
        
        if (isset($keys[$userId])) {
            unset($keys[$userId]);
            return $this->saveKeysToFile($keys);
        }
        
        return true;
    }

    /**
     * Load keys from file
     * 
     * @return array Keys
     */
    private function loadKeysFromFile(): array
    {
        if (!file_exists($this->keysFile)) {
            return [];
        }
        
        $data = file_get_contents($this->keysFile);
        $keys = json_decode($data, true);
        
        return is_array($keys) ? $keys : [];
    }

    /**
     * Save keys to file
     * 
     * @param array $keys Keys
     * @return bool Success
     */
    private function saveKeysToFile(array $keys): bool
    {
        $dir = dirname($this->keysFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        $data = json_encode($keys, JSON_PRETTY_PRINT);
        
        return file_put_contents($this->keysFile, $data) !== false;
    }

    // =============================================================================
    // PRIVATE METHODS - Encryption
    // =============================================================================

    /**
     * Get or generate encryption key
     * 
     * @return string Encryption key
     */
    private function getEncryptionKey(): string
    {
        // Use environment variable if available
        $key = $_ENV['API_KEY_ENCRYPTION_KEY'] ?? null;
        
        if ($key !== null) {
            return base64_decode($key);
        }
        
        // Use a key file
        $keyFile = STORAGE_PATH . '/.key';
        
        if (file_exists($keyFile)) {
            return file_get_contents($keyFile);
        }
        
        // Generate new key
        $key = random_bytes(32);
        
        // Save key
        $dir = dirname($keyFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        file_put_contents($keyFile, $key);
        chmod($keyFile, 0600);
        
        return $key;
    }

    /**
     * Encrypt data
     * 
     * @param string $data Data to encrypt
     * @return string Encrypted data (base64 encoded)
     */
    private function encrypt(string $data): string
    {
        $iv = random_bytes(16);
        
        $encrypted = openssl_encrypt(
            $data,
            'AES-256-CBC',
            $this->encryptionKey,
            OPENSSL_RAW_DATA,
            $iv
        );
        
        return base64_encode($iv . $encrypted);
    }

    /**
     * Decrypt data
     * 
     * @param string $data Data to decrypt (base64 encoded)
     * @return string Decrypted data
     */
    private function decrypt(string $data): string
    {
        $data = base64_decode($data);
        
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        
        $decrypted = openssl_decrypt(
            $encrypted,
            'AES-256-CBC',
            $this->encryptionKey,
            OPENSSL_RAW_DATA,
            $iv
        );
        
        return $decrypted !== false ? $decrypted : '';
    }

    /**
     * Validate API key format
     * 
     * @param string $apiKey API key
     * @param string $provider Provider name
     * @return bool True if valid format
     */
    private function validateKeyFormat(string $apiKey, string $provider): bool
    {
        switch (strtolower($provider)) {
            case 'openai':
                return strpos($apiKey, 'sk-') === 0 && strlen($apiKey) > 20;
                
            case 'claude':
                return strpos($apiKey, 'sk-ant-') === 0 && strlen($apiKey) > 20;
                
            case 'gemini':
                return strlen($apiKey) > 20;
                
            default:
                return strlen($apiKey) > 10;
        }
    }

    /**
     * Mask API key for display
     * 
     * @param string $apiKey API key
     * @return string Masked key
     */
    public static function maskKey(string $apiKey): string
    {
        $length = strlen($apiKey);
        
        if ($length <= 8) {
            return str_repeat('*', $length);
        }
        
        return substr($apiKey, 0, 4) . str_repeat('*', $length - 8) . substr($apiKey, -4);
    }
}
