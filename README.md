# SugiPHP Container

[![Build Status](https://travis-ci.org/SugiPHP/Container.png)](https://travis-ci.org/SugiPHP/Container)

Version 2 of the SugiPHP Container implements [`ContainerInterface`](https://github.com/container-interop/container-interop/blob/master/src/Interop/Container/ContainerInterface.php)

## Installation

```shell
# stable version (when available)
composer require sugiphp/container ~2.0

# development
composer require sugiphp/container ~2.@dev
```

## Usage

Container is able to store two different data types: objects (services) and parameters.

### Store values

```php
<?php
$container = new Container();
// store a parameter
$container->set("param", "value");
// store an object
$container->set("pdo", function() {
	return new PDO("mysql:dbname=testdb;host=127.0.0.1", "user", "pass");
});
?>
```

### Get previously stored values and objects

```php
<?php
$container->get("param"); // returns "value"
$container->get("unset"); // will throw an NotFoundException
$db = $container->get("pdo"); // returns instance of a PDO (not the closure itself, but the result);
// later in a code...
$db1 = $container->get("pdo"); // returns the SAME instance of the PDO (not new instance!) ($db1 === $db)

// if you need a new instance of the PDO you can force it with factory() method
$db2 = $container->factory("pdo"); // returns new instance of the PDO.
// the second instance is not stored in a container, so if you use factory again
$db3 = $container->factory("pdo"); // you'll get third instance which is different from the instances above

$db4 = $container->get("pdo"); // will return same instance as the first one ($db4 === $db === $db1)
?>
```

#### Always get fresh copies (new instances)

```php
<?php
// Wrap closure in factory method
$container->set("rand", $container->factory(function() {
	return mt_rand();
}));

$rand1 = $container->get("rand");
$rand2 = $container->get("rand");
// both values will differ (unless your are extremely lucky)
?>
```

#### Get stored closures as they were stored

```php
<?php
$closure = $container->raw("pdo"); // this will return the closure, not the result
// so you can invoke it and make a new PDO instance
$db = $closure();
?>
```

#### Always get raw services

```php
<?php
$container->set("name", $container->raw(function() {
	return "John";
}));

is_string($container->get("name")); // FALSE
// actually it will return stored closure
?>
```

### Checking existence of a key

```php
<?php
$container->set("param", "value");
$container->set("null", NULL);

$container->has("param"); // TRUE
$container->has("null"); // TRUE
$container->has("unset"); // FALSE
?>
```

### Deleting keys

To delete a previously stored key use `delete($key)` method

### Overriding keys and locking them

```php
<?php
// set a "name"
$container->set("name", "John");
$container->get("name"); // "John"
// override a "name"
$container->set("name", "John Doe");
$container->get("name"); // "John Doe"

// lock a key
$container->lock("name");
// now if you try to override "name"
$container->set("name", "Foo Bar"); // will throw ContainerException
// or try to delete that key
$container->delete("name"); // will throw ContainerException
?>
```
Note that there is no `unlock()` method.


## Array Access

Container implements build in PHP ArrayAccess class, which means that you can store, fetch, check and delete values using array notation

```php
<?php
$container["foo"] = "bar";
echo $container["foo"]; // prints bar
$container["pdo"] = function () {
    return new PDO("mysql:dbname=testdb;host=127.0.0.1", "user", "pass");
};
$db = $container["pdo"]; // returns instance of the PDO class
// checking for existence
isset($container["foo"]); // TRUE
// delete a key
unset("foo");
// checking for existence
isset($container["foo"]); // FALSE
?>
```
Note that unlike typical arrays where trying to get a key which is not set will throw an error, container will remain silent and will return NULL.

You can use `foreach` construct as well.

## Property Access

Container uses magic `__set` and `__get` methods to allow access via properties

```php
$container->set("foo") = "bar";
$container["foo"] = "bar";
$container->foo = "bar";
// All of the above are doing the same job

$container->get("foo"); // "bar"
$container["foo"]; // "bar"
$container->foo; // "bar"
```
