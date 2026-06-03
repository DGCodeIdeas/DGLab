# Adapter Template: Container Orchestration (Kubernetes)

## Overview
This template demonstrates implementing a container orchestration adapter using the Kubernetes API. Follow this template when creating new deployment/orchestration adapters for the Sovereign Stack.

## Interface Implemented
[`ContainerAdapterInterface`](/docs/integration/interoperability-standards.md#4-containeradapterinterface-deployment---container-orchestration)

## Package Structure

```
dg-adapter-kubernetes/
├── composer.json
├── README.md
├── src/
│   ├── KubernetesAdapter.php            # Main adapter implementation
│   ├── KubernetesServiceProvider.php    # ServiceProvider for registration
│   ├── Config/
│   │   └── kubernetes.php              # Default configuration
│   ├── Exception/
│   │   ├── KubernetesConnectionException.php
│   │   ├── KubernetesDeployException.php
│   │   └── KubernetesApiException.php
│   ├── Client/
│   │   ├── ApiClient.php               # Kubernetes API HTTP client
│   │   ├── DeploymentClient.php         # Deployments API operations
│   │   ├── ServiceClient.php            # Services API operations
│   │   └── PodClient.php               # Pods API operations
│   ├── Serializer/
│   │   ├── DeploymentSpecSerializer.php # PHP array -> K8s manifest
│   │   └── StatusDeserializer.php       # K8s response -> ServiceStatus
│   └── Auth/
│       ├── TokenAuthenticator.php       # Bearer token auth
│       ├── ClientCertAuthenticator.php  # Client certificate auth
│       └── ServiceAccountAuthenticator.php # In-cluster SA auth
├── config/
│   └── kubernetes.php                   # Published configuration
└── tests/
    ├── Unit/
    │   ├── KubernetesAdapterTest.php
    │   ├── Client/
    │   │   ├── DeploymentClientTest.php
    │   │   ├── ServiceClientTest.php
    │   │   └── PodClientTest.php
    │   └── Serializer/
    │       ├── DeploymentSpecSerializerTest.php
    │       └── StatusDeserializerTest.php
    ├── Integration/
    │   └── KubernetesAdapterIntegrationTest.php
    └── TestDouble/
        └── FakeKubernetesServer.php
```

## Full Implementation

### 1. Composer Manifest

```json
{
    "name": "dg/adapter-kubernetes",
    "type": "sovereign-stack-adapter",
    "description": "Sovereign Stack adapter for Kubernetes container orchestration",
    "keywords": ["sovereign-stack", "adapter", "container", "kubernetes", "k8s", "orchestration"],
    "license": "MIT",
    "require": {
        "php": ">=8.2",
        "sovereign/core": "^2.0",
        "sovereign/adapter-contracts": "^1.0",
        "psr/http-client": "^1.0",
        "psr/http-factory": "^1.0"
    },
    "require-dev": {
        "sovereign/test-utils": "^1.0",
        "phpunit/phpunit": "^11.0",
        "guzzlehttp/guzzle": "^7.0",
        "http-interop/http-factory-guzzle": "^1.0"
    },
    "suggest": {
        "guzzlehttp/guzzle": "HTTP client for Kubernetes API communication"
    },
    "autoload": {
        "psr-4": {
            "Sovereign\\Adapter\\Kubernetes\\": "src/"
        }
    },
    "extra": {
        "sovereign-stack": {
            "type": "adapter",
            "category": "container",
            "target-blueprints": ["DEPLOY-01"],
            "min-core-version": "2.0.0"
        }
    }
}
```

### 2. API Client

```php
<?php
namespace Sovereign\Adapter\Kubernetes\Client;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Sovereign\Adapter\Kubernetes\Exception\KubernetesApiException;
use Sovereign\Adapter\Kubernetes\Exception\KubernetesConnectionException;

/**
 * Low-level Kubernetes API client wrapping PSR-18 HTTP communication.
 */
class ApiClient
{
    private const K8S_JSON_MEDIA_TYPE = 'application/json';

    private string $baseUrl;
    private array $defaultHeaders;

    public function __construct(
        private readonly ClientInterface $httpClient,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface $streamFactory,
        array $config
    ) {
        $this->baseUrl = rtrim($config['api_server_url'], '/');
        $this->defaultHeaders = [
            'Accept' => self::K8S_JSON_MEDIA_TYPE,
            'Content-Type' => self::K8S_JSON_MEDIA_TYPE,
            'User-Agent' => 'SovereignStack-KubernetesAdapter/1.0',
        ];

        // Add authentication headers
        $authHeader = $this->resolveAuthHeader($config);
        if ($authHeader !== null) {
            $this->defaultHeaders[$authHeader[0]] = $authHeader[1];
        }
    }

    /**
     * Perform a GET request against the Kubernetes API.
     *
     * @param string $path  API path (e.g., /api/v1/namespaces/default/pods)
     * @return array  Decoded JSON response
     * @throws KubernetesApiException
     */
    public function get(string $path): array
    {
        return $this->request('GET', $path);
    }

    /**
     * Perform a POST request against the Kubernetes API.
     */
    public function post(string $path, array $body): array
    {
        return $this->request('POST', $path, $body);
    }

    /**
     * Perform a PUT request against the Kubernetes API.
     */
    public function put(string $path, array $body): array
    {
        return $this->request('PUT', $path, $body);
    }

    /**
     * Perform a PATCH request against the Kubernetes API.
     */
    public function patch(string $path, array $body): array
    {
        return $this->request('PATCH', $path, $body);
    }

    /**
     * Perform a DELETE request against the Kubernetes API.
     */
    public function delete(string $path): array
    {
        return $this->request('DELETE', $path);
    }

    /**
     * Check if the Kubernetes API is reachable.
     */
    public function ping(): bool
    {
        try {
            $this->get('/api/v1');
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function request(string $method, string $path, ?array $body = null): array
    {
        $url = $this->baseUrl . $path;
        $request = $this->requestFactory->createRequest($method, $url);

        foreach ($this->defaultHeaders as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        if ($body !== null) {
            $stream = $this->streamFactory->createStream(
                json_encode($body, JSON_UNESCAPED_SLASHES)
            );
            $request = $request->withBody($stream);
        }

        try {
            $response = $this->httpClient->sendRequest($request);
        } catch (\Psr\Http\Client\NetworkExceptionInterface $e) {
            throw new KubernetesConnectionException(
                "Kubernetes API unreachable at {$this->baseUrl}: {$e->getMessage()}",
                previous: $e
            );
        }

        $statusCode = $response->getStatusCode();
        $responseBody = (string) $response->getBody();
        $data = json_decode($responseBody, true);

        if ($statusCode >= 400) {
            $message = $data['message'] ?? "HTTP {$statusCode} from Kubernetes API";
            $reason = $data['reason'] ?? 'Unknown';

            throw new KubernetesApiException(
                "[{$reason}] {$message}",
                $statusCode,
                $data
            );
        }

        return $data ?? [];
    }

    private function resolveAuthHeader(array $config): ?array
    {
        return match ($config['auth_type'] ?? 'service_account') {
            'token' => ['Authorization', 'Bearer ' . ($config['token'] ?? '')],
            'service_account' => $this->loadServiceAccountToken(),
            default => null,
        };
    }

    private function loadServiceAccountToken(): ?array
    {
        // In-cluster service account token
        $tokenPath = '/var/run/secrets/kubernetes.io/serviceaccount/token';
        if (file_exists($tokenPath)) {
            $token = trim(file_get_contents($tokenPath));
            return ['Authorization', 'Bearer ' . $token];
        }
        return null;
    }
}
```

### 3. Deployment Spec Serializer

```php
<?php
namespace Sovereign\Adapter\Kubernetes\Serializer;

/**
 * Converts Sovereign Stack deployment specs into Kubernetes manifest arrays.
 */
class DeploymentSpecSerializer
{
    /**
     * Convert a deployment specification into a Kubernetes Deployment manifest.
     *
     * @param string $serviceName  Service name
     * @param array  $spec         Deployment specification
     * @return array  Kubernetes Deployment manifest
     */
    public function toDeployment(string $serviceName, array $spec): array
    {
        $labels = $spec['labels'] ?? [
            'app' => $serviceName,
            'managed-by' => 'sovereign-stack',
        ];

        $manifest = [
            'apiVersion' => 'apps/v1',
            'kind' => 'Deployment',
            'metadata' => [
                'name' => $serviceName,
                'namespace' => $spec['namespace'] ?? 'default',
                'labels' => $labels,
                'annotations' => $spec['annotations'] ?? [
                    'sovereign-stack/version' => $spec['version'] ?? 'latest',
                ],
            ],
            'spec' => [
                'replicas' => $spec['replicas'] ?? 1,
                'selector' => [
                    'matchLabels' => [
                        'app' => $serviceName,
                    ],
                ],
                'template' => [
                    'metadata' => [
                        'labels' => $labels,
                    ],
                    'spec' => [
                        'containers' => [
                            $this->buildContainerSpec($serviceName, $spec),
                        ],
                        'imagePullSecrets' => $this->buildImagePullSecrets($spec),
                        'serviceAccountName' => $spec['service_account'] ?? null,
                    ],
                ],
                'strategy' => $this->buildStrategy($spec),
                'revisionHistoryLimit' => $spec['revision_history_limit'] ?? 5,
            ],
        ];

        // Add resource limits if specified
        if (isset($spec['resources'])) {
            $manifest['spec']['template']['spec']['containers'][0]['resources'] = $spec['resources'];
        }

        // Add health checks
        if (isset($spec['health_check'])) {
            $manifest['spec']['template']['spec']['containers'][0] = array_merge(
                $manifest['spec']['template']['spec']['containers'][0],
                $this->buildHealthChecks($spec['health_check'])
            );
        }

        // Add affinity rules
        if (isset($spec['affinity'])) {
            $manifest['spec']['template']['spec']['affinity'] = $spec['affinity'];
        }

        // Remove null values
        return $this->removeNulls($manifest);
    }

    /**
     * Convert a deployment specification into a Kubernetes Service manifest.
     */
    public function toService(string $serviceName, array $spec): array
    {
        $ports = array_map(
            fn(array $port) => [
                'name' => $port['name'] ?? 'http',
                'protocol' => $port['protocol'] ?? 'TCP',
                'port' => $port['port'],
                'targetPort' => $port['target_port'] ?? $port['port'],
            ],
            $spec['ports'] ?? []
        );

        return [
            'apiVersion' => 'v1',
            'kind' => 'Service',
            'metadata' => [
                'name' => $serviceName,
                'namespace' => $spec['namespace'] ?? 'default',
                'labels' => [
                    'app' => $serviceName,
                    'managed-by' => 'sovereign-stack',
                ],
            ],
            'spec' => [
                'type' => $spec['service_type'] ?? 'ClusterIP',
                'selector' => [
                    'app' => $serviceName,
                ],
                'ports' => $ports,
            ],
        ];
    }

    private function buildContainerSpec(string $serviceName, array $spec): array
    {
        $container = [
            'name' => $serviceName,
            'image' => $spec['image'],
            'imagePullPolicy' => $spec['image_pull_policy'] ?? 'IfNotPresent',
        ];

        // Environment variables
        if (isset($spec['env'])) {
            $container['env'] = array_map(
                fn(string $key, mixed $value) => is_string($value)
                    ? ['name' => $key, 'value' => $value]
                    : ['name' => $key, 'valueFrom' => $value],
                array_keys($spec['env']),
                $spec['env']
            );
        }

        // Ports
        if (isset($spec['ports'])) {
            $container['ports'] = array_map(
                fn(array $port) => [
                    'name' => $port['name'] ?? 'http',
                    'containerPort' => $port['container_port'] ?? $port['port'],
                    'protocol' => $port['protocol'] ?? 'TCP',
                ],
                $spec['ports']
            );
        }

        // Volume mounts
        if (isset($spec['volumes'])) {
            $container['volumeMounts'] = array_map(
                fn(array $volume) => [
                    'name' => $volume['name'],
                    'mountPath' => $volume['mount_path'],
                    'readOnly' => $volume['read_only'] ?? false,
                ],
                $spec['volumes']
            );
        }

        return $container;
    }

    private function buildHealthChecks(array $healthCheck): array
    {
        $checks = [];

        if (isset($healthCheck['liveness'])) {
            $checks['livenessProbe'] = $this->buildProbe($healthCheck['liveness']);
        }

        if (isset($healthCheck['readiness'])) {
            $checks['readinessProbe'] = $this->buildProbe($healthCheck['readiness']);
        }

        if (isset($healthCheck['startup'])) {
            $checks['startupProbe'] = $this->buildProbe($healthCheck['startup']);
        }

        return $checks;
    }

    private function buildProbe(array $probe): array
    {
        $result = [];

        if (isset($probe['http_get'])) {
            $result['httpGet'] = [
                'path' => $probe['http_get']['path'],
                'port' => $probe['http_get']['port'],
            ];
        } elseif (isset($probe['tcp_socket'])) {
            $result['tcpSocket'] = ['port' => $probe['tcp_socket']['port']];
        } elseif (isset($probe['exec'])) {
            $result['exec'] = ['command' => $probe['exec']['command']];
        }

        if (isset($probe['initial_delay'])) {
            $result['initialDelaySeconds'] = $probe['initial_delay'];
        }
        if (isset($probe['period'])) {
            $result['periodSeconds'] = $probe['period'];
        }
        if (isset($probe['timeout'])) {
            $result['timeoutSeconds'] = $probe['timeout'];
        }
        if (isset($probe['failure_threshold'])) {
            $result['failureThreshold'] = $probe['failure_threshold'];
        }

        return $result;
    }

    private function buildStrategy(array $spec): array
    {
        $strategy = $spec['strategy'] ?? 'rolling_update';

        return match ($strategy) {
            'recreate' => ['type' => 'Recreate'],
            'rolling_update' => [
                'type' => 'RollingUpdate',
                'rollingUpdate' => [
                    'maxUnavailable' => $spec['max_unavailable'] ?? '25%',
                    'maxSurge' => $spec['max_surge'] ?? '25%',
                ],
            ],
            default => ['type' => 'RollingUpdate'],
        };
    }

    private function buildImagePullSecrets(array $spec): ?array
    {
        if (!isset($spec['image_pull_secrets'])) {
            return null;
        }

        return array_map(
            fn(string $name) => ['name' => $name],
            (array) $spec['image_pull_secrets']
        );
    }

    private function removeNulls(array $data): array
    {
        $result = [];
        foreach ($data as $key => $value) {
            if ($value === null || $value === [] || $value === '') {
                continue;
            }
            $result[$key] = is_array($value) ? $this->removeNulls($value) : $value;
        }
        return $result;
    }
}
```

### 4. Main Adapter Implementation

```php
<?php
namespace Sovereign\Adapter\Kubernetes;

use Sovereign\Adapter\BaseAdapter;
use Sovereign\Adapter\Contracts\ContainerAdapterInterface;
use Sovereign\Adapter\Contracts\DeploymentResult;
use Sovereign\Adapter\Contracts\ServiceStatus;
use Sovereign\Adapter\Kubernetes\Client\ApiClient;
use Sovereign\Adapter\Kubernetes\Client\DeploymentClient;
use Sovereign\Adapter\Kubernetes\Client\ServiceClient;
use Sovereign\Adapter\Kubernetes\Client\PodClient;
use Sovereign\Adapter\Kubernetes\Serializer\DeploymentSpecSerializer;
use Sovereign\Adapter\Kubernetes\Serializer\StatusDeserializer;

class KubernetesAdapter extends BaseAdapter implements ContainerAdapterInterface
{
    private ApiClient $apiClient;
    private DeploymentClient $deploymentClient;
    private ServiceClient $serviceClient;
    private PodClient $podClient;
    private DeploymentSpecSerializer $serializer;
    private StatusDeserializer $deserializer;
    private string $defaultNamespace;

    public function __construct(
        ApiClient $apiClient,
        array $config
    ) {
        $this->apiClient = $apiClient;
        $this->defaultNamespace = $config['namespace'] ?? 'default';
        $this->serializer = new DeploymentSpecSerializer();
        $this->deserializer = new StatusDeserializer();

        $this->deploymentClient = new DeploymentClient($apiClient);
        $this->serviceClient = new ServiceClient($apiClient);
        $this->podClient = new PodClient($apiClient);
    }

    // --- ContainerAdapterInterface Implementation ---

    public function deploy(string $serviceName, array $spec): DeploymentResult
    {
        $namespace = $spec['namespace'] ?? $this->defaultNamespace;

        try {
            // Create or update Deployment
            $deploymentManifest = $this->serializer->toDeployment($serviceName, $spec);
            $result = $this->deploymentClient->apply($namespace, $deploymentManifest);

            // Create or update Service if ports are specified
            if (isset($spec['ports'])) {
                $serviceManifest = $this->serializer->toService($serviceName, $spec);
                $this->serviceClient->apply($namespace, $serviceManifest);
            }

            $revision = $result['metadata']['generation'] ?? '1';

            return new DeploymentResult(
                success: true,
                deploymentId: "{$namespace}/{$serviceName}",
                revision: (string) $revision
            );
        } catch (\Throwable $e) {
            return new DeploymentResult(
                success: false,
                deploymentId: "{$namespace}/{$serviceName}",
                revision: '0',
                errorMessage: $e->getMessage()
            );
        }
    }

    public function scale(string $serviceName, int $replicas): void
    {
        $namespace = $this->defaultNamespace;

        // Scale via PATCH request
        $patch = [
            'spec' => [
                'replicas' => $replicas,
            ],
        ];

        $this->deploymentClient->patch($namespace, $serviceName, $patch);
    }

    public function getServiceStatus(string $serviceName): ServiceStatus
    {
        $namespace = $this->defaultNamespace;

        try {
            $deployment = $this->deploymentClient->get($namespace, $serviceName);
            $pods = $this->podClient->list($namespace, ['app' => $serviceName]);

            return $this->deserializer->toServiceStatus(
                $serviceName,
                $deployment,
                $pods
            );
        } catch (\Throwable $e) {
            return new ServiceStatus(
                serviceName: $serviceName,
                desiredReplicas: 0,
                readyReplicas: 0,
                status: 'unknown',
                version: null
            );
        }
    }

    public function listServices(): array
    {
        $namespace = $this->defaultNamespace;
        $deployments = $this->deploymentClient->list($namespace);

        $statuses = [];
        foreach ($deployments['items'] ?? [] as $deployment) {
            $name = $deployment['metadata']['name'];
            $pods = $this->podClient->list($namespace, ['app' => $name]);
            $statuses[] = $this->deserializer->toServiceStatus($name, $deployment, $pods);
        }

        return $statuses;
    }

    public function rollback(string $serviceName, string $revision): DeploymentResult
    {
        $namespace = $this->defaultNamespace;

        try {
            // Use Deployment rollout undo via annotation
            $patch = [
                'metadata' => [
                    'annotations' => [
                        'deployment.kubernetes.io/revision' => $revision,
                    ],
                ],
                'spec' => [
                    'rollbackTo' => [
                        'revision' => (int) $revision,
                    ],
                ],
            ];

            $this->deploymentClient->patch($namespace, $serviceName, $patch);

            return new DeploymentResult(
                success: true,
                deploymentId: "{$namespace}/{$serviceName}",
                revision: $revision
            );
        } catch (\Throwable $e) {
            return new DeploymentResult(
                success: false,
                deploymentId: "{$namespace}/{$serviceName}",
                revision: $revision,
                errorMessage: $e->getMessage()
            );
        }
    }

    public function getLogs(string $serviceName, int $tailLines = 100): array
    {
        $namespace = $this->defaultNamespace;

        // Find pods for the service
        $pods = $this->podClient->list($namespace, ['app' => $serviceName]);
        $podNames = array_map(
            fn(array $pod) => $pod['metadata']['name'],
            $pods['items'] ?? []
        );

        if (empty($podNames)) {
            return [];
        }

        // Get logs from the first running pod
        $podName = $podNames[0];
        return $this->podClient->getLogs($namespace, $podName, $tailLines);
    }

    // --- AdapterInterface Implementation ---

    public function getId(): string
    {
        return 'dg.kubernetes';
    }

    public function getName(): string
    {
        return 'Kubernetes Adapter';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function getFrameworkConstraint(): string
    {
        return '>=2.0.0 <3.0.0';
    }

    public function getTargetBlueprints(): array
    {
        return ['DEPLOY-01'];
    }

    public function healthCheck(): bool
    {
        return $this->apiClient->ping();
    }

    public function boot(): void
    {
        // Verify cluster access at boot
        if (!$this->healthCheck()) {
            error_log('Kubernetes adapter: cluster unreachable at boot');
        }
    }

    public function shutdown(): void
    {
        // No persistent connections to close for HTTP API
    }
}
```

### 5. Status Deserializer

```php
<?php
namespace Sovereign\Adapter\Kubernetes\Serializer;

use Sovereign\Adapter\Contracts\ServiceStatus;

class StatusDeserializer
{
    /**
     * Convert Kubernetes Deployment + Pod data into a ServiceStatus.
     */
    public function toServiceStatus(string $serviceName, array $deployment, array $pods): ServiceStatus
    {
        $spec = $deployment['spec'] ?? [];
        $status = $deployment['status'] ?? [];

        $desiredReplicas = $status['replicas'] ?? $spec['replicas'] ?? 0;
        $readyReplicas = $status['readyReplicas'] ?? 0;
        $version = $deployment['metadata']['annotations']['sovereign-stack/version']
            ?? $deployment['metadata']['labels']['version']
            ?? null;

        // Determine aggregate status
        $statusText = $this->determineStatus($desiredReplicas, $readyReplicas, $pods);

        // Build endpoints from Service if available
        $endpoints = [];
        foreach ($pods['items'] ?? [] as $pod) {
            if ($this->isPodReady($pod)) {
                $podIP = $pod['status']['podIP'] ?? null;
                if ($podIP) {
                    $endpoints[] = $podIP;
                }
            }
        }

        return new ServiceStatus(
            serviceName: $serviceName,
            desiredReplicas: $desiredReplicas,
            readyReplicas: $readyReplicas,
            status: $statusText,
            version: $version,
            endpoints: $endpoints
        );
    }

    private function determineStatus(int $desired, int $ready, array $pods): string
    {
        if ($desired === 0) {
            return 'stopped';
        }

        if ($ready === 0 && $desired > 0) {
            // Check if any pods exist at all
            if (empty($pods['items'])) {
                return 'failed';
            }
            return 'degraded';
        }

        if ($ready < $desired) {
            return 'degraded';
        }

        // Check if all pods are ready
        $allReady = true;
        foreach ($pods['items'] ?? [] as $pod) {
            if (!$this->isPodReady($pod)) {
                $allReady = false;
                break;
            }
        }

        return $allReady ? 'running' : 'degraded';
    }

    private function isPodReady(array $pod): bool
    {
        $conditions = $pod['status']['conditions'] ?? [];
        foreach ($conditions as $condition) {
            if (($condition['type'] ?? '') === 'Ready') {
                return ($condition['status'] ?? '') === 'True';
            }
        }
        return false;
    }
}
```

### 6. Service Provider

```php
<?php
namespace Sovereign\Adapter\Kubernetes;

use Sovereign\Adapter\AdapterServiceProvider;
use Sovereign\Adapter\Contracts\AdapterInterface;
use Sovereign\Adapter\Contracts\ContainerAdapterInterface;
use Sovereign\Adapter\Kubernetes\Client\ApiClient;

class KubernetesServiceProvider extends AdapterServiceProvider
{
    protected function createAdapter(): AdapterInterface
    {
        $config = config('adapters.kubernetes', []);

        // Resolve PSR-18 HTTP client from container
        $httpClient = $this->app->has(\Psr\Http\Client\ClientInterface::class)
            ? $this->app->make(\Psr\Http\Client\ClientInterface::class)
            : new \GuzzleHttp\Client();

        $requestFactory = $this->app->has(\Psr\Http\Message\RequestFactoryInterface::class)
            ? $this->app->make(\Psr\Http\Message\RequestFactoryInterface::class)
            : new \GuzzleHttp\Psr7\HttpFactory();

        $streamFactory = $this->app->has(\Psr\Http\Message\StreamFactoryInterface::class)
            ? $this->app->make(\Psr\Http\Message\StreamFactoryInterface::class)
            : new \GuzzleHttp\Psr7\HttpFactory();

        $apiClient = new ApiClient($httpClient, $requestFactory, $streamFactory, $config);

        return new KubernetesAdapter($apiClient, $config);
    }

    protected function bootServices(): void
    {
        // Register as default container adapter if configured
        if (config('adapters.kubernetes.default', false)) {
            $this->app->bind(
                ContainerAdapterInterface::class,
                fn() => $this->app->make(KubernetesAdapter::class)
            );
        }
    }
}
```

### 7. Default Configuration

```php
<?php
// config/kubernetes.php
return [
    /*
    |--------------------------------------------------------------------------
    | Kubernetes Adapter Configuration
    |--------------------------------------------------------------------------
    */

    // Kubernetes API server URL
    'api_server_url' => env('K8S_API_SERVER', 'https://kubernetes.default.svc'),

    // Authentication type: 'token', 'service_account', or 'cert'
    'auth_type' => env('K8S_AUTH_TYPE', 'service_account'),

    // Bearer token (for 'token' auth type)
    'token' => env('K8S_TOKEN'),

    // Default namespace for deployments
    'namespace' => env('K8S_NAMESPACE', 'default'),

    // Whether to verify TLS certificate
    'verify_ssl' => env('K8S_VERIFY_SSL', true),

    // Request timeout in seconds
    'timeout' => env('K8S_TIMEOUT', 30),

    // Set to true to make this the default container adapter
    'default' => env('K8S_DEFAULT', false),
];
```

## Testing

### Unit Test Pattern

```php
<?php
namespace Sovereign\Adapter\Kubernetes\Tests\Unit;

use Sovereign\Adapter\Kubernetes\KubernetesAdapter;
use Sovereign\Adapter\Kubernetes\Client\ApiClient;
use Sovereign\Adapter\Testing\AdapterTestCase;

class KubernetesAdapterTest extends AdapterTestCase
{
    private ApiClient $mockApiClient;

    protected function createAdapter(): KubernetesAdapter
    {
        $this->mockApiClient = $this->createMock(ApiClient::class);

        return new KubernetesAdapter(
            $this->mockApiClient,
            ['namespace' => 'test']
        );
    }

    protected function getMockConfig(): array
    {
        return ['namespace' => 'test'];
    }

    public function test_deploy_creates_deployment(): void
    {
        $this->mockApiClient->method('post')->willReturn([
            'metadata' => ['generation' => 1],
        ]);

        $result = $this->adapter->deploy('my-service', [
            'image' => 'my-app:latest',
            'replicas' => 3,
            'ports' => [['port' => 8080]],
        ]);

        $this->assertTrue($result->success);
        $this->assertEquals('test/my-service', $result->deploymentId);
    }

    public function test_deploy_failure_returns_error(): void
    {
        $this->mockApiClient->method('post')
            ->willThrowException(new \RuntimeException('API unavailable'));

        $result = $this->adapter->deploy('failing-service', [
            'image' => 'my-app:latest',
        ]);

        $this->assertFalse($result->success);
        $this->assertNotNull($result->errorMessage);
    }

    public function test_scale_updates_replicas(): void
    {
        $this->mockApiClient->expects($this->once())
            ->method('patch')
            ->with(
                $this->stringContains('test'),
                $this->stringContains('my-service'),
                $this->callback(function (array $patch) {
                    return ($patch['spec']['replicas'] ?? 0) === 5;
                })
            );

        $this->adapter->scale('my-service', 5);
    }

    public function test_get_service_status_returns_status(): void
    {
        $this->mockApiClient->method('get')->willReturn([
            'spec' => ['replicas' => 3],
            'status' => ['readyReplicas' => 2],
            'metadata' => ['name' => 'my-service'],
        ]);

        $status = $this->adapter->getServiceStatus('my-service');

        $this->assertEquals('my-service', $status->serviceName);
        $this->assertEquals(3, $status->desiredReplicas);
        $this->assertContains($status->status, ['running', 'degraded', 'failed', 'stopped']);
    }
}
```

### Integration Test Pattern

```php
<?php
namespace Sovereign\Adapter\Kubernetes\Tests\Integration;

use Sovereign\Adapter\Kubernetes\KubernetesAdapter;
use Sovereign\Adapter\Kubernetes\Client\ApiClient;
use Sovereign\Adapter\Testing\AdapterIntegrationTest;

class KubernetesAdapterIntegrationTest extends AdapterIntegrationTest
{
    private const K8S_API_URL = 'https://127.0.0.1:6443';

    protected function setUp(): void
    {
        parent::setUp();

        // Check if we have a real or kind/minikube cluster
        if (!$this->isClusterAvailable()) {
            $this->markTestSkipped(
                'Kubernetes cluster not available at ' . self::K8S_API_URL
            );
        }
    }

    protected function createAdapter(): KubernetesAdapter
    {
        $httpClient = new \GuzzleHttp\Client(['verify' => false]);
        $requestFactory = new \GuzzleHttp\Psr7\HttpFactory();
        $streamFactory = new \GuzzleHttp\Psr7\HttpFactory();

        $apiClient = new ApiClient($httpClient, $requestFactory, $streamFactory, [
            'api_server_url' => self::K8S_API_URL,
            'auth_type' => 'service_account',
        ]);

        return new KubernetesAdapter($apiClient, ['namespace' => 'default']);
    }

    private function isClusterAvailable(): bool
    {
        try {
            $ch = curl_init(self::K8S_API_URL . '/api/v1');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 3);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $result = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            return $httpCode === 200;
        } catch (\Throwable) {
            return false;
        }
    }

    public function test_end_to_end_deployment(): void
    {
        $adapter = $this->createAdapter();
        $adapter->boot();

        // Deploy a simple nginx service
        $result = $adapter->deploy('test-nginx', [
            'image' => 'nginx:alpine',
            'replicas' => 1,
            'ports' => [['port' => 80]],
        ]);

        $this->assertTrue($result->success);

        // Wait and check status
        sleep(5);
        $status = $adapter->getServiceStatus('test-nginx');
        $this->assertEquals('test-nginx', $status->serviceName);

        // Cleanup
        $adapter->scale('test-nginx', 0);
        $adapter->shutdown();
    }
}
```

## Verification Checklist

- [ ] `composer.json` follows `dg/adapter-{name}` naming convention
- [ ] `extra.sovereign-stack` metadata is populated correctly
- [ ] Adapter implements all `ContainerAdapterInterface` methods
- [ ] Deployment spec serializer produces valid Kubernetes manifests
- [ ] HTTP client uses PSR-18 interfaces for flexibility
- [ ] Authentication supports multiple methods (token, SA, cert)
- [ ] Status deserializer correctly maps K8s status to ServiceStatus
- [ ] Error handling wraps K8s API errors in adapter exceptions
- [ ] Rollback uses K8s native rollout undo
- [ ] Unit tests cover deploy, scale, status, rollback operations
- [ ] Integration tests skip gracefully when cluster unavailable
- [ ] Configuration supports all common K8s deployment options

## Related Documents

- [Adapter Pattern](/docs/integration/adapter-pattern.md) - Architecture and contract definitions
- [Interoperability Standards](/docs/integration/interoperability-standards.md) - Standard interface contracts
- [Adapter Library](/docs/integration/adapter-library.md) - Complete adapter catalog