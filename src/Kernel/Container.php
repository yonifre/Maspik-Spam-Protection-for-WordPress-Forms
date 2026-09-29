<?php

declare(strict_types=1);

namespace Maspik\Kernel;

if (! defined('ABSPATH')) {
    exit;
}


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
                // An \Error, not an \Exception. This is a defect - a service
                // asked for that was never registered, which in the field means
                // files from two releases running side by side after an update
                // that did not finish, or a stale opcode cache. Guard contains
                // \Error and deliberately lets \Exception through, because some
                // host plugins use exceptions as their refusal signal; as a
                // RuntimeException this one escaped every hook it was raised in
                // and came out as a 500 on the host's request.
                throw new \Error(sprintf('Maspik container: unknown service "%s".', $id));
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
