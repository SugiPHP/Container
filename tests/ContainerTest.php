<?php
/**
 * @package    SugiPHP
 * @subpackage Container
 * @category   tests
 * @author     Plamen Popov <tzappa@gmail.com>
 * @license    http://opensource.org/licenses/mit-license.php (MIT License)
 */

use SugiPHP\Container\Container;

class MyClass {}

class ContainerTest extends PHPUnit_Framework_TestCase
{
	public function testContainerCanBeCreated()
	{
		$this->assertInstanceOf("SugiPHP\Container\Container", new Container());
	}

	public function testGetWithoutSetReturnsNull()
	{
		$container = new Container();

		$this->assertNull($container->get("param"));
	}

	public function testHas()
	{
		$container = new Container();
		$container->set("param", "value");
		$obj = new MyClass();
		$container->set("obj", $obj);
		$container->set("closure", function () {
			return new MyClass();
		});
		$container->set("null", null);

		$this->assertTrue($container->has("param"));
		$this->assertTrue($container->has("obj"));
		$this->assertTrue($container->has("closure"));
		$this->assertTrue($container->has("null"));
		$this->assertFalse($container->has("unset"));
	}

	public function testSetString()
	{
		$container = new Container();
		$container->set("param", "value");

		$this->assertTrue($container->has("param"));
		$this->assertSame("value", $container->get("param"));
	}

	public function testSetInteger()
	{
		$container = new Container();
		$container->set("int", 4);

		$this->assertTrue($container->has("int"));
		$this->assertEquals('4', $container->get("int"));
		$this->assertSame(4, $container->get("int"));
	}

	public function testSetFalse()
	{
		$container = new Container();
		$container->set("false", false);

		$this->assertTrue($container->has("false"));
		$this->assertFalse($container->get("false"));
	}

	public function testSetTrue()
	{
		$container = new Container();
		$container->set("true", true);

		$this->assertTrue($container->has("true"));
		$this->assertTrue($container->get("true"));
	}

	public function testSetNull()
	{
		$container = new Container();
		$container->set("null", null);

		$this->assertTrue($container->has("null"));
		$this->assertNull($container->get("null"));
	}

	public function testSetObject()
	{
		$container = new Container();
		$obj = new MyClass();
		$container->set("obj", $obj);

		$this->assertSame($obj, $container->get("obj"));
	}

	public function testWithClosure()
	{
		$container = new Container();
		$container->set("myclass", function() {
			return new MyClass();
		});

		$this->assertInstanceOf("MyClass", $container->get("myclass"));
	}

	public function testDelete()
	{
		$container = new Container();
		$container->set("param", "value");
		$this->assertTrue($container->has("param"));
		$this->assertSame("value", $container->get("param"));
		$container->delete("param");
		$this->assertNull($container->get("param"));
		$this->assertFalse($container->has("param"));
	}

	public function testArrayAccess()
	{
		$container = new Container();
		$this->assertFalse(isset($container["param"]));
		$this->assertNull($container["param"]);

		$container["null"] = null;
		$this->assertTrue(isset($container["null"]));
		$this->assertNull($container["null"]);

		$container["param"] = "value";

		$this->assertTrue(isset($container["param"]));
		$this->assertSame("value", $container["param"]);

		$obj = new MyClass();
		$container["obj"] = $obj;

		$this->assertTrue(isset($container["obj"]));
		$this->assertSame($obj, $container["obj"]);

		$container["closure"] = function() {
			return new MyClass();
		};

		$this->assertTrue(isset($container["closure"]));
		$this->assertEquals($obj, $container["closure"]);

		unset($container["param"], $container["obj"], $container["closure"], $container["null"]);
		$this->assertFalse(isset($container["param"]));
		$this->assertNull($container["param"]);
		$this->assertFalse(isset($container["obj"]));
		$this->assertNull($container["obj"]);
		$this->assertFalse(isset($container["closure"]));
		$this->assertNull($container["closure"]);
		$this->assertFalse(isset($container["null"]));
		$this->assertNull($container["null"]);
	}

	public function testIteratable()
	{
		$container = new Container();
		$container["null"] = null;
		$container["param"] = "value";
		$container->set("one", 1);
		$container->set("closure", function() {
			return new MyClass();
		});

		foreach ($container as $key => $value) {
			$this->assertTrue($container->has($key));
			$this->assertSame($value, $container->get($key));
		}
	}

	public function testHasWithArrayAccess()
	{
		$container = new Container();
		$container["param"] = "value";
		$container["obj"] = new MyClass();
		$container["closure"] = function () {
			return new MyClass();
		};
		$container["null"] = null;

		$this->assertTrue(isset($container["param"]));
		$this->assertTrue(isset($container["closure"]));
		$this->assertTrue(isset($container["null"]));
		$this->assertTrue(isset($container["obj"]));
		$this->assertFalse(isset($container["unset"]));
	}

	public function testGetSameClosureTwice()
	{
		$container = new Container();
		$container->set("closure", function() {
			return new MyClass();
		});

		$this->assertSame($container->get("closure"), $container->get("closure"));
	}

	public function testGetFactory()
	{
		$container = new Container();
		$container->set("closure", function() {
			return new MyClass();
		});

		$this->assertEquals($container->factory("closure"), $container->factory("closure"));
		$this->assertNotSame($container->factory("closure"), $container->factory("closure"));
	}

	public function testOverriding()
	{
		$container = new Container();
		$container->set("param", "value");
		$container->set("param", "other value");

		$this->assertSame("other value", $container->get("param"));
	}

	public function testOverridingLockedValue()
	{
		$container = new Container();
		$container->set("param", "value");

		$container->lock("param");
		$this->setExpectedException("SugiPHP\Container\Exception");
		$container->set("param", "foo");
	}

	public function testOverridingLockedValueHoldsOldOne()
	{
		$container = new Container();
		$container->set("param", "value");
		$container->lock("param");
		try {
			$container->set("param", "foo");
		} catch (SugiPHP\Container\Exception $e) {
			//
		}

		// check the param hold old value
		$this->assertSame("value", $container->get("param"));
	}

	public function testLocksForbidsDeletion()
	{
		$container = new Container();
		$container->set("param", "value");
		$container->lock("param");
		$this->setExpectedException("SugiPHP\Container\Exception");
		$container->delete("param");
	}

	public function testLocksForbidsDeletionAndHoldsAValue()
	{
		$container = new Container();
		$container->set("param", "value");
		$container->lock("param");
		try {
			$container->delete("param");
		} catch (SugiPHP\Container\Exception $e) {
			//
		}
		$this->assertSame("value", $container->get("param"));
	}

	public function testGetRawFunction()
	{
		$container = new Container();
		$function = function () {
			return "value";
		};
		$container->set("func", $function);
		$this->assertSame($function, $container->raw("func"));
	}

	public function testSettingRaWForClosures()
	{
		$container = new Container();
		$function = function () {
			return "value";
		};
		$container->set("func", $container->raw($function));

		$this->assertSame($function, $container->get("func"));
	}

	public function testNullValueGetRaw()
	{
		$container = new Container();
		$container["null"] = null;
		$this->assertNull($container->raw("null"));
	}

	public function testGetRawCanBeUsedForDefinigFreshObjects()
	{
		$container = new Container();
		$container->set("closure", function() {
			return new MyClass();
		});

		$MyClass = $container->raw("closure");
		$this->assertInstanceOf("MyClass", $MyClass());

		$this->assertEquals($MyClass(), $MyClass());
		$this->assertNotSame($MyClass(), $MyClass());
	}

	public function testSettingFactoryForClosures()
	{
		$container = new Container();
		$container->set("closure", $container->factory(function() {
			return new MyClass();
		}));

		$this->assertEquals($container->get("closure"), $container->get("closure"));
		$this->assertNotSame($container->get("closure"), $container->get("closure"));
	}
}
