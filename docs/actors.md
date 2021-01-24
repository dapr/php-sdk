# Actors

## Introduction

If you're new to the actor pattern, the best place to learn about the actor pattern is in
the [Dapr docs.](https://docs.dapr.io/developing-applications/building-blocks/actors/actors-background/)

In the PHP SDK, there are two sides to an actor, the Client, and the Actor (aka, the Runtime). As a client of an actor,
you'll interact with a remote actor via the `ActorProxy` class. This class generates a proxy class on-the-fly using one
of several configured strategies.

When writing an actor, state can be managed for you. You can hook into the actor lifecycle, and define reminders and
timers. This gives you considerable power for handling all types of problems that the actor pattern is suited for.

## The Actor Proxy

Whenever you want to communicate with an actor, you'll need to get a proxy object to do so. The proxy is responsible for
serializing your request, deserializing the response, and returning it to you, all while obeying the contract defined by
the defined interface.

In order to create the proxy, you'll first need an interface to define how and what we send and receive from an actor.
For example, if we want to communicate with a counting actor that solely keeps track of counts, we might define the
interface as follows:

```php
#[\Dapr\Actors\Attributes\DaprType('Counter')]
interface ICount {
    function increment(int $amount = 1): void;
    function get_count(): int;
}
```

It's a good idea to put this interface in a shared library that the actor and clients can both access. The `DaprType`
attribute tells the DaprClient the name of the actor to send to. It should match the implementation's `DaprType`.

Then to get the actor proxy for the actor with id `1`:

```php
$id = 1;
$proxy = \Dapr\Actors\ActorProxy::get(ICount::class, $id);
$proxy->increment(10);
```

`$proxy` now is an instance that satisfies the `ICount` interface.

### Proxy Modes

There are three different ways that a proxy instance can be created:

```php
\Dapr\Actors\ProxyModes::DYNAMIC;
\Dapr\Actors\ProxyModes::GENERATED;
\Dapr\Actors\ProxyModes::GENERATED_CACHED;
```

#### Generated

This is the default mode. In this mode, a class is generated and `eval`'d on every request. It's mostly for development
and shouldn't be on in production unless you need to ensure the proxy is an `instanceof` your interface and/or you wish
to use the following optimizations.

##### Optimizing

You can generate the string manually by calling:

```php
\Dapr\Actors\ActorProxy::generate_proxy_class(ICounter::class);
```

By calling this in a custom script, saving the string to a file, and autoloading the generated files, you can
significantly increase actor throughput.

#### Generated_Cached

This is the same as `ProxyModes::GENERATED` except the class is stored in a tmp file so it doesn't need to be
regenerated on every request. It doesn't know when to update the cached class, so using it in development is discouraged
but is offered for when manually generating the files isn't possible.

#### Dynamic

In this mode, the proxy satisfies the interface contract, however, it does not actually implement the interface itself
(meaning `instanceof` will be `false`). This mode takes advantage of a few quirks in PHP to work and exists for cases
where code cannot be `eval`'d or generated.

## Writing Actors

To create an actor, we need to implement the interface we defined earlier and also add the `DaprType` attribute. We can
also use the `Actor` trait to help us implement most of the boilerplate required. The reason a trait is used vs. a base
class was because in PHP, you may only inherit from a single class. This allows you to define a family of actors as you
see fit.

Here's our counter actor:

```php
#[\Dapr\Actors\Attributes\DaprType('Count')]
class Counter extends \Dapr\Actors\Actor implements ICount {
    function __construct(protected string $id, private CountState $state) {
        parent::__construct($id);
    }
    
    function increment(int $amount = 1): void {
        $this->state->count += $amount;
    }
    
    function get_count(): int {
        return $this->state->count;
    }
}
```

### Actor Lifecycle

The most important bit is the constructor. It takes at least one argument, the id of the actor. Any additional arguments
need to have the type specified, which must extend `ActorType`.

An actor is instantiated via the constructor on every request. You can use it to calculate ephemeral state or handle any
kind of request-specific startup you require, such as setting up other clients or connections.

After the actor is instantiated, the `on_activation()` method may be called. The `on_activation()` method is called any
time the actor
"wakes up" or when it is created for the first time. It is not called on every request.

Next, the actor method is called. This may be from a timer, reminder, or from a client. You may perform any work that
needs to be done and/or throw an exception.

Finally, the result of the work is returned to the caller. After some time (depending on how you've configured the
service), the actor will be deactivated and `on_deactivation()` will be called. This may not be called if the host dies,
daprd crashes, or some other error occurs which prevents it from being called successfully.

### Actor Methods

Most of the boilerplate for `IActor` is handled by the `Actor` trait. Here are those methods and what they do:

#### create_reminder()

```
public function create_reminder(
        Reminder $reminder
    ): bool
```

This method allows you to create a reminder. Reminders are fired whether the actor is inactive or active and will keep
an actor active. These allow you to create long-lived behaviors. These will call the `remind()` method.

#### get_reminder()

```
public function get_reminder(
        string $name
    ): ?Reminder 
```

This method allows you to get an already created reminder by its name. It returns `null` if no reminder exists.

#### delete_reminder()

```
public function delete_reminder(string $name): bool
```

This method allows you to delete a reminder.

#### create_timer()

```
public function create_timer(
        Timer $timer,
    ): bool
```

Timers are not persisted. They will not wake an actor up, and only fire while an actor is active. These call any given
method on the class as a callback.

#### delete_timer()

```
public function delete_timer(string $name): bool
```

Delete a timer with a given name.

### IActor Methods

Though the `Actor` trait covers most methods, there are still some you must implement.

#### get_id()

```
function get_id(): mixed;
```

You must return the id passed to the constructor. If you do not, the behavior is undefined.

#### remind()

```
function remind(string $name, $data): void;
```

When a reminder is created, it calls this method. The name will be the name of the reminder and the data will be the
data you passed to the reminder.

#### on_activation()

```
function on_activation(): void;
```

This method is called when the actor is activated.

#### on_deactivation()

```
function on_deactivation(): void;
```

This method is called when the actor is deactivated.

## Registering an actor

Dapr expects to know what actors a service may host at startup. You can do this using the `ActorRuntime`:

```php
\Dapr\Actors\ActorRuntime::register_actor(Counter::class);
```

## Actor State

Actor state is a "Plain Old PHP Object" (POPO) that extends `ActorState`. The `ActorState` base class provides a couple
of useful methods. Here's an example implementation:

```php
class CountState extends \Dapr\Actors\ActorState {
    public int $count = 0;
}
```

### ActorState::save_state

```
public function save_state(): void
```

This method commits the current transaction and restarts the transaction. This is automatically called for you when your
method completes, but there may be cases where you want to call it manually.

### ActorState::roll_back

```
public function roll_back(): void
```

This method rolls back the current transaction. This may be handy in certain situations where you want to reset the
state back to how it was when the actor method was first called.
