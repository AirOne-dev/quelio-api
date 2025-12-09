<?php

class ManifestController
{
    /**
     * Generate PWA manifest
     * GET /manifest.json
     */
    public function indexAction(): void
    {
        // Get colors from query parameters
        $primary = $_GET['primary'] ?? 'DC2626';
        $secondary = $_GET['secondary'] ?? '059669';
        $background = $_GET['background'] ?? '1a1d29';

        // Sanitize colors (remove # if present and validate hex)
        $primary = ltrim($primary, '#');
        $secondary = ltrim($secondary, '#');
        $background = ltrim($background, '#');

        // Validate hex colors (6 characters, alphanumeric)
        if (!preg_match('/^[0-9A-Fa-f]{6}$/', $primary)) {
            $primary = 'DC2626';
        }
        if (!preg_match('/^[0-9A-Fa-f]{6}$/', $secondary)) {
            $secondary = '059669';
        }
        if (!preg_match('/^[0-9A-Fa-f]{6}$/', $background)) {
            $background = '1a1d29';
        }

        // Add # prefix
        $backgroundColor = '#' . $background;
        $themeColor = $backgroundColor;

        // Get the base URL
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        $baseUrl = $protocol . '://' . $host;

        // Get the directory path
        $scriptPath = dirname($_SERVER['SCRIPT_NAME']);
        $apiPath = $scriptPath;
        $appPath = dirname($apiPath);

        $iconUrl = $baseUrl . $apiPath . '/icon.svg?primary=' . urlencode($primary) . '&secondary=' . urlencode($secondary);

        // Generate manifest
        $manifest = [
            'name' => 'Quel io',
            'short_name' => 'Quel io',
            'description' => 'Suivez vos horaires de travail',
            'start_url' => $appPath . '/',
            'display' => 'standalone',
            'background_color' => $backgroundColor,
            'theme_color' => $themeColor,
            'orientation' => 'portrait',
            'icons' => [
                [
                    'src' => $iconUrl,
                    'sizes' => '512x512',
                    'type' => 'image/svg+xml',
                    'purpose' => 'any maskable'
                ],
                [
                    'src' => $iconUrl,
                    'sizes' => '192x192',
                    'type' => 'image/svg+xml'
                ],
                [
                    'src' => $iconUrl,
                    'sizes' => '144x144',
                    'type' => 'image/svg+xml'
                ]
            ]
        ];

        JsonResponse::success($manifest);
    }
}
