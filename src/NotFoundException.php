<?php

declare(strict_types=1);

namespace SugiPHP\Container;

use Psr\Container\NotFoundExceptionInterface;

/**
 * Container NotFound Exception that implements PSR-11 NotFoundExceptionInterface
 */
class NotFoundException extends ContainerException implements NotFoundExceptionInterface
{
}
