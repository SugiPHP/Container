<?php

declare(strict_types=1);

namespace SugiPHP\Container;

use Psr\Container\ContainerInterface;

/**
 * Plain PSR-11 container: stores definitions and returns them as-is, invoking
 * closures fresh on every get() call.
 */
class Container implements ContainerInterface
{
    /**
     * @var array<string, mixed>
     */
    private array $definitions = [];

    /**
     * @var array<string, bool>
     */
    private array $locks = [];

    /**
     * Sets a parameter defined in an unique key ID.
     * You can set objects as a closures.
     *
     * @param string $id    Identifier of the entry to set
     * @param mixed  $value Value or closure function
     *
     * @throws ContainerException If the key is locked
     */
    public function set(string $id, mixed $value): void
    {
        $this->assertNotLocked($id, 'override');
        $this->definitions[$id] = $value;
    }

    /**
     * Finds an entry of the container by its identifier and returns it.
     * A closure definition is always invoked fresh — nothing is cached here.
     *
     * @param string $id Identifier of the entry to look for.
     *
     * @throws NotFoundException No entry was found for this identifier.
     */
    public function get(string $id): mixed
    {
        if (!array_key_exists($id, $this->definitions)) {
            throw new NotFoundException("No entry was found for the identifier '{$id}'");
        }

        $definition = $this->definitions[$id];

        return $definition instanceof \Closure ? $definition() : $definition;
    }

    public function has(string $id): bool
    {
        return array_key_exists($id, $this->definitions);
    }

    /**
     * Unsets a parameter or an object.
     *
     * @param string $id Key name
     *
     * @throws ContainerException If the key is locked
     */
    public function delete(string $id): void
    {
        $this->assertNotLocked($id, 'delete');
        unset($this->definitions[$id]);
    }

    /**
     * Lock the key, so it cannot be overwritten.
     * Note that there is no unlock method and will never be!
     *
     * @param string $id Key name
     */
    public function lock(string $id): void
    {
        $this->locks[$id] = true;
    }

    public function isLocked(string $id): bool
    {
        return !empty($this->locks[$id]);
    }

    private function assertNotLocked(string $id, string $action): void
    {
        if ($this->isLocked($id)) {
            throw new ContainerException("Cannot {$action} locked key '{$id}'");
        }
    }
}
