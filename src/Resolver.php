<?php

declare(strict_types=1);

namespace SugiPHP\Container;

/**
 * Extends Container and adds singleton caching on top of it: get() invokes a
 * closure definition once and caches the result, unless the id was registered
 * via setFactory(). Also adds make() (bypass the cache for one call) and
 * bind() (resolve one id to another).
 */
class Resolver extends Container
{
    /**
     * Cached singleton values, keyed by id.
     *
     * @var array<string, mixed>
     */
    protected array $resolved = [];

    /**
     * Ids whose closure definition should always be invoked fresh instead of cached.
     *
     * @var array<string, true>
     */
    protected array $factories = [];

    /**
     * Id-to-id bindings: requesting the key resolves to get()-ing the value instead.
     *
     * @var array<string, string>
     */
    protected array $bindings = [];

    /**
     * Ids currently being resolved via a binding, to detect circular bindings.
     *
     * @var array<string, true>
     */
    private array $resolvingBindings = [];

    /**
     * @throws ContainerException If the key is locked
     */
    public function set(string $id, mixed $value): void
    {
        parent::set($id, $value);
        unset($this->resolved[$id], $this->factories[$id], $this->bindings[$id]);
    }

    /**
     * Registers a closure under $id and marks it so that get() always invokes
     * it fresh instead of caching a singleton.
     *
     * @throws ContainerException If the key is locked
     */
    public function setFactory(string $id, \Closure $closure): void
    {
        $this->set($id, $closure);
        $this->factories[$id] = true;
    }

    public function get(string $id): mixed
    {
        if (array_key_exists($id, $this->resolved)) {
            return $this->resolved[$id];
        }

        if (parent::has($id)) {
            $value = parent::get($id);
            if (isset($this->factories[$id])) {
                return $value;
            }
            $this->resolved[$id] = $value;
            return $value;
        }

        return $this->resolveMissing($id);
    }

    /**
     * Resolves $id right now, bypassing the singleton cache for this one call.
     * Only considers direct definitions — never bindings.
     *
     * @throws NotFoundException No entry was found for this identifier.
     */
    public function make(string $id): mixed
    {
        return parent::get($id);
    }

    public function has(string $id): bool
    {
        if ($this->isRegistered($id)) {
            return true;
        }

        $seen = [];
        while (isset($this->bindings[$id])) {
            if (isset($seen[$id])) {
                return false;
            }
            $seen[$id] = true;
            $id = $this->bindings[$id];
            if ($this->isRegistered($id)) {
                return true;
            }
        }

        return $this->isReachableAsIs($id);
    }

    /**
     * @throws ContainerException If the key is locked
     */
    public function delete(string $id): void
    {
        parent::delete($id);
        unset($this->resolved[$id], $this->factories[$id], $this->bindings[$id]);
    }

    /**
     * Registers $target as the id to resolve instead, whenever $id is requested.
     *
     * @throws ContainerException If $id is locked
     */
    public function bind(string $id, string $target): void
    {
        parent::delete($id);
        unset($this->resolved[$id], $this->factories[$id]);
        $this->bindings[$id] = $target;
    }

    /**
     * Called by has() once neither the cache, the container, nor a chain of
     * bindings reach a registered id. Base implementation always fails;
     * Injector overrides this to recognize instantiable class names.
     */
    protected function isReachableAsIs(string $id): bool
    {
        return false;
    }

    /**
     * Called by get() once $id was found in neither the cache nor the
     * container. Base implementation follows a binding, if any; Injector
     * overrides this to attempt autowiring before giving up.
     *
     * @throws NotFoundException
     */
    protected function resolveMissing(string $id): mixed
    {
        if (isset($this->bindings[$id])) {
            $target = $this->bindings[$id];
            if ($target === $id || isset($this->resolvingBindings[$target])) {
                throw new ContainerException("Circular binding detected for '{$id}'");
            }
            $this->resolvingBindings[$id] = true;
            try {
                return $this->get($target);
            } catch (\Throwable $e) {
                throw new ContainerException(
                    "Cannot resolve '{$id}': failed to resolve bound id '{$target}'",
                    previous: $e
                );
            } finally {
                unset($this->resolvingBindings[$id]);
            }
        }

        throw new NotFoundException("No entry was found for the identifier '{$id}'");
    }

    private function isRegistered(string $id): bool
    {
        return array_key_exists($id, $this->resolved) || parent::has($id);
    }
}
