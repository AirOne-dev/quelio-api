<?php

class Router
{
    private array $routes = [];
    private array $middlewares = [];
    private ?Container $container = null;

    /**
     * Add a route to the router
     * @param string $method HTTP method (GET, POST, etc.)
     * @param string $path Route path
     * @param callable $handler Route handler
     * @param array $middlewares Optional middlewares for this route
     */
    public function addRoute(string $method, string $path, callable $handler, array $middlewares = []): void
    {
        $this->routes[] = [
            'method' => strtoupper($method),
            'path' => $path,
            'handler' => $handler,
            'middlewares' => $middlewares
        ];
    }

    /**
     * Add a GET route
     */
    public function get(string $path, callable $handler, array $middlewares = []): void
    {
        $this->addRoute('GET', $path, $handler, $middlewares);
    }

    /**
     * Add a POST route
     */
    public function post(string $path, callable $handler, array $middlewares = []): void
    {
        $this->addRoute('POST', $path, $handler, $middlewares);
    }

    /**
     * Add a global middleware (runs on all routes)
     */
    public function addMiddleware(callable $middleware): void
    {
        $this->middlewares[] = $middleware;
    }

    /**
     * Set the dependency injection container
     */
    public function setContainer(Container $container): void
    {
        $this->container = $container;
    }

    /**
     * Register a route with automatic controller instantiation
     * Usage: $router->route('POST', '/path', ControllerClass::class, [OptionalMiddleware::class])
     *
     * @param string $method HTTP method (GET, POST, etc.)
     * @param string $path Route path
     * @param string $controllerClass Controller class name
     * @param array $middlewares Optional middleware classes
     */
    public function route(string $method, string $path, string $controllerClass, array $middlewares = []): void
    {
        // Convert middleware classes to instances
        $middlewareInstances = array_map(function ($middlewareClass) {
            if (is_string($middlewareClass)) {
                $middleware = $this->container->get($middlewareClass);
                return [$middleware, 'handle'];
            }
            return $middlewareClass;
        }, $middlewares);

        $this->addRoute($method, $path, function () use ($controllerClass) {
            $this->dispatchController($controllerClass, 'index');
        }, $middlewareInstances);
    }

    /**
     * Register a controller route with auto-routing (both GET and POST)
     * Convention: /path -> PathController::indexAction() or dispatch()
     *
     * @param string $path Route path
     * @param string $controllerClass Controller class name
     * @param array $middlewares Optional middlewares
     */
    public function controller(string $path, string $controllerClass, array $middlewares = []): void
    {
        $this->route('GET', $path, $controllerClass, $middlewares);
        $this->route('POST', $path, $controllerClass, $middlewares);
    }

    /**
     * Dispatch to a controller action
     */
    private function dispatchController(string $controllerClass, string $defaultAction = 'index'): void
    {
        if ($this->container === null) {
            throw new Exception("Container not set. Call setContainer() first.");
        }

        // Create controller instance with dependency injection
        $controller = $this->container->createController($controllerClass);

        // If controller extends ActionController, use dispatch
        if ($controller instanceof ActionController) {
            $controller->dispatch($defaultAction);
        } else {
            // Call the action directly
            $actionMethod = $defaultAction . 'Action';
            if (method_exists($controller, $actionMethod)) {
                $controller->$actionMethod();
            } else {
                throw new Exception("Action not found: $controllerClass::$actionMethod");
            }
        }
    }

    /**
     * Run the router
     */
    public function run(): void
    {
        $method = $_SERVER['REQUEST_METHOD'];
        $path = $_SERVER['REQUEST_URI'];

        // Remove query string
        $path = parse_url($path, PHP_URL_PATH);

        // Get the script directory to handle subdirectories
        $scriptDir = dirname($_SERVER['SCRIPT_NAME']);
        if ($scriptDir !== '/') {
            // Remove the script directory from the path
            if (str_starts_with($path, $scriptDir)) {
                $path = substr($path, strlen($scriptDir));
            }
        }

        // Ensure path starts with /
        if (!str_starts_with($path, '/')) {
            $path = '/' . $path;
        }

        // Remove trailing slash except for root
        if ($path !== '/' && str_ends_with($path, '/')) {
            $path = rtrim($path, '/');
        }

        foreach ($this->routes as $route) {
            if ($route['method'] === $method && $this->matchPath($route['path'], $path)) {
                try {
                    // Run global middlewares
                    foreach ($this->middlewares as $middleware) {
                        $result = $middleware();
                        if ($result !== null) {
                            return; // Middleware stopped the request
                        }
                    }

                    // Run route-specific middlewares
                    foreach ($route['middlewares'] as $middleware) {
                        $result = $middleware();
                        if ($result !== null) {
                            return; // Middleware stopped the request
                        }
                    }

                    // Run the route handler
                    call_user_func($route['handler']);
                    return;
                } catch (\Throwable $e) {
                    $this->handleError($e);
                    return;
                }
            }
        }

        // No route found
        $this->notFound();
    }

    /**
     * Match a route path with the current path
     */
    private function matchPath(string $routePath, string $currentPath): bool
    {
        // Simple exact match for now
        // Can be extended to support parameters like /users/:id
        return $routePath === $currentPath;
    }

    /**
     * Handle errors
     */
    private function handleError(\Throwable $e): void
    {
        JsonResponse::serverError('Internal server error', $e);
    }

    /**
     * Handle 404
     */
    private function notFound(): void
    {
        JsonResponse::notFound('Route not found');
    }
}
