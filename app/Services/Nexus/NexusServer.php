<?php

namespace DGLab\Services\Nexus;

use Swoole\WebSocket\Server;
use Swoole\Http\Request;
use Swoole\Http\Response;
use DGLab\Models\User;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Class NexusServer
 *
 * The core WebSocket server handling connection lifecycle and message routing.
 */
class NexusServer
{
    protected Server $server;
    protected ConnectionManagerInterface $connectionManager;
    protected HandshakeValidator $validator;
    protected LoggerInterface $logger;
    protected string $host;
    protected int $port;

    public function __construct(
        ConnectionManagerInterface $connectionManager,
        HandshakeValidator $validator,
        LoggerInterface $logger,
        string $host = '0.0.0.0',
        int $port = 8080
    ) {
        $this->connectionManager = $connectionManager;
        $this->validator = $validator;
        $this->logger = $logger;
        $this->host = $host;
        $this->port = $port;
    }

    /**
     * Start the Swoole WebSocket server.
     */
    public function start(): void
    {
        $this->server = new Server($this->host, $this->port);

        $this->server->on('handshake', [$this, 'onHandshake']);
        $this->server->on('open', [$this, 'onOpen']);
        $this->server->on('message', [$this, 'onMessage']);
        $this->server->on('close', [$this, 'onClose']);

        $this->logger->info("Nexus WebSocket Server starting on {$this->host}:{$this->port}");
        $this->server->start();
    }

    /**
     * Handle the WebSocket handshake with JWT validation.
     */
    public function onHandshake(Request $request, Response $response): bool
    {
        try {
            $user = $this->validator->validate($request);

            // Handshake successful, set standard RFC 6455 headers
            if ($this->performHandshake($request, $response)) {
                $this->connectionManager->add($request->fd, [
                    'user_id' => $user->id,
                    'tenant_id' => $user->tenant_id ?? 0
                ]);
                $this->logger->info("Handshake successful for User ID: {$user->id} (FD: {$request->fd})");
                return true;
            }

            return false;
        } catch (Throwable $e) {
            $this->logger->error("Handshake failed: " . $e->getMessage());
            $response->status(401);
            $response->header('Content-Type', 'text/plain');
            $response->end("Unauthorized");
            return false;
        }
    }

    /**
     * Standard WebSocket handshake header logic for Swoole.
     */
    protected function performHandshake(Request $request, Response $response): bool
    {
        if (!isset($request->header['sec-websocket-key'])) {
            return false;
        }

        $key = $request->header['sec-websocket-key'];
        if (0 === preg_match('#^[+/0-9A-Za-z]{21}[AQgw]==$#', $key) || 16 !== strlen(base64_decode($key))) {
            return false;
        }

        $headers = [
            'Upgrade' => 'websocket',
            'Connection' => 'Upgrade',
            'Sec-WebSocket-Accept' => base64_encode(sha1($key . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true)),
            'Sec-WebSocket-Version' => '13',
        ];

        if (isset($request->header['sec-websocket-protocol'])) {
            $headers['Sec-WebSocket-Protocol'] = $request->header['sec-websocket-protocol'];
        }

        foreach ($headers as $name => $value) {
            $response->header($name, $value);
        }

        $response->status(101);
        $response->end();

        return true;
    }

    public function onOpen(Server $server, Request $request): void
    {
        $this->logger->info("Connection opened (FD: {$request->fd})");
    }

    public function onMessage(Server $server, $frame): void
    {
        $this->logger->debug("Message received from FD {$frame->fd}: {$frame->data}");

        try {
            $data = json_decode($frame->data, true);
            if (!$data) {
                return;
            }

            // Simple echo/ping for Phase One
            if (isset($data['type']) && $data['type'] === 'ping') {
                $server->push($frame->fd, json_encode([
                    'type' => 'pong',
                    'payload' => ['timestamp' => time()],
                    'meta' => ['request_id' => $data['meta']['request_id'] ?? null]
                ]));
            }

        } catch (Throwable $e) {
            $this->logger->error("Error processing message: " . $e->getMessage());
        }
    }

    public function onClose(Server $server, int $fd): void
    {
        $this->connectionManager->remove($fd);
        $this->logger->info("Connection closed (FD: {$fd})");
    }
}
