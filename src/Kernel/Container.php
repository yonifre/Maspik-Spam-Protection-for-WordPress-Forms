<?php

declare(strict_types=1);

namespace Maspik\Kernel;

/**
 * Minimal lazy service container. Intentionally not PSR-11 and not autowired:
 * every service is registered explicitly in a ServiceProvider, so the full
 * object graph is greppable.
 */
final class Container
{
    /** @var array<string, callable(Container): object> */
    private array $factories = [];

    /** @var array<string, object> */
    private array $instances = [];

    /**
     * @param callable(Container): object $factory
     */
    public function set(string $id, callable $factory): void
    {
        $this->factories[$id] = $factory;
        unset($this->instances[$id]);
    }

    /**
     * @template T of object
     * @param class-string<T>|string $id
     * @return T
     */
    public function get(string $id): object
    {
        if (! isset($this->instances[$id])) {
            if (! isset($this->factories[$id])) {
                throw new \RuntimeException(sprintf('Maspik container: unknown service "%s".', $id));
            }
            $this->instances[$id] = ($this->factories[$id])($this);
        }

        /** @var T */
        return $this->instances[$id];
    }

    public function has(string $id): bool
    {
        return isset($this->factories[$id]) || isset($this->instances[$id]);
    }
}
