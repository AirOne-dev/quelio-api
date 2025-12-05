<?php

/**
 * Simple Dependency Injection Container
 * Manages service instantiation and resolution
 */
class Container
{
    private array $services = [];
    private array $singletons = [];

    /**
     * Register a service factory
     * @param string $name Service name
     * @param callable $factory Factory function that creates the service
     */
    public function set(string $name, callable $factory): void
    {
        $this->services[$name] = $factory;
    }

    /**
     * Register a singleton service
     * @param string $name Service name
     * @param callable $factory Factory function
     */
    public function singleton(string $name, callable $factory): self
    {
        $this->services[$name] = $factory;
        $this->singletons[$name] = null;

        return $this;
    }

    /**
     * Get a service instance
     * @param string $name Service name
     * @return mixed Service instance
     */
    public function get(string $name)
    {
        if (!isset($this->services[$name])) {
            throw new Exception("Service not found: $name");
        }

        // Return singleton if already instantiated
        if (array_key_exists($name, $this->singletons)) {
            if ($this->singletons[$name] === null) {
                $this->singletons[$name] = $this->services[$name]($this);
            }
            return $this->singletons[$name];
        }

        // Create new instance
        return $this->services[$name]($this);
    }

    /**
     * Check if a service exists
     */
    public function has(string $name): bool
    {
        return isset($this->services[$name]);
    }

    /**
     * Create a controller instance with automatic dependency injection
     * @param string $controllerClass Controller class name
     * @return object Controller instance
     */
    public function createController(string $controllerClass): object
    {
        // Use reflection to get constructor parameters
        $reflection = new ReflectionClass($controllerClass);
        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            return new $controllerClass();
        }

        $params = [];
        foreach ($constructor->getParameters() as $param) {
            $type = $param->getType();

            if ($type === null) {
                throw new Exception("Cannot resolve parameter {$param->getName()} in $controllerClass");
            }

            $typeName = $type->getName();

            // Try to get from container
            if ($this->has($typeName)) {
                $params[] = $this->get($typeName);
            } elseif (class_exists($typeName)) {
                // Try to create automatically if it's a class
                $params[] = $this->createController($typeName);
            } else {
                throw new Exception("Cannot resolve dependency: $typeName");
            }
        }

        return $reflection->newInstanceArgs($params);
    }
}
