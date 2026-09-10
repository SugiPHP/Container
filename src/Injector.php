<?php

declare(strict_types=1);

namespace SugiPHP\Container;

/**
 * Extends Resolver with reflection-based autowiring.
 * Autowired instances are cached as singletons.
 */
class Injector extends Resolver
{
    /**
     * Prevent circular references by tracking currently resolving keys
     *
     * @var array<string, bool>
     */
    private array $resolving = [];

    protected function isReachableAsIs(string $id): bool
    {
        return class_exists($id) && (new \ReflectionClass($id))->isInstantiable();
    }

    protected function resolveMissing(string $id): mixed
    {
        if (isset($this->bindings[$id])) {
            return parent::resolveMissing($id);
        }

        if ($this->isReachableAsIs($id)) {
            return $this->autowire($id);
        }

        throw new NotFoundException("No entry was found for the identifier '{$id}'");
    }

    private function autowire(string $id): mixed
    {
        if (isset($this->resolving[$id])) {
            throw new ContainerException("Circular dependency detected for '{$id}'");
        }
        $this->resolving[$id] = true;
        try {
            $ref = new \ReflectionClass($id);
            if (!$ref->isInstantiable()) {
                throw new NotFoundException("Cannot autowire '{$id}': not instantiable");
            }
            $constructor = $ref->getConstructor();
            if ($constructor === null) {
                $instance = new $id();
            } else {
                $args = [];
                foreach ($constructor->getParameters() as $param) {
                    $type = $param->getType();
                    if ($param->isVariadic()) {
                        throw new NotFoundException("Cannot autowire '{$id}': variadic parameter '\${$param->getName()}' must be registered manually");
                    } elseif ($type instanceof \ReflectionUnionType || $type instanceof \ReflectionIntersectionType) {
                        throw new NotFoundException("Cannot autowire '{$id}': parameter '\${$param->getName()}' has union/intersection type — register manually");
                    } elseif ($type instanceof \ReflectionNamedType && !$type->isBuiltin() && $this->has($type->getName())) {
                        try {
                            $args[] = $this->get($type->getName());
                        } catch (\Throwable $e) {
                            throw new ContainerException(
                                "Cannot autowire '{$id}': failed to resolve parameter '\${$param->getName()}' of type '{$type->getName()}'",
                                previous: $e
                            );
                        }
                    } elseif ($param->isDefaultValueAvailable()) {
                        $args[] = $param->getDefaultValue();
                    } elseif ($type instanceof \ReflectionNamedType && $type->isBuiltin()) {
                        throw new NotFoundException(
                            "Cannot autowire '{$id}': parameter '\${$param->getName()}' has an unsupported builtin type '{$type->getName()}' — register manually"
                        );
                    } elseif ($type instanceof \ReflectionNamedType) {
                        throw new NotFoundException(
                            "Cannot autowire '{$id}': unresolvable parameter '\${$param->getName()}' of type '{$type->getName()}'"
                        );
                    } else {
                        throw new NotFoundException(
                            "Cannot autowire '{$id}': parameter '\${$param->getName()}' has no type declaration — register manually"
                        );
                    }
                }
                try {
                    $instance = $ref->newInstanceArgs($args);
                } catch (\Throwable $e) {
                    throw new ContainerException("Cannot autowire '{$id}': constructor threw an exception", previous: $e);
                }
            }
            $this->resolved[$id] = $instance;
            return $instance;
        } finally {
            unset($this->resolving[$id]);
        }
    }
}
