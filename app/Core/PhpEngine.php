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
        } catch (\Exception $e) {
            ob_get_clean();
            throw $e;
        }

        return ob_get_clean();
    }
}
