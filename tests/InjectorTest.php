<?php

declare(strict_types=1);

namespace SugiPHP\Container\Tests;

use SugiPHP\Container\Resolver;
use SugiPHP\Container\Injector;
use SugiPHP\Container\ContainerException;
use SugiPHP\Container\NotFoundException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * Injector (reflection-based autowiring) Tests
 */
#[CoversClass(Injector::class)]
#[UsesClass(Resolver::class)]
#[UsesClass(ContainerException::class)]
#[UsesClass(NotFoundException::class)]
class InjectorTest extends TestCase
{
    public function testLockingClassBeforeFirstAutowiringDoesNotBreakResolution(): void
    {
        $container = new Injector();
        $container->lock(AutowireSimple::class);

        $instance = $container->get(AutowireSimple::class);

        $this->assertInstanceOf(AutowireSimple::class, $instance);
        $this->assertSame($instance, $container->get(AutowireSimple::class));
    }

    public function testHasDoesNotAccumulateStateForClassesNeverInstantiated(): void
    {
        $container = new Injector();

        $before = $this->containerStateSnapshot($container);

        for ($i = 0; $i < 25; $i++) {
            $container->has(AutowireSimple::class);
            $container->has(AutowireWithDep::class);
            $container->has(AutowireWithDefault::class);
        }

        $after = $this->containerStateSnapshot($container);
        $this->assertSame($before, $after);
    }

    /**
     * Snapshot of the size of every stateful property on the container,
     * used to assert an operation has no lingering side effects.
     *
     * @return array<string, int|null>
     */
    private function containerStateSnapshot(Injector $container): array
    {
        $snapshot = [];
        foreach ((new \ReflectionObject($container))->getProperties() as $property) {
            $value = $property->getValue($container);
            $snapshot[$property->getName()] = match (true) {
                is_array($value), $value instanceof \Countable => count($value),
                default => null,
            };
        }
        return $snapshot;
    }

    public function testAutowiringSimpleClass(): void
    {
        $container = new Injector();
        $this->assertInstanceOf(AutowireSimple::class, $container->get(AutowireSimple::class));
    }

    public function testAutowiringReturnsSingleton(): void
    {
        $container = new Injector();
        $this->assertSame($container->get(AutowireSimple::class), $container->get(AutowireSimple::class));
    }

    public function testAutowiringUsesDefaultValueForUnresolvableClassTypeParameter(): void
    {
        $container = new Injector();
        $obj = $container->get(AutowireWithOptionalInterfaceDep::class);
        $this->assertNull($obj->dep);
    }

    public function testAutowiringResolvesTypedDependency(): void
    {
        $container = new Injector();
        $obj = $container->get(AutowireWithDep::class);
        $this->assertInstanceOf(AutowireWithDep::class, $obj);
        $this->assertInstanceOf(AutowireSimple::class, $obj->dep);
    }

    public function testAutowiringUsesDefaultScalarValue(): void
    {
        $container = new Injector();
        $obj = $container->get(AutowireWithDefault::class);
        $this->assertSame('default', $obj->value);
    }

    public function testAutowiringExplicitRegistrationTakesPrecedence(): void
    {
        $container = new Injector();
        $explicit = new AutowireSimple();
        $container->set(AutowireSimple::class, $explicit);
        $this->assertSame($explicit, $container->get(AutowireSimple::class));
    }

    public function testAutowiringFailsForInterface(): void
    {
        $container = new Injector();
        $this->expectException(NotFoundException::class);
        $container->get(AutowireInterface::class);
    }

    public function testAutowiringFailsForUnresolvableScalar(): void
    {
        $container = new Injector();
        $this->expectException(NotFoundException::class);
        $container->get(AutowireWithScalar::class);
    }

    public function testAutowiringDetectsCircularDependency(): void
    {
        $container = new Injector();
        $this->expectException(ContainerException::class);
        $container->get(AutowireCircularA::class);
    }

    public function testAutowiringRejectsVariadicParameter(): void
    {
        $container = new Injector();
        $this->expectException(NotFoundException::class);
        $container->get(AutowireWithVariadicDep::class);
    }

    public function testAutowiringWrapsConstructorExceptionInContainerException(): void
    {
        $container = new Injector();

        try {
            $container->get(AutowireThrowingConstructor::class);
            $this->fail('Expected a ContainerException to be thrown.');
        } catch (ContainerException $e) {
            $this->assertInstanceOf(\RuntimeException::class, $e->getPrevious());
            $this->assertSame('boom', $e->getPrevious()->getMessage());
        }
    }

    public function testAutowiringWrapsNestedUnresolvableParameterWithContext(): void
    {
        $container = new Injector();

        try {
            $container->get(AutowireWithNestedUnresolvableDep::class);
            $this->fail('Expected a ContainerException to be thrown.');
        } catch (ContainerException $e) {
            $this->assertStringContainsString('AutowireWithNestedUnresolvableDep', $e->getMessage());
            $this->assertStringContainsString('dep', $e->getMessage());
            $this->assertInstanceOf(NotFoundException::class, $e->getPrevious());
            $this->assertStringContainsString('dsn', $e->getPrevious()->getMessage());
        }
    }

    public function testAutowiringWrapsBoundClassFailureInContainerException(): void
    {
        $container = new Injector();
        $container->bind(AutowireInterface::class, AutowireThrowingConstructor::class);

        try {
            $container->get(AutowireInterface::class);
            $this->fail('Expected a ContainerException to be thrown.');
        } catch (ContainerException $e) {
            $this->assertStringContainsString('AutowireInterface', $e->getMessage());
            $this->assertInstanceOf(ContainerException::class, $e->getPrevious());
            $this->assertInstanceOf(\RuntimeException::class, $e->getPrevious()->getPrevious());
        }
    }

    public function testBindResolvesInterfaceToConcreteClass(): void
    {
        $container = new Injector();
        $container->bind(AutowireInterface::class, AutowireSimple::class);
        $this->assertInstanceOf(AutowireSimple::class, $container->get(AutowireInterface::class));
    }

    public function testBindResolvesInterfaceDependencyInConstructor(): void
    {
        $container = new Injector();
        $container->bind(AutowireInterface::class, AutowireSimple::class);
        $obj = $container->get(AutowireWithInterfaceDep::class);
        $this->assertInstanceOf(AutowireWithInterfaceDep::class, $obj);
        $this->assertInstanceOf(AutowireSimple::class, $obj->dep);
    }

    public function testResolvingBoundInterfaceDoesNotDestroyTheBinding(): void
    {
        $container = new Injector();
        $container->bind(AutowireInterface::class, AutowireSimple::class);
        $container->get(AutowireInterface::class);

        $bindings = (new \ReflectionProperty(Injector::class, 'bindings'))->getValue($container);
        $this->assertSame(AutowireSimple::class, $bindings[AutowireInterface::class]);
    }

    public function testBindOverridesExistingSetDefinition(): void
    {
        $container = new Injector();
        $container->set(AutowireInterface::class, 'not-a-real-instance');
        $container->bind(AutowireInterface::class, AutowireSimple::class);

        $this->assertInstanceOf(AutowireSimple::class, $container->get(AutowireInterface::class));
    }

    public function testSetOverridesExistingBind(): void
    {
        $container = new Injector();
        $container->bind(AutowireInterface::class, AutowireSimple::class);
        $explicit = new AutowireSimple();
        $container->set(AutowireInterface::class, $explicit);

        $this->assertSame($explicit, $container->get(AutowireInterface::class));
    }

    public function testHasReturnsFalseWhenBoundConcreteClassDoesNotExist(): void
    {
        $container = new Injector();
        $container->bind(AutowireInterface::class, 'SugiPHP\\Container\\Tests\\TypoClassNameThatDoesNotExist');

        $this->assertFalse($container->has(AutowireInterface::class));
    }

    public function testAutowiringInvokableClassIsReturnedAsIsOnRepeatedGet(): void
    {
        $container = new Injector();

        $first = $container->get(AutowireInvokable::class);
        $second = $container->get(AutowireInvokable::class);

        $this->assertInstanceOf(AutowireInvokable::class, $first);
        $this->assertSame($first, $second);
    }
}

// Fixture classes for autowiring tests

class AutowireSimple implements AutowireInterface
{
}

class AutowireInvokable
{
    public function __invoke(): string
    {
        return 'invoked';
    }
}

class AutowireWithVariadicDep
{
    /** @var AutowireSimple[] */
    public array $deps;

    public function __construct(AutowireSimple ...$deps)
    {
        $this->deps = $deps;
    }
}

class AutowireWithDep
{
    public function __construct(public readonly AutowireSimple $dep)
    {
    }
}

class AutowireWithDefault
{
    public function __construct(public readonly string $value = 'default')
    {
    }
}

interface AutowireInterface
{
}

class AutowireWithInterfaceDep
{
    public function __construct(public readonly AutowireInterface $dep)
    {
    }
}

interface AutowireUnboundInterface
{
}

class AutowireWithOptionalInterfaceDep
{
    public function __construct(public readonly ?AutowireUnboundInterface $dep = null)
    {
    }
}

class AutowireWithScalar
{
    public function __construct(public readonly string $dsn)
    {
    }
}

class AutowireThrowingConstructor
{
    public function __construct()
    {
        throw new \RuntimeException('boom');
    }
}

class AutowireWithNestedUnresolvableDep
{
    public function __construct(public readonly AutowireWithScalar $dep)
    {
    }
}

class AutowireCircularA
{
    public function __construct(public readonly AutowireCircularB $b)
    {
    }
}

class AutowireCircularB
{
    public function __construct(public readonly AutowireCircularA $a)
    {
    }
}
