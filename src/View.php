<?php
declare(strict_types=1);

namespace App;

/**
 * Renders views/<template>.php inside views/layout.php.
 * Templates get: $e (escape), $url, $asset, $csrf, plus the data you pass in.
 */
final class View
{
    public function __construct(
        private readonly string $viewsDir,
        private readonly string $publicDir,
        private readonly Session $session,
    ) {
    }

    public function render(string $template, array $data = []): string
    {
        $vars = $data + [
            'e'     => static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            'url'   => static fn (string $path): string => BASE_PATH . $path,
            'asset' => fn (string $path): string => $this->assetUrl($path),
            'csrf'  => $this->session->csrfToken(),
        ];

        $content = $this->capture($template, $vars);

        return $this->capture('layout', $vars + ['content' => $content]);
    }

    private function assetUrl(string $path): string
    {
        $file = $this->publicDir . '/assets/' . $path;
        $version = is_file($file) ? filemtime($file) : 0;

        return BASE_PATH . '/assets/' . $path . '?v=' . $version;
    }

    private function capture(string $__template, array $__vars): string
    {
        extract($__vars, EXTR_SKIP);
        ob_start();
        try {
            require $this->viewsDir . '/' . $__template . '.php';

            return (string) ob_get_contents();
        } finally {
            ob_end_clean();
        }
    }
}
