<?php

declare(strict_types=1);

namespace SugiPHP\Container;

use Psr\Container\ContainerExceptionInterface;
use Exception;

/**
 * Container Exception that implements PSR-11 Container Exception Interface
 */
class ContainerException extends Exception implements ContainerExceptionInterface
{
}
