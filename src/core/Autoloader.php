<?php

/**
 * PSR-4 Autoloader
 * Automatically loads classes from the src/ directory
 */
class Autoloader
{
    private string $baseDir;

    public function __construct(string $baseDir)
    {
        $this->baseDir = rtrim($baseDir, '/') . '/';
    }

    /**
     * Register the autoloader
     */
    public function register(): void
    {
        spl_autoload_register([$this, 'loadClass']);
    }

    /**
     * Load a class file
     */
    private function loadClass(string $className): void
    {
        // Try multiple locations
        $paths = [
            // Core classes (Router, Container, ServiceProvider)
            $this->baseDir . 'core/' . $className . '.php',
            // HTTP classes (JsonResponse, ActionController)
            $this->baseDir . 'http/' . $className . '.php',
            // Controllers
            $this->baseDir . 'controllers/' . $className . '.php',
            // Middleware
            $this->baseDir . 'middleware/' . $className . '.php',
            // Services
            $this->baseDir . 'services/' . $className . '.php',
            // Fallback: direct class in src/
            $this->baseDir . $className . '.php',
        ];

        foreach ($paths as $file) {
            if (file_exists($file)) {
                require_once $file;
                return;
            }
        }
    }
}
