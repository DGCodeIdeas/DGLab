<?php

namespace DGLab\Core;

abstract class Controller
{
    /**
     * Return a JSON response.
     *
     * @param mixed $data
     * @param int $status
     * @return Response
     */
    protected function json(mixed $data, int $status = 200): Response
    {
        return Response::json($data, $status);
    }

    /**
     * Return a plain response.
     *
     * @param mixed $content
     * @param int $status
     * @return Response
     */
    protected function response(mixed $content, int $status = 200): Response
    {
        return new Response($content, $status);
    }
}
