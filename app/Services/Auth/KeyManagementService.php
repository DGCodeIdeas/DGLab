<?php
namespace DGLab\Services\Auth;
use RuntimeException;
class KeyManagementService {
    protected string $storagePath;
    public function __construct() {
        $this->storagePath = config('auth.key_storage_path', dirname(__DIR__, 2) . '/storage/keys');
        if (!is_dir($this->storagePath)) mkdir($this->storagePath, 0700, true);
    }
    public function getKey(string $name, string $type = 'private'): string {
        $path = $this->storagePath . '/' . $name . '.' . $type;
        if (!file_exists($path)) {
            if ($type === 'private') return $this->generateKeyPair($name)['private'];
            throw new RuntimeException("Key file not found: $path");
        }
        return file_get_contents($path);
    }
    public function generateKeyPair(string $name): array {
        $res = openssl_pkey_new(["private_key_bits" => 2048, "private_key_type" => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($res, $privKey);
        $pubKey = openssl_pkey_get_details($res)['key'];
        file_put_contents($this->storagePath . '/' . $name . '.private', $privKey);
        file_put_contents($this->storagePath . '/' . $name . '.public', $pubKey);
        chmod($this->storagePath . '/' . $name . '.private', 0600);
        chmod($this->storagePath . '/' . $name . '.public', 0644);
        return ['private' => $privKey, 'public' => $pubKey];
    }
}
