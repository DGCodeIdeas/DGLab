<?php

namespace DGLab\Controllers\Superpowers;

use DGLab\Core\Controller;
use DGLab\Core\Request;
use DGLab\Core\Response;
use DGLab\Core\View;
use DGLab\Core\Application;
use DGLab\Services\Superpowers\Runtime\GlobalStateStoreInterface;

class ActionController extends Controller
{
    public function handle(Request $request): Response
    {
        $action = $request->input('action');
        $viewName = $request->input('view');
        $stateEncrypted = $request->input('state');
        $view = new View();
        $g = Application::getInstance()->get(GlobalStateStoreInterface::class);
        $before = $g->all();
        $content = $view->render($viewName, ['__action' => $action, '__state' => $stateEncrypted], null);
        $after = $g->all();
        $changed = [];
        foreach ($after as $k => $v) {
            if (!array_key_exists($k, $before) || $before[$k] !== $v) {
                $changed[$k] = $v;
            }
        }
        return new Response(json_encode(['html' => $content, 'changedPersisted' => $changed]), 200, ['Content-Type' => 'application/json']);
    }
}
