<?php

namespace DGLab\Controllers;

use DGLab\Core\Controller;
use DGLab\Core\Request;
use DGLab\Core\Response;
use DGLab\Services\Download\DownloadManager;
use DGLab\Database\DownloadToken;
use DGLab\Services\Download\AuditService;
use DGLab\Core\Application;

/**
 * Download Controller
 *
 * Handles file delivery for signed URLs and temporary tokens.
 */
class DownloadController extends Controller
{
    /**
     * Audit Service
     */
    private AuditService $audit;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->audit = new AuditService();
    }

    /**
     * Handle signed URL downloads
     *
     * Route: /s/{signature}
     */
    public function signedDownload(Request $request): Response
    {
        $startTime = $this->audit->startTimer();
        $signature = (string)$request->route('signature');
        $manager = DownloadManager::getInstance();

        $payload = $manager->decryptSignature($signature);

        if (!$payload) {
            $this->audit->record('unknown', 'unknown', 403, $startTime, 'Invalid signature.');
            return Response::json(['error' => 'Invalid or tampered signature.'], 403);
        }

        $path = (string)$payload['path'];
        $driver = (string)($payload['driver'] ?? 'unknown');

        // Validate expiration
        if (isset($payload['expires']) && $payload['expires'] < time()) {
            $this->audit->record($path, $driver, 403, $startTime, 'Expired signature.');
            return Response::json(['error' => 'Download link has expired.'], 403);
        }

        // Validate IP (Optional but included in payload by default)
        if (isset($payload['ip']) && $payload['ip'] !== ($request->getClientIp() ?: '')) {
            $this->audit->record($path, $driver, 403, $startTime, 'Unauthorized IP.');
            return Response::json(['error' => 'Unauthorized IP address.'], 403);
        }

        try {
            $response = $manager->download($path, null, [], $driver);
            $this->addDebugHeaders($response, $driver, $path, $startTime);
            $this->audit->record($path, $driver, 200, $startTime);
            return $response;
        } catch (\Exception $e) {
            $this->audit->record($path, $driver, 404, $startTime, $e->getMessage());
            return Response::json(['error' => $e->getMessage()], 404);
        }
    }

    /**
     * Handle token-based downloads
     *
     * Route: /dl/{token}
     */
    public function tokenDownload(Request $request): Response
    {
        $startTime = $this->audit->startTimer();
        $token = (string)$request->route('token');
        $hashedToken = hash('sha256', $token);

        $downloadToken = DownloadToken::findValid($hashedToken);

        if (!$downloadToken) {
            $this->audit->record('unknown', 'token', 403, $startTime, 'Invalid or consumed token.');
            return Response::json(['error' => 'Invalid, expired, or fully consumed download token.'], 403);
        }

        $path = (string)$downloadToken->getAttribute('file_path');
        $driver = (string)$downloadToken->getAttribute('driver');

        // Enforce IP if required
        if ($downloadToken->getAttribute('enforce_ip') &&
            $downloadToken->getAttribute('ip_address') !== ($request->getClientIp() ?: '')) {
            $this->audit->record($path, $driver, 403, $startTime, 'Unauthorized IP.');
            return Response::json(['error' => 'Unauthorized IP address.'], 403);
        }

        // Increment use count
        $downloadToken->incrementUse();

        $manager = DownloadManager::getInstance();
        try {
            $response = $manager->download($path, null, [], $driver);
            $this->addDebugHeaders($response, $driver, $path, $startTime);
            $this->audit->record($path, $driver, 200, $startTime);
            return $response;
        } catch (\Exception $e) {
            $this->audit->record($path, $driver, 404, $startTime, $e->getMessage());
            return Response::json(['error' => $e->getMessage()], 404);
        }
    }

    /**
     * Add debug headers to the response if enabled
     */
    private function addDebugHeaders(Response $response, string $driver, string $path, float $startTime): void
    {
        if (Application::getInstance()->config('app.debug')) {
            $latency = (int)((microtime(true) - $startTime) * 1000);
            $response->setHeader('X-Download-Driver', $driver);
            $response->setHeader('X-Download-Storage-Path', $path);
            $response->setHeader('X-Download-Latency', $latency . 'ms');
        }
    }
}
