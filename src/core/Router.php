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
     * @param string $controllerClass Controller class name
     * @param array $middlewares Optional middlewares for this route
     */
    public function addRoute(string $method, string $path, string $controllerClass, array $middlewares = []): self
    {
        $this->routes[] = [
            'method' => strtoupper($method),
            'path' => $path,
            'handler' => fn() => $this->dispatchController($controllerClass, 'index'),
            'middlewares' => array_map(function ($middlewareSpec) {
                // If it's an array like [ClassName::class, 'methodName']
                if (is_array($middlewareSpec) && count($middlewareSpec) === 2 && is_string($middlewareSpec[0]) && is_string($middlewareSpec[1])) {
                    $middleware = $this->container->get($middlewareSpec[0]);
                    return [$middleware, $middlewareSpec[1]];
                }
                // If it's just a class name string, use 'handle' method
                if (is_string($middlewareSpec)) {
                    $middleware = $this->container->get($middlewareSpec);
                    return [$middleware, 'handle'];
                }
                // Otherwise return as-is (already a callable)
                return $middlewareSpec;
            }, $middlewares)
        ];

        return $this;
    }

    /**
     * Add a GET route
     */
    public function get(string $path, string $controllerClass, array $middlewares = []): self
    {
        return $this->addRoute('GET', $path, $controllerClass, $middlewares);
    }

    /**
     * Add a POST route
     */
    public function post(string $path, string $controllerClass, array $middlewares = []): self
    {
        return $this->addRoute('POST', $path, $controllerClass, $middlewares);
    }

    /**
     * Add a GET and POST routes
     */
    public function getAndPost(string $path, string $controllerClass, array $middlewares = []): self
    {
        return $this
            ->addRoute('GET', $path, $controllerClass, $middlewares)
            ->addRoute('POST', $path, $controllerClass, $middlewares);
    }

    /**
     * Set the dependency injection container
     */
    public function setContainer(Container $container): self
    {
        $this->container = $container;

        return $this;
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

        // Decode URL-encoded characters (like %20 for space)
        $path = urldecode($path);

        // Get the script directory to handle subdirectories
        $scriptDir = dirname($_SERVER['SCRIPT_NAME']);

        // Normalize script directory (decode URL encoding)
        $scriptDir = urldecode($scriptDir);

        // Remove the script directory from the path for subdirectory support
        if ($scriptDir !== '/') {
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
        JsonResponse::error('Route not found', 404);
    }
}
