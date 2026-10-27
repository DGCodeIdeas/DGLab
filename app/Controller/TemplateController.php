<?php

declare(strict_types=1);

namespace App\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use SovereignStack\Core\Http\Response;
use SovereignStack\Core\Http\Stream;
use SovereignStack\Core\View\ViewInterface;

/**
 * Template rendering controller — demonstrates view subsystem integration.
 */
final class TemplateController
{
    public function __construct(
        private readonly ViewInterface $view,
    ) {}

    public function hello(ServerRequestInterface $request): ResponseInterface
    {
        $name = $request->getQueryParams()['name'] ?? 'World';
        $html = $this->view->render('hello.twig', ['name' => $name]);

        $stream = new Stream('php://temp', 'r+');
        $stream->write($html);
        $stream->rewind();

        return new Response(
            statusCode: 200,
            headers: ['Content-Type' => 'text/html; charset=utf-8'],
            body: $stream,
        );
    }
}
