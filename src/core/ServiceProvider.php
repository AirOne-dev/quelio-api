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
    public function register(): self
    {
        $this->container->singleton('array', fn() => $this->config);
        $this->registerCoreServices();

        return $this;
    }

    /**
     * Register core application services
     */
    private function registerCoreServices(): void
    {
        // AuthContext (shared instance for request lifecycle)
        // Will have Auth and Storage injected after they are registered
        $this->container
            ->singleton(AuthContext::class, fn() => new AuthContext())
            ->singleton(Storage::class, fn() => new Storage($this->config['debug_mode']))
            ->singleton(KelioClient::class, fn() => new KelioClient($this->config['kelio_url']))
            ->singleton(Auth::class, fn($c) => new Auth($c->get(Storage::class), $this->config['encryption_key']))
            ->singleton(AuthMiddleware::class, fn($c) =>
                new AuthMiddleware(
                    $c->get(Auth::class),
                    $c->get(KelioClient::class),
                    $c->get(AuthContext::class),
                    $c->get(RateLimiter::class),
                    $c->get(Storage::class),
                    $this->config
                )
            )
            ->singleton(TimeCalculator::class, fn() => new TimeCalculator($this->config))
            ->singleton(RateLimiter::class, fn() => new RateLimiter($this->config['rate_limit_max_attempts'], $this->config['rate_limit_window']));

        // Inject Auth and Storage into AuthContext
        $this->container
            ->get(AuthContext::class)
            ->setServices(
                $this->container->get(Auth::class),
                $this->container->get(Storage::class)
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
