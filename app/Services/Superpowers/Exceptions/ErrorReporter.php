<?php

namespace DGLab\Services\Superpowers\Exceptions;

use DGLab\Core\Application;

class ErrorReporter
{
    public function report(\Throwable $e): string
    {
        $viewPath = null;
        $viewLine = null;
        if ($e instanceof SuperpowersException) {
            $viewPath = $e->getViewPath();
            $viewLine = $e->getViewLine();
        }

        $snippet = "";
        if ($viewPath && $viewLine && file_exists($viewPath)) {
            $snippet = $this->generateSnippet($viewPath, $viewLine);
        }

        $displayFile = str_replace(Application::getInstance()->getBasePath(), '', $viewPath ?: $e->getFile());
        $displayLine = $viewLine ?: $e->getLine();

        ob_start();
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <title>Superpowers Error</title>
            <style>
                body { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; background: #0f172a; color: #f8fafc; margin: 0; padding: 2rem; line-height: 1.5; }
                .container { max-width: 1000px; margin: 0 auto; background: #1e293b; border-radius: 8px; overflow: hidden; border: 1px solid #334155; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5); }
                .header { background: #dc2626; padding: 1.5rem; color: white; font-weight: bold; }
                .content { padding: 2rem; }
                .message { font-size: 1.125rem; margin-bottom: 1rem; color: #f1f5f9; }
                .location { color: #94a3b8; font-size: 0.875rem; margin-bottom: 2rem; border-bottom: 1px solid #334155; padding-bottom: 1rem; }
                .snippet { background: #0f172a; border-radius: 6px; padding: 1rem; overflow-x: auto; border: 1px solid #334155; margin-bottom: 2rem; }
                .line { display: flex; gap: 1rem; }
                .ln { color: #475569; text-align: right; min-width: 2.5rem; user-select: none; }
                .highlight { background: #450a0a; border-left: 4px solid #dc2626; margin-left: -1rem; padding-left: calc(1rem - 4px); width: 100%; }
                pre { margin: 0; color: #94a3b8; font-size: 0.875rem; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">Superpowers Error</div>
                <div class="content">
                    <div class="message"><?php echo nl2br(htmlspecialchars($e->getMessage())); ?></div>
                    <div class="location">In <strong><?php echo htmlspecialchars($displayFile); ?></strong> on line <strong><?php echo $displayLine; ?></strong></div>
                    <?php if ($snippet): ?>
                        <div class="snippet"><?php echo $snippet; ?></div>
                    <?php endif; ?>
                    <div class="trace">
                        <strong>Stack Trace:</strong>
                        <pre><?php echo htmlspecialchars($e->getTraceAsString()); ?></pre>
                    </div>
                </div>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }

    private function generateSnippet(string $path, int $line): string
    {
        $lines = file($path);
        $contextLines = (int) Application::config('superpowers.errors.context_lines', 3);
        $start = max(0, $line - $contextLines - 1);
        $end = min(count($lines), $line + $contextLines);
        $output = "";
        for ($i = $start; $i < $end; $i++) {
            $isErrorLine = ($i + 1 === $line);
            $output .= "<div class='line" . ($isErrorLine ? " highlight" : "") . "'>";
            $output .= "<span class='ln'>" . ($i + 1) . "</span>";
            $output .= "<span>" . htmlspecialchars($lines[$i]) . "</span>";
            $output .= "</div>";
        }
        return $output;
    }
}
