# SugiPHP\Container

**Version 3.0** — PSR-11 compatible dependency injection container.

There are three classes, each adding one capability on top of the previous:

- **`Container`** — plain PSR-11 storage: `set()` / `get()` / `has()` / `delete()` / `lock()`.
  A closure definition is invoked fresh on every `get()` call — nothing is cached.
- **`Resolver`** — extends `Container` and adds singleton caching, `setFactory()`
  (opt a specific id out of caching), `make()` (bypass the cache for one call),
  and `bind()` (resolve one id to another).
- **`Injector`** — extends `Resolver` and adds reflection-based autowiring:
  an id that is neither registered nor bound is constructed automatically if
  it names an instantiable class.

Pick the class that matches what you need — a `Resolver` for singleton
services without reflection, an `Injector` for full autowiring.

## Features

- Get and set values, objects, and closures by string key
- `Resolver` caches closure results as singletons
- `setFactory()` — register a closure so each `get()` call returns a fresh instance
- `make()` — resolve an id right now, bypassing the singleton cache for that one call
- `bind()` — resolve one id by getting another instead
- `lock()` — prevent a key from being overwritten or deleted
- `Injector` automatically resolves classes and their dependencies via reflection
- Implements `Psr\Container\ContainerInterface`

## Installation

```php
use SugiPHP\Container\Container;

$c = new Container();
```

## Usage

### Scalar values

```php
$c->set('debug', true);
$c->get('debug'); // true

$c->set('dsn', 'mysql:host=localhost;dbname=app');
$c->get('dsn'); // 'mysql:host=localhost;dbname=app'
$c->has('dsn'); // true
$c->delete('dsn');
```

### Objects

```php
$c->set('db', new PDO($c->get('dsn')));
$c->get('db'); // the PDO instance
```

### Closures

`Container` invokes a closure definition fresh on every `get()` call:

```php
$c = new Container();
$c->set('db', function () use ($c) {
    return new PDO($c->get('dsn'));
});

$c->get('db') === $c->get('db'); // false — a new instance every call
```

Use `Resolver` when you want the result cached as a singleton instead:

```php
use SugiPHP\Container\Resolver;

$c = new Resolver();
$c->set('db', function () use ($c) {
    return new PDO($c->get('dsn'));
});

$c->get('db') === $c->get('db'); // true — same instance
```

### Factory (fresh instance each call, on a Resolver)

```php
// Register a closure so each get() returns a new instance, even through a Resolver
$c->setFactory('request', function () {
    return new Request();
});

$c->get('request') === $c->get('request'); // false — new instance each time
```

### Make (bypass the singleton cache for one call, on a Resolver)

```php
$c->set('request', function () {
    return new Request();
});

$c->get('request') === $c->get('request'); // true — cached singleton

$c->make('request'); // a fresh Request, this one call only — 'request' is still a singleton afterward

$c->make('missing'); // throws NotFoundException, same as get()
```

### Storing a closure as a value

`set()` invokes a stored closure (to produce the definition's value). To make
`get()` return a closure itself instead, wrap it in another closure — only the
outer one gets invoked:

```php
$c->set('greet', function () {
    return function () {
        return 'hello';
    };
});

$c->get('greet'); // the inner Closure
```

### Locking

```php
$c->set('env', 'production');
$c->lock('env');

$c->set('env', 'staging'); // throws ContainerException
$c->delete('env');         // throws ContainerException
$c->isLocked('env');       // true
```

### Binding one id to another (on a Resolver)

`bind()` makes requesting `$id` resolve to `get()`-ing `$target` instead. No
reflection involved — `$target` just needs to be resolvable itself.

```php
$c = new Resolver();
$c->set('file.logger', function () {
    return new FileLogger();
});
$c->bind('logger', 'file.logger');

$c->get('logger'); // the FileLogger instance from 'file.logger'
```

### Autowiring

Use `Injector` to get autowiring: `get()` will automatically instantiate
any class that is neither registered nor bound, resolving its constructor
dependencies recursively.

```php
use SugiPHP\Container\Injector;

$c = new Injector();

// No set() calls needed — resolved automatically
$c->get(MyService::class);        // instantiated with no args
$c->get(MyController::class);     // constructor deps resolved recursively
```

Autowired instances are cached as singletons. Explicit `set()` registrations
always take precedence.

### Resolution precedence

For a given id, `Injector::get()` resolves in this order:

1. An explicit registration — `set()` / `setFactory()`. Always wins; also clears any binding for the same id.
2. A `bind()`-registered binding.
3. Autowiring the id itself as a class name.

Registering an id explicitly always overrides a binding for that same id, and vice versa — `set()` clears the id's binding, `bind()` clears the id's explicit registration.

### Binding interfaces to concrete classes

On an `Injector`, `bind()`'s target doesn't need to be registered — an
instantiable class name is autowired automatically:

```php
$c = new Injector();
$c->bind(LoggerInterface::class, FileLogger::class);

$c->get(LoggerInterface::class);          // returns an autowired FileLogger instance
$c->get(ServiceWithLogger::class);        // LoggerInterface dep resolved automatically
```

Autowiring fails with `NotFoundException` when:
- The class does not exist
- The class is abstract or an interface
- A constructor parameter has a builtin type (scalar, array, etc.) with no default value
- A constructor parameter is variadic
- A constructor parameter has a union or intersection type

```php
// Must still be registered explicitly — scalar arg with no default
$c->set('dsn', 'mysql:host=localhost;dbname=app');
$c->set(PDO::class, fn() => new PDO($c->get('dsn')));
```

## Exceptions

| Class | Thrown when |
|---|---|
| `ContainerException` | Overriding/deleting a locked key, a circular binding, or a circular dependency detected during autowiring |
| `NotFoundException` | `get()` called for a key that does not exist, or autowiring fails to resolve a class |

Both implement the corresponding PSR-11 interfaces.
