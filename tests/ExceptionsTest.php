<?php

declare(strict_types=1);

namespace SugiPHP\Container\Tests;

use SugiPHP\Container\ContainerException;
use SugiPHP\Container\NotFoundException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

/**
 * Tests PSR-11 exceptions
 */
#[CoversClass(ContainerException::class)]
#[CoversClass(NotFoundException::class)]
class ExceptionsTest extends TestCase
{
    public function testContainerExceptionImplementsPsrContainerExceptionInterface(): void
    {
        $this->assertInstanceOf(ContainerExceptionInterface::class, new ContainerException());
    }

    public function testNotFoundExceptionImplementsPsrContainerNotFoundExceptionInterface(): void
    {
        $this->assertInstanceOf(NotFoundExceptionInterface::class, new NotFoundException());
    }

    public function testNotFoundExceptionExtendsContainerException(): void
    {
        $this->assertInstanceOf(ContainerException::class, new NotFoundException());
    }
}
