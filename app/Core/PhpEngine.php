<?php

namespace DGLab\Core;

use DGLab\Core\Contracts\ViewEngineInterface;

/**
 * Class PhpEngine
 *
 * The default PHP view engine.
 *
 * @package DGLab\Core
 */
class PhpEngine implements ViewEngineInterface
{
    /**
     * @var View
     */
    private View $view;

    public function __construct(View $view)
    {
        $this->view = $view;
    }

    /**
     * Render the PHP view file.
     *
     * @param string $path
     * @param array $data
     * @return string
     */
    public function render(string $path, array $data = []): string
    {
        extract($data);
        ob_start();

        try {
            include $path;
        } catch (\Throwable $e) {
            ob_get_clean();
            throw $e;
        }

        return ob_get_clean();
    }

    /**
     * Forward calls to the View instance.
     */
    public function __call(string $method, array $args): mixed
    {
        return call_user_func_array([$this->view, $method], $args);
    }

    /**
     * Legacy yield support
     */
    public function yield(string $name, string $default = ''): string
    {
        return $this->view->yield($name, $default);
    }

    /**
     * Legacy section support
     */
    public function section(string $name): void
    {
        $this->view->section($name);
    }

    /**
     * Legacy endSection support
     */
    public function endSection(): void
    {
        $this->view->endSection();
    }
}
