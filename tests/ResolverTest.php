<?php

declare(strict_types=1);

namespace SugiPHP\Container\Tests;

use SugiPHP\Container\Container;
use SugiPHP\Container\ContainerException;
use SugiPHP\Container\NotFoundException;
use SugiPHP\Container\Resolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use StdClass;

/**
 * Resolver (singleton caching + bindings) Tests
 */
#[CoversClass(Resolver::class)]
#[UsesClass(Container::class)]
#[UsesClass(ContainerException::class)]
#[UsesClass(NotFoundException::class)]
class ResolverTest extends TestCase
{
    public function testResolverImplementsContainerInteropInterface(): void
    {
        $this->assertInstanceOf(ContainerInterface::class, new Resolver());
    }

    public function testGetWithoutSetThrows(): void
    {
        $resolver = new Resolver();
        $this->expectException(NotFoundException::class);
        $resolver->get('param');
    }

    public function testSetAndGet(): void
    {
        $resolver = new Resolver();
        $resolver->set('param', 'value');

        $this->assertTrue($resolver->has('param'));
        $this->assertSame('value', $resolver->get('param'));
    }

    public function testGetCachesClosureResultAsSingleton(): void
    {
        $resolver = new Resolver();
        $resolver->set('closure', function () {
            return new StdClass();
        });

        $this->assertSame($resolver->get('closure'), $resolver->get('closure'));
    }

    public function testOverridingClosureInvalidatesCachedResult(): void
    {
        $resolver = new Resolver();
        $resolver->set('random', function () {
            return new StdClass();
        });
        $first = $resolver->get('random');

        $resolver->set('random', function () {
            return new StdClass();
        });
        $second = $resolver->get('random');

        $this->assertNotSame($first, $second);
    }

    public function testDelete(): void
    {
        $resolver = new Resolver();
        $resolver->set('param', 'value');
        $resolver->get('param');
        $resolver->delete('param');

        $this->assertFalse($resolver->has('param'));
        $this->expectException(NotFoundException::class);
        $resolver->get('param');
    }

    public function testMakeBypassesTheCache(): void
    {
        $resolver = new Resolver();
        $resolver->set('closure', function () {
            return new StdClass();
        });

        $this->assertSame($resolver->get('closure'), $resolver->get('closure'));
        $this->assertNotSame($resolver->make('closure'), $resolver->make('closure'));
        $this->assertNotSame($resolver->make('closure'), $resolver->get('closure'));
    }

    public function testMakeThrowsForUnregisteredId(): void
    {
        $resolver = new Resolver();
        $this->expectException(NotFoundException::class);
        $resolver->make('missing');
    }

    public function testSetFactoryReturnsFreshInstanceEveryGet(): void
    {
        $resolver = new Resolver();
        $resolver->setFactory('closure', function () {
            return new StdClass();
        });

        $this->assertNotSame($resolver->get('closure'), $resolver->get('closure'));
    }

    public function testOverwritingFactoryWithPlainSetCachesNormally(): void
    {
        $resolver = new Resolver();
        $resolver->setFactory('svc', function () {
            return new StdClass();
        });
        $this->assertNotSame($resolver->get('svc'), $resolver->get('svc'));

        $resolver->set('svc', function () {
            return new StdClass();
        });

        $this->assertSame($resolver->get('svc'), $resolver->get('svc'));
    }

    public function testDeletingThenResettingClearsFactoryMarking(): void
    {
        $resolver = new Resolver();
        $resolver->setFactory('svc', function () {
            return new StdClass();
        });
        $resolver->delete('svc');

        $resolver->set('svc', function () {
            return new StdClass();
        });

        $this->assertSame($resolver->get('svc'), $resolver->get('svc'));
    }

    public function testLockingForbidsOverridingAndDeleting(): void
    {
        $resolver = new Resolver();
        $resolver->set('param', 'value');
        $resolver->lock('param');

        $this->expectException(ContainerException::class);
        $resolver->set('param', 'other');
    }

    public function testBindResolvesToAnotherRegisteredId(): void
    {
        $resolver = new Resolver();
        $resolver->set('file.logger', function () {
            return new StdClass();
        });
        $resolver->bind('logger', 'file.logger');

        $this->assertSame($resolver->get('logger'), $resolver->get('file.logger'));
    }

    public function testBindDoesNotRequireReflection(): void
    {
        $resolver = new Resolver();
        $resolver->set('dsn', 'mysql:host=localhost');
        $resolver->bind('database.dsn', 'dsn');

        $this->assertSame('mysql:host=localhost', $resolver->get('database.dsn'));
    }

    public function testBindOverridesExistingSetDefinition(): void
    {
        $resolver = new Resolver();
        $resolver->set('logger', 'not-a-real-logger');
        $resolver->set('file.logger', 'a-real-logger');
        $resolver->bind('logger', 'file.logger');

        $this->assertSame('a-real-logger', $resolver->get('logger'));
    }

    public function testSetOverridesExistingBind(): void
    {
        $resolver = new Resolver();
        $resolver->set('file.logger', 'a-real-logger');
        $resolver->bind('logger', 'file.logger');
        $resolver->set('logger', 'explicit-value');

        $this->assertSame('explicit-value', $resolver->get('logger'));
    }

    public function testHasReturnsFalseForBindingToUnregisteredId(): void
    {
        $resolver = new Resolver();
        $resolver->bind('logger', 'file.logger');

        $this->assertFalse($resolver->has('logger'));
    }

    public function testHasReturnsFalseForCircularBinding(): void
    {
        $resolver = new Resolver();
        $resolver->bind('A', 'B');
        $resolver->bind('B', 'A');

        $this->assertFalse($resolver->has('A'));
    }

    public function testGetThrowsContainerExceptionForCircularBinding(): void
    {
        $resolver = new Resolver();
        $resolver->bind('A', 'B');
        $resolver->bind('B', 'A');

        $this->expectException(ContainerException::class);
        $resolver->get('A');
    }

    public function testBindForbidsRebindingLockedKey(): void
    {
        $resolver = new Resolver();
        $resolver->set('file.logger', 'a-real-logger');
        $resolver->bind('logger', 'file.logger');
        $resolver->lock('logger');

        $this->expectException(ContainerException::class);
        $resolver->bind('logger', 'other.logger');
    }

    public function testBindForbidsRebindingLockedKeyHoldsOldBinding(): void
    {
        $resolver = new Resolver();
        $resolver->set('file.logger', 'a-real-logger');
        $resolver->bind('logger', 'file.logger');
        $resolver->lock('logger');
        try {
            $resolver->bind('logger', 'other.logger');
        } catch (ContainerException $e) {
            //
        }

        $this->assertSame('a-real-logger', $resolver->get('logger'));
    }

}
