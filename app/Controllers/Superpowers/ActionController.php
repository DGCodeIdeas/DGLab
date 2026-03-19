<?php

namespace DGLab\Controllers\Superpowers;

use DGLab\Core\Controller;
use DGLab\Core\Request;
use DGLab\Core\Response;
use DGLab\Core\View;
use DGLab\Core\Application;

/**
 * Class ActionController
 *
 * Handles Superpowers reactive actions.
 */
class ActionController extends Controller
{
    public function handle(Request $request): Response
    {
        $action = $request->input('action');
        $viewName = $request->input('view');
        $stateEncrypted = $request->input('state');

        $view = new View();

        // Pass special parameters to trigger re-hydration and action in the engine
        $content = $view->render($viewName, [
            '__action' => $action,
            '__state' => $stateEncrypted
        ], null);

        return new Response(json_encode([
            'html' => $content
        ]), 200, ['Content-Type' => 'application/json']);
    }
}
