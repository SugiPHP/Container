<?php

declare(strict_types=1);

namespace SugiPHP\Container\Tests;

use SugiPHP\Container\Container;
use SugiPHP\Container\ContainerException;
use SugiPHP\Container\NotFoundException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use StdClass;

/**
 * Container (PSR-11) Tests
 */
#[CoversClass(Container::class)]
#[UsesClass(ContainerException::class)]
#[UsesClass(NotFoundException::class)]
class ContainerTest extends TestCase
{
    public function testContainerImplementsContainerInteropInterface(): void
    {
        $this->assertInstanceOf(ContainerInterface::class, new Container());
    }

    public function testGetWithoutSetThrows(): void
    {
        $container = new Container();

        $this->expectException(NotFoundException::class);
        $container->get('param');
    }

    public function testHas(): void
    {
        $container = new Container();
        $container->set('param', 'value');
        $obj = new StdClass();
        $container->set('obj', $obj);
        $container->set('closure', function () {
            return new StdClass();
        });
        $container->set('null', null);

        $this->assertTrue($container->has('param'));
        $this->assertTrue($container->has('obj'));
        $this->assertTrue($container->has('closure'));
        $this->assertTrue($container->has('null'));
        $this->assertFalse($container->has('unset'));
    }

    public function testSetString(): void
    {
        $container = new Container();
        $container->set('param', 'value');

        $this->assertTrue($container->has('param'));
        $this->assertSame('value', $container->get('param'));
    }

    public function testSetInteger(): void
    {
        $container = new Container();
        $container->set('int', 4);

        $this->assertTrue($container->has('int'));
        $this->assertEquals('4', $container->get('int'));
        $this->assertSame(4, $container->get('int'));
    }

    public function testSetFalse(): void
    {
        $container = new Container();
        $container->set('false', false);

        $this->assertTrue($container->has('false'));
        $this->assertFalse($container->get('false'));
    }

    public function testSetTrue(): void
    {
        $container = new Container();
        $container->set('true', true);

        $this->assertTrue($container->has('true'));
        $this->assertTrue($container->get('true'));
    }

    public function testSetNull(): void
    {
        $container = new Container();
        $container->set('null', null);

        $this->assertTrue($container->has('null'));
        $this->assertNull($container->get('null'));
    }

    public function testSetObject(): void
    {
        $container = new Container();
        $obj = new StdClass();
        $container->set('obj', $obj);

        $this->assertSame($obj, $container->get('obj'));
    }

    public function testWithClosureIsInvoked(): void
    {
        $container = new Container();
        $container->set('StdClass', function () {
            return new StdClass();
        });

        $this->assertInstanceOf('StdClass', $container->get('StdClass'));
    }

    public function testDelete(): void
    {
        $container = new Container();
        $container->set('param', 'value');
        $this->assertTrue($container->has('param'));
        $this->assertSame('value', $container->get('param'));
        $container->delete('param');
        $this->assertFalse($container->has('param'));
    }

    public function testGetSetHas(): void
    {
        $container = new Container();
        $this->assertFalse($container->has('param'));

        $container->set('null', null);
        $this->assertTrue($container->has('null'));
        $this->assertNull($container->get('null'));

        $container->set('param', 'value');

        $this->assertTrue($container->has('param'));
        $this->assertSame('value', $container->get('param'));

        $obj = new StdClass();
        $container->set('obj', $obj);

        $this->assertTrue($container->has('obj'));
        $this->assertSame($obj, $container->get('obj'));

        $container->set('closure', function () use ($obj) {
            return $obj;
        });

        $this->assertTrue($container->has('closure'));
        $this->assertEquals($obj, $container->get('closure'));

        $container->delete('param');
        $container->delete('obj');
        $container->delete('closure');
        $container->delete('null');
        $this->assertFalse($container->has('param'));
        $this->assertFalse($container->has('obj'));
        $this->assertFalse($container->has('closure'));
        $this->assertFalse($container->has('null'));
    }

    public function testClosureIsInvokedFreshEveryCall(): void
    {
        $container = new Container();
        $container->set('closure', function () {
            return new StdClass();
        });

        // Container never caches — that's Resolver's job.
        $this->assertNotSame($container->get('closure'), $container->get('closure'));
    }

    public function testOverridingValueWithValue(): void
    {
        $container = new Container();
        $container->set('param', 'value');
        $container->set('param', 'other value');

        $this->assertSame('other value', $container->get('param'));
    }

    public function testOverridingClosureWithValue(): void
    {
        $container = new Container();
        $container->set('random', function () {
            return rand(1, 9);
        });
        $this->assertIsInt($container->get('random'));
        $container->set('random', 'a');
        $this->assertSame('a', $container->get('random'));
    }

    public function testOverridingValueWithClosure(): void
    {
        $container = new Container();
        $container->set('random', 'a');
        $this->assertSame('a', $container->get('random'));
        $container->set('random', function () {
            return rand(1, 9);
        });
        $this->assertIsInt($container->get('random'));
    }

    public function testOverridingClosureWithClosure(): void
    {
        $container = new Container();
        $container->set('random', function () {
            return rand(1, 9);
        });
        $this->assertIsInt($container->get('random'));
        $container->set('random', function () {
            return 'pi=' . (3 +  0.14);
        });
        $this->assertSame('pi=3.14', $container->get('random'));
    }

    public function testOverridingLockedValue(): void
    {
        $container = new Container();
        $container->set('param', 'value');

        $container->lock('param');
        $this->expectException(ContainerException::class);
        $container->set('param', 'foo');
    }

    public function testOverridingLockedValueHoldsOldOne(): void
    {
        $container = new Container();
        $container->set('param', 'value');
        $container->lock('param');
        try {
            $container->set('param', 'foo');
        } catch (ContainerException $e) {
            //
        }

        // check the param hold old value
        $this->assertSame('value', $container->get('param'));
    }

    public function testLocksForbidsDeletion(): void
    {
        $container = new Container();
        $container->set('param', 'value');
        $container->lock('param');
        $this->expectException(ContainerException::class);
        $container->delete('param');
    }

    public function testLocksForbidsDeletionAndHoldsAValue(): void
    {
        $container = new Container();
        $container->set('param', 'value');
        $container->lock('param');
        try {
            $container->delete('param');
        } catch (ContainerException $e) {
            //
        }
        $this->assertSame('value', $container->get('param'));
    }

    public function testIsLocked(): void
    {
        $container = new Container();
        $this->assertFalse($container->isLocked('param'));
        $container->lock('param');
        $this->assertTrue($container->isLocked('param'));
    }

    public function testWrappingAClosureMakesGetReturnItUnwrapped(): void
    {
        $container = new Container();
        $function = function () {
            return 'value';
        };
        // The outer closure is invoked; it returns the captured inner one as-is.
        $container->set('func', function () use ($function) {
            return $function;
        });

        $this->assertSame($function, $container->get('func'));
    }

    public function testSettingInvokableObjectStoresItAsIs(): void
    {
        $container = new Container();
        $invokable = new class () {
            public function __invoke(): string
            {
                return 'invoked';
            }
        };
        $container->set('action', $invokable);

        $this->assertSame($invokable, $container->get('action'));
    }
}
