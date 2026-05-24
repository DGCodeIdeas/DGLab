<?php

namespace DGLab\Core;

use DGLab\Services\Superpowers\SuperpowersEngine;

class View
{
    protected Application $app;
    protected string $viewPath;
    protected string $layout = 'layouts.shell';
    protected ?string $fragmentMode = null;

    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->viewPath = $app->getBasePath() . '/resources/views';
    }

    public function setFragmentMode(?string $mode): void
    {
        $this->fragmentMode = $mode;
    }

    public function render(string $template, array $data = [], ?string $layout = null): string
    {
        $engine = new SuperpowersEngine($this->viewPath);

        if ($this->fragmentMode) {
            $engine->setFragmentMode($this->fragmentMode);
        }

        $content = $engine->render($template, $data);

        $layout = $layout ?? $this->layout;
        if ($layout && !$this->fragmentMode) {
            return $engine->render($layout, array_merge($data, ['content' => $content]));
        }

        return $content;
    }
}
