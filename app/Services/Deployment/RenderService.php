<?php

namespace DGLab\Services\Deployment;

use DGLab\Core\Application;

class RenderService
{
    private string $apiKey;
    private string $serviceId;
    private string $baseUrl = 'https://api.render.com/v1';

    public function __construct(array $config)
    {
        $this->apiKey = $config['api_key'] ?? '';
        $this->serviceId = $config['service_id'] ?? '';
    }

    public function triggerDeploy(): array
    {
        if (empty($this->apiKey) || empty($this->serviceId)) {
            return ['error' => 'Render API Key or Service ID not configured'];
        }

        $url = "{$this->baseUrl}/services/{$this->serviceId}/deploys";

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer {$this->apiKey}",
            "Content-Type: application/json"
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [
            'status' => $httpCode,
            'response' => json_decode($response, true)
        ];
    }

    public function getLatestDeployStatus(): array
    {
        if (empty($this->apiKey) || empty($this->serviceId)) {
            return ['error' => 'Render API Key or Service ID not configured'];
        }

        $url = "{$this->baseUrl}/services/{$this->serviceId}/deploys?limit=1";

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer {$this->apiKey}",
            "Content-Type: application/json"
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode($response, true);
        $deploy = $data[0]['deploy'] ?? null;

        return [
            'status' => $httpCode,
            'deploy_status' => $deploy['status'] ?? 'unknown',
            'deploy_id' => $deploy['id'] ?? null
        ];
    }
}
