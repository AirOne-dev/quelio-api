<?php

/**
 * Service Provider
 * Automatically registers all services in the container
 */
class ServiceProvider
{
    public function __construct(
        private Container $container,
        private array $config
    ) {
    }

    /**
     * Register all services automatically
     */
    public function register(): void
    {
        // Register config as array
        $this->container->singleton('array', fn() => $this->config);

        // Register core services
        $this->registerCoreServices();
    }

    /**
     * Register core application services
     */
    private function registerCoreServices(): void
    {
        $config = $this->config;

        // AuthContext (shared instance for request lifecycle)
        $this->container->singleton(AuthContext::class, fn() => new AuthContext());

        // Storage (depends on debug_mode config)
        $this->container->singleton(Storage::class, fn() => new Storage($config['debug_mode']));

        // KelioClient (depends on config)
        $this->container->singleton(KelioClient::class, fn() =>
            new KelioClient($config['kelio_url'])
        );

        // Auth (depends on Storage and encryption key from config)
        $this->container->singleton(Auth::class, fn($c) =>
            new Auth($c->get(Storage::class), $config['encryption_key'])
        );

        // AuthMiddleware (depends on Auth, KelioClient, AuthContext, and RateLimiter)
        $this->container->singleton(AuthMiddleware::class, fn($c) =>
            new AuthMiddleware(
                $c->get(Auth::class),
                $c->get(KelioClient::class),
                $c->get(AuthContext::class),
                $c->get(RateLimiter::class)
            )
        );

        // TimeCalculator (depends on config)
        $this->container->singleton(TimeCalculator::class, fn() =>
            new TimeCalculator($config)
        );

        // RateLimiter (depends on config)
        $this->container->singleton(RateLimiter::class, fn() =>
            new RateLimiter($config['rate_limit_max_attempts'], $config['rate_limit_window'])
        );
    }

    /**
     * Get the container
     */
    public function getContainer(): Container
    {
        return $this->container;
    }
}
