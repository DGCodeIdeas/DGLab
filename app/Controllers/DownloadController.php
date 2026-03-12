<?php

namespace DGLab\Controllers;

use DGLab\Core\Controller;
use DGLab\Core\Request;
use DGLab\Core\Response;
use DGLab\Services\Download\DownloadManager;
use DGLab\Database\DownloadToken;

/**
 * Download Controller
 *
 * Handles file delivery for signed URLs and temporary tokens.
 */
class DownloadController extends Controller
{
    /**
     * Handle signed URL downloads
     *
     * Route: /s/{signature}
     */
    public function signedDownload(Request $request): Response
    {
        $signature = (string)$request->route('signature');
        $manager = DownloadManager::getInstance();

        $payload = $manager->decryptSignature($signature);

        if (!$payload) {
            return Response::json(['error' => 'Invalid or tampered signature.'], 403);
        }

        // Validate expiration
        if (isset($payload['expires']) && $payload['expires'] < time()) {
            return Response::json(['error' => 'Download link has expired.'], 403);
        }

        // Validate IP (Optional but included in payload by default)
        if (isset($payload['ip']) && $payload['ip'] !== ($request->getClientIp() ?: '')) {
            return Response::json(['error' => 'Unauthorized IP address.'], 403);
        }

        try {
            return $manager->download((string)$payload['path'], null, [], (string)($payload['driver'] ?? ''));
        } catch (\Exception $e) {
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
        $token = (string)$request->route('token');
        $hashedToken = hash('sha256', $token);

        $downloadToken = DownloadToken::findValid($hashedToken);

        if (!$downloadToken) {
            return Response::json(['error' => 'Invalid, expired, or fully consumed download token.'], 403);
        }

        // Enforce IP if required
        if ($downloadToken->getAttribute('enforce_ip') &&
            $downloadToken->getAttribute('ip_address') !== ($request->getClientIp() ?: '')) {
            return Response::json(['error' => 'Unauthorized IP address.'], 403);
        }

        // Increment use count
        $downloadToken->incrementUse();

        $manager = DownloadManager::getInstance();
        try {
            return $manager->download(
                (string)$downloadToken->getAttribute('file_path'),
                null,
                [],
                (string)$downloadToken->getAttribute('driver')
            );
        } catch (\Exception $e) {
            return Response::json(['error' => $e->getMessage()], 404);
        }
    }
}
