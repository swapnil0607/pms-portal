<?php

namespace App\Core;

class View
{
    public static function render(string $template, array $data = [], string $layout = 'layout'): void
    {
        extract($data, EXTR_SKIP);
        $viewPath = __DIR__ . '/../Views/' . $template . '.php';
        $layoutPath = __DIR__ . '/../Views/' . $layout . '.php';

        ob_start();
        require $viewPath;
        $content = ob_get_clean();

        ob_start();
        require $layoutPath;
        $html = ob_get_clean();

        $basePath = app_base_path();
        if ($basePath !== '') {
            $quotedBase = preg_quote(ltrim($basePath, '/'), '/');
            $html = preg_replace(
                '/\b(href|src|action|data-copy-link)=([\'"])\/(?!\/|' . $quotedBase . '\/)/',
                '$1=$2' . $basePath . '/',
                $html
            );
        }

        echo $html;
    }
}
