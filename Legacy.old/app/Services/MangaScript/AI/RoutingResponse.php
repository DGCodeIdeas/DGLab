<?php

namespace DGLab\Services\MangaScript\AI;

/**
 * Placeholder for RoutingResponse (formerly part of RoutingEngine.php)
 */
class RoutingResponse
{
    protected array $model;
    protected array $input;

    public function __construct(array $model, array $input)
    {
        $this->model = $model;
        $this->input = $input;
    }

    public function execute(): array
    {
        return [
            'title' => $this->input['title'] ?? 'Generated Script',
            'model' => $this->model['model'],
            'content' => 'Sample manga script content generated via ' .
                $this->model['provider'] . ' (' . $this->model['model'] . ')'
        ];
    }
}
