# Using this library

This library is licensed with the MIT license.

Add the library to your `composer.json`:

> composer require dapr/php-sdk

Some basic documentation is below, more documentation can be found [in the docs](docs);

# Accessing Secrets

You can access secrets easily:

```php
<?php

$app = Dapr\App::create();
$app->get('/a-secret/{name}', function(string $name, \Dapr\SecretManager $secretManager) {
    return $secretManager->retrieve(secret_store: 'my-secret-store', name: $name);
});
$app->get('/a-secret', function(\Dapr\SecretManager $secretManager) {
    return $secretManager->all(secret_store: 'my-secret-store');
});
$app->start();
```

# Accessing State

State is just Plain Old PHP Objects (POPO's) with an attribute:

```php
<?php
#[\Dapr\State\Attributes\StateStore('statestore', \Dapr\consistency\EventualLastWrite::class)]
class MyState {
    /**
     * @var string 
     */
    public string $string_value;
    
    /**
     * @var ComplexType[] 
     */
    #[\Dapr\Deserialization\Attributes\ArrayOf(ComplexType::class)] 
    public array $complex_type;
    
    /**
      * @var Exception 
      */
    public Exception $object_type;
    
    /**
     * @var int 
     */
    public int $counter = 0;

    /**
     * Increment the counter
     * @param int $amount Amount to increment by
     */
    public function increment(int $amount = 1): void {
        $this->counter += $amount;
    }
}

$app = \Dapr\App::create();
$app->post('/my-state/{key}', function (
    string $key, 
    #[\Dapr\Attributes\FromBody] string $body, 
    \Dapr\State\StateManager $stateManager) {
        $stateManager->save_state(store_name: 'store', item: new \Dapr\State\StateItem(key: $key, value: $body));
        $stateManager->save_object(new MyState);
});
$app->get('/my-state/{key}', function(string $key, \Dapr\State\StateManager $stateManager) {
    return $stateManager->load_state(store_name: 'store', key: $key);
});
$app->start();
```

## Transactional State

You can also use transactional state to interact with state objects by extending `TransactionalState` with our state
objects.

```php
#[\Dapr\State\Attributes\StateStore('statestore', \Dapr\consistency\StrongFirstWrite::class)]
class SomeState extends \Dapr\State\TransactionalState {
    public string $value;
    public function ok(): void {
        $this->value = 'ok';
    }
}

$app = Dapr\App::create();
$app->get('/do-work', function(SomeState $state) {
    $state->begin();
    $state->value = 'not-ok';
    $state->ok();
    $state->commit();
    return $state;
});
$app->start();
```

# Actors

Actors are fully implemented and quite powerful. In order to define an actor, you must first define the interface.
You'll likely want to put this in a separate library for easy calling from other services.

```php
<?php
/**
 * Actor that keeps a count
 */
 #[\Dapr\Actors\Attributes\DaprType('ExampleActor')]
interface ICounter {
    /**
     * Increment a counter
     */
    public function increment(int $amount): void;
}
```

Once the interface is defined, you'll need to implement the behavior and register the actor.

```php
<?php

class CountState extends \Dapr\Actors\ActorState {
    public int $count = 0;
}

#[\Dapr\Actors\Attributes\DaprType('Counter')]
class Counter extends \Dapr\Actors\Actor implements ICounter {
    /**
     * Initialize the class
     */
    public function __construct(string $id, private CountState $state) {
        parent::__construct($id);
    }

    /**
     * Increment the count by 1
     */
    public function increment(int $amount): void {
        $this->state->count += $amount;
    }
}

// register the actor with the runtime
$app = \Dapr\App::create(configure: fn(\DI\ContainerBuilder $builder) => $builder->addDefinitions([
    'dapr.actors' => [Counter::class]
]));
$app->start();
```

The state to inject is read from the constructor arguments, the state must derive from `ActorState` to be injected. You
may use as many state classes as you'd like. State is automatically saved for you if you make any changes to it during
the method call using transactional state.

The `Actor` base class gives you access to some helper functions and saves you from writing some boiler-plate. You may
also implement `IActor` and use the `ActorTrait` as well.

## Calling an Actor

In order to call an actor, simply call the `ActorProxy` and get a proxy object:

```php
<?php
use Dapr\Actors\ActorProxy;

 $app = \Dapr\App::create();
 $app->get('/increment/{actorId}[/{amount:\d+}]', function(string $actorId, ActorProxy $actorProxy, int $amount = 1) {
    $counter = $actorProxy->get(ICounter::class, $actorId);
    $counter->increment($amount);
    $counter->create_reminder('increment', new \Dapr\Actors\Reminder('increment', new DateInterval('PT10M'), data: 10 ));
 });
$app->start();
```

## Actor Limitations

1. There's no re-entrance to an actor, this can cause deadlocks if you're not careful.
2. By design, static functions don't work.
3. There's overhead cost in calling "getter" functions.

More detail here: https://docs.dapr.io/developing-applications/building-blocks/actors/actors-overview/

# Pub/Sub

Delivering events around your application is an important aspect of any application. This is supported by Dapr, and
implemented in this SDK.

## Publishing

In order to publish an event, you just instantiate the `Publish` object with the `FactoryInterface`:

```php
<?php
$app = \Dapr\App::create();
$app->get('/publish', function(\DI\FactoryInterface $factory) {
    $publisher = $factory->make(\Dapr\PubSub\Publish::class, ['pubsub' => 'redis-pubsub']);
    $publisher->topic('my-topic')->publish(['message' => 'arrive at dawn']);
});
$app->start();
```

## Subscribing

```php
$app = \Dapr\App::create(configure: fn(\DI\ContainerBuilder $builder) => $builder->addDefinitions([
    'dapr.subscriptions' => [new \Dapr\PubSub\Subscription('redis-pubsub', 'my-topic', '/receive-message')]
]));
$app->post('/receive-message', function(#[\Dapr\Attributes\FromBody] \Dapr\PubSub\CloudEvent $event) {
 // do something
});
$app->start();
```

# Serializing

If you need to register a custom serializer, you can completely override the built-in serializers on a per-type basis or
even the default serializers:

```php
// register a custom type serializer
$app = \Dapr\App::create(configure: fn(\DI\ContainerBuilder $builder) => $builder->addDefinitions([
    'dapr.serializers.custom' => [MyType::class => [MyType::class, 'serialize']],
    'dapr.deserializers.custom' => [MyType::class => [MyType::class, 'deserialize']],
]));

// get the serializer to do manual serializations
$app->get('/', function(\Dapr\Serialization\ISerializer $serializer) {
    return $serializer->as_array('anything here');
});
$app->start();
```

# Project setup

See [Getting Started](docs/getting-started.md)

# Development

Simply run `composer start` on a machine where `dapr init` has already been run. This will start the daprd service on
the current open terminal. Then navigate to [http://localhost:9502/do_tests](http://localhost:9502/do_tests) to let the
integration tests run.

# Tests

Simply run `composer test` to run the unit tests. You can lint using `composer lint`
