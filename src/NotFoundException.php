<?php
/**
 * SugiPHP Container Exception
 *
 * @package SugiPHP.Container
 * @author  Plamen Popov <tzappa@gmail.com>
 * @license http://opensource.org/licenses/mit-license.php (MIT License)
 */

namespace SugiPHP\Container;

class NotFoundException extends ContainerException implements \Interop\Container\Exception\NotFoundException
{
}
