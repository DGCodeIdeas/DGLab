# CORE-15: Session & Cookie Manager

**Phase ID**: CORE-15  
**Tier**: Core  

## Component Name and Description
The Session & Cookie Manager delivers a secure, PSR‑7‑compatible session handling framework that supports multiple storage back‑ends (database, Redis, and file) and provides cryptographically signed/encrypted cookies, automatic CSRF token generation, and seamless integration with the Sovereign Stack’s request lifecycle. Its primary responsibilities are:

1. **Cryptographically Signed & Encrypted Cookies** – All session identifiers and CSRF tokens are stored in cookies that are encrypted with AES‑256‑GCM (via CORE‑13) and signed with an HMAC derived from the same key.  
2. **PSR‑7 Response Session Cookie Injection** – A PSR‑7 middleware injects the session cookie into the response and extracts it from the request, ensuring immutability and compliance with PSR‑7 contracts.  
3. **Multi‑Driver Session Storage** – Supports three session storage back‑ends:  
   * **Database** – PDO‑based storage using a per‑tenant table (`sessions`).  
   * **Redis** – PhpRedis or Predis implementation for high‑throughput environments.  
   * **File** – Flysystem‑backed storage for low‑traffic or edge deployments.  
4. **CSRF Token Pipeline** – Generates single‑use, time‑limited CSRF tokens that are stored in the session and echoed back in a hidden form field or custom header.  
5. **Session Lifecycle Automation** – Handles start‑session, read, write, and destroy flows, including automatic renewal of the encryption key via CORE‑13’s key‑derivation service.  

The manager is deliberately framework‑agnostic; it can be used by any PSR‑7 application (e.g., Slim, Laravel, or custom routers) and is registered as a singleton in the DI container (CORE‑02).

---

## Context7 Research
| Topic | Reference | Key Takeaways |
|-------|-----------|---------------|
| PSR‑7 Cookies | `/websites/php_net_manual_en` – *Response headers* | Cookies are added via `$response->withHeader('Set-Cookie', ...)`. Must use `HttpOnly`, `Secure`, and `SameSite=Strict` flags for security. |
| Secure Cookie Encryption | `/jedisct1/libsodium-doc` – *Secret‑stream* | Use `crypto_aead_chacha20poly1305_ietf_encrypt` for authenticated encryption; include nonce (24 bytes) and tag (16 bytes). |
| Session Storage Drivers | `/thephpleague/flysystem-thephpleague` – *Flysystem* | Filesystem adapter can store session blobs; for Redis use `phpredis` extension or `predis/predis`. |
| CSRF Token Best Practices | `/fastify/fastify-secure-session` – *Secure session plugin* | Tokens should be single‑use, include timestamp, and be invalidated after verification. |
| PSR‑7 Middleware Flow | `/psr/http-message` – *Message interface* | Middleware must return a PSR‑7 response; cannot modify the original request object. |
| Key Management for Cookie Encryption | `/websites/php_net_manual_en` – *openssl_encrypt* | Derive an HMAC key from the same master key used for AES‑256‑GCM; store only the base64‑encoded nonce+ciphertext+tag. |
| Session Regeneration Attack Prevention | `/legacy/Architecture/CORE_FRAMEWORK.md` – *Session handling* | Rotate session ID after privilege elevation; use `session_regenerate_id(true)`. |

---

## Architectural Design

### Package Layout
```
Sovereign\Core\Session\
    ├─ Drivers\
    │    ├─ SessionStoreInterface.php
    │    ├─ DatabaseSessionStore.php
    │    ├─ RedisSessionStore.php
    │    └─ FileSessionStore.php
    ├─ Encryption\
    │    └─ CookieEncryptorInterface.php
    ├─ Middleware\
    │    └─ SessionCookieMiddleware.php
    ├─ Csrf\
    │    └─ CsrfTokenService.php
    └─ SessionManager.php
```

### Core Interfaces
```php
namespace Sovereign\Core\Session\Drivers;

interface SessionStoreInterface
{
    public function get(string $id): ?array;
    public function set(string $id, array $data, ?int $ttl = null): bool;
    public function destroy(string $id): bool;
    public function regenerate(string $oldId): string;
}
```

```php
namespace Sovereign\Core\Encryption;

interface CookieEncryptorInterface
{
    /**
     * Encrypt and sign a cookie value.
     *
     * @return string Base64‑encoded payload: nonce|ciphertext|tag
     */
    public function encrypt(string $plaintext, string $key): string;

    /**
     * Decrypt and verify a cookie value.
     *
     * @return string|null Plaintext on success, null on failure
     */
    public function decrypt(string $encrypted, string $key): ?string;
}
```

### Implementations
* **DatabaseSessionStore** – Stores serialized data in a `sessions` table (`session_id`, `payload`, `last_activity`, `ttl`). Uses prepared statements and enforces TTL cleanup via a scheduled job.  
* **RedisSessionStore** – Leverages `Redis::setex`/`Redis::get` with a TTL; supports atomic `GETSET` for regeneration.  
* **FileSessionStore** – Serializes payload and writes to a per‑tenant file under `storage/sessions/{id}.json` via Flysystem.  
* **CookieEncryptor** – Implements `CookieEncryptorInterface` using the **OpenSslAesGcmDriver** from CORE‑13 (fallback to Sodium driver if configured). Handles nonce generation (`random_bytes(12)`) and tag extraction.  
* **CsrfTokenService** – Generates a token (`bin2hex(random_bytes(32))`), stores it in the session store, and provides `getToken()`, `validateToken($token)`. Tokens expire after a configurable duration.  
* **SessionCookieMiddleware** – PSR‑7 middleware that:  
  1. Reads the `PHPSESSID` cookie (or configurable name).  
  2. Calls `SessionManager::start($cookieValue)` to obtain a store instance.  
  3. If no valid session exists, creates a new ID via `store->regenerate()`.  
  4. Encrypts the session ID using `CookieEncryptor` and adds it to the response as a `Set-Cookie` header with `HttpOnly; Secure; SameSite=Strict`.  
  5. Provides `$response->withHeader('X-CSRF-Token', $csrfToken)` for downstream services.  

### Session Manager (Facade)
```php
namespace Sovereign\Core\Session;

class SessionManager
{
    private string $sessionName;
    private CookieEncryptorInterface $encryptor;
    private SessionStoreInterface $storeFactory; // resolves driver based on config
    private CsrfTokenService $csrfService;

    public function __construct(
        string $sessionName,
        CookieEncryptorInterface $encryptor,
        SessionStoreInterface $storeFactory,
        CsrfTokenService $csrfService
    ) {
        $this->sessionName = $sessionName;
        $this->encryptor = $encryptor;
        $this->storeFactory = $storeFactory;
        $this->csrfService = $csrfService;
    }

    /**
     * Start a session for the current request.
     *
     * @param string|null $cookieValue Existing encrypted cookie value
     * @return array{id:string, store:SessionStoreInterface, csrf:string}
     */
    public function start(?string $cookieValue = null): array
    {
        $id = $cookieValue ? $this->decryptCookie($cookieValue) : $this->storeFactory->regenerate('');
        $store = $this->storeFactory->getStore($id);
        $csrf = $this->csrfService->generateToken($id);
        return [
            'id'    => $id,
            'store' => $store,
            'csrf'  => $csrf,
        ];
    }

    private function decryptCookie(string $encrypted): string
    {
        $decrypted = $this->encryptor->decrypt($encrypted, key: $this->getEncryptionKey());
        return $decrypted;
    }

    private function getEncryptionKey(): string
    {
        // Derive from master key stored in config (CORE-13) – 32‑byte key
        return CryptoFacade::getInstance()->deriveKey('session_cookie');
    }
}
```

### Mermaid Component Diagram
```mermaid
graph TD
    A[SessionCookieMiddleware] -->|PSR‑7 Request| B[Extract Cookie]
    B --> C{Is Encrypted?}
    C -->|Yes| D[CookieEncryptor->decrypt]
    C -->|No| E[Use Default ID]
    D --> F[SessionManager->start]
    F --> G[Select SessionStore (DB/Redis/File)]
    G --> H[Store->get/set/regenerate]
    H --> I[CsrfTokenService->generateToken]
    I --> J[Add X‑CSRF‑Token Header]
    J --> K[Add Set‑Cookie Header]
    K --> L[Return Modified PSR‑7 Response]
    style A fill:#001f3f,stroke:#333,stroke-width:2px
    style B fill:#0074D9,stroke:#333,stroke-width:1px
    style C fill:#2ECC40,stroke:#333,stroke-width:1px
    style D fill:#FF4136,stroke:#333,stroke-width:1px
    style E fill:#AAA,stroke:#555,stroke-width:1px
    style F fill:#0074D9,stroke:#333,stroke-width:1px
    style G fill:#2ECC40,stroke:#333,stroke-width:1px
    style H fill:#FF4136,stroke:#333,stroke-width:1px
    style I fill:#001f3f,stroke:#333,stroke-width:1px
    style J fill:#0074D9,stroke:#333,stroke-width:1px
    style K fill:#FF4136,stroke:#333,stroke-width:1px
    style L fill:#AAA,stroke:#555,stroke-width:1px
```

### Integration Strategy
| Dependency | Integration Point | Reason |
|------------|-------------------|--------|
| **CORE‑01** | `random_bytes()` for generating cryptographically strong nonces and CSRF tokens. | Guarantees entropy ≥ 128‑bit. |
| **CORE‑02 (DI Container)** | Registers `SessionManager`, `SessionStoreInterface` implementations, `CookieEncryptorInterface`, and `CsrfTokenService` as singletons or request‑scoped services. | Enables automatic injection and lazy loading. |
| **CORE‑08 (Error & Exception Handlers)** | Centralizes session‑related exceptions (e.g., decryption failures) into audit logs with tenant context (CORE‑14). | Maintains traceability and compliance. |
| **CORE‑13 (Cryptographic Core Engine)** | Provides the AES‑256‑GCM and ChaCha20‑Poly1305 drivers used by `CookieEncryptor`. | Guarantees consistent encryption semantics across the stack. |
| **CORE‑14 (Multi‑Tenancy Core Isolator)** | Retrieves tenant‑specific session storage configuration (e.g., which driver to use) via `TenantContext`. | Enforces per‑tenant isolation and allows tenant‑level policy overrides. |
| **CORE‑16 (Task Scheduler)** | May store task‑related session data for long‑running jobs; the scheduler can reuse the same session store. | Shared session space for async tasks. |
| **CORE‑18 (View & SuperPHP Transpiler)** | Reads CSRF token from the session to inject hidden fields into rendered views. | Tight coupling with view rendering pipeline. |

### CI Verification Criteria
| Area | Requirement |
|------|-------------|
| **Unit Tests** | 100 % branch coverage on `SessionManager`, each `SessionStoreInterface` implementation, `CookieEncryptorInterface`, and `CsrfTokenService`. Mock PSR‑7 request/response objects and verify header injection. |
| **Integration Tests** | Spin up a Docker Compose stack with MySQL, Redis, and a local filesystem. Run tests that: <br>1. Create a session, store data, retrieve it, and destroy it. <br>2. Verify encrypted cookie format (`nonce|ciphertext|tag`). <br>3. Confirm CSRF token validation rejects reused or expired tokens. |
| **Performance Benchmarks** | *Session read/write*: ≥ 200 k ops/sec for Redis store, ≥ 150 k ops/sec for DB store (with proper indexing). *Cookie encryption*: ≤ 0.05 ms per encrypt/decrypt cycle. |
| **Security Tests** | Constant‑time comparison for token validation; ensure encrypted cookie cannot be tampered with (tampered ciphertext results in decryption failure). Verify `SameSite=Strict` and `Secure` flags are always set. |
| **Static Analysis** | Enforce PSR‑12; run `phpstan` level 7; confirm all public methods declare strict types and return types. |
| **Compliance Checks** | Ensure that session data never leaks across tenants; the `TenantContext` must be consulted before selecting a store. |
| **Semantic Versioning** | Adding a new storage driver (e.g., DynamoDB) is a **Patch**. Changing the `SessionManager` method signatures or removing a driver is a **Minor** (backward‑compatible via interface). Breaking changes such as removing the CSRF token pipeline are **Major**. |

---

## SemVer Impact
**Minor** – Introduces a new session & cookie management layer without altering existing public APIs of CORE‑01 or CORE‑02. Existing applications can adopt the manager by registering it in the DI container; no breaking changes to core contracts. Breaking API changes (e.g., removing `SessionManager::start` or altering its return shape) would trigger a **Major** version bump.

--- 

*Prepared by the Sovereign Stack Architect Team*  