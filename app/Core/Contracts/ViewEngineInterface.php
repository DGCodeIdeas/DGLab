<?php

namespace DGLab\Core\Contracts;

/**
 * Interface ViewEngineInterface
 *
 * Defines the contract for view rendering engines.
 *
 * @package DGLab\Core\Contracts
 */
interface ViewEngineInterface
{
    /**
     * Render a view file with the given data.
     *
     * @param string $path The absolute path to the view file.
     * @param array $data The data to be passed to the view.
     * @return string The rendered content.
     */
    public function render(string $path, array $data = []): string;
}
