# Using this library

[![PHP Composer](https://github.com/dapr/php-sdk/actions/workflows/php.yml/badge.svg)](https://github.com/dapr/php-sdk/actions/workflows/php.yml)

This library is licensed with the MIT license.

Add the library to your `composer.json`:

> composer require dapr/php-sdk

Some basic documentation is below, more documentation can be
found [in the docs](https://docs.dapr.io/developing-applications/sdks/php/);

# Migrating to 1.2

In preparation for gRPC support in this SDK, there's now a new DaprClient in `\Dapr\Client\DaprClient`. Please update
your code to use the new client.

There shouldn't be many changes to your code to upgrade to 1.2+ from a prior version. Namely, there are some
deprecations:

## Deprecations

The following have been deprecated and will be removed in 1.4+.

### \Dapr\SecretManager has been deprecated

Simply use the new client instead.

### \Dapr\Client has been deprecated

Simply use the new client: `\Dapr\Client\DaprClient`.

### \Dapr\PubSub\Publish has been deprecated

Simply instantiate `\Dapr\PubSub\Topic` directly or use the new client directly.

## Fallbacks and Upgrades

### \Dapr\State\StateManager

This class has been upgraded to use the new client. It shouldn't require any changes to your code, however, the old
behavior can be utilized with `\Dapr\State\StateManagerOld`.

### \Dapr\State\TransactionalState

This class has been upgrade to use the new client. It shouldn't require any changes to your code, however, the old
behavior can be utilized with `\Dapr\State\TransactionalStateOld`.

# Creating a Dapr Client

```php
$client = \Dapr\Client\DaprClient::clientBuilder()->build();
```

## Using the Middleware

The `App` object also implements a PSR-15 compatible middleware which implements the actor routes and subscribe routes
for you.

```php
$app = \Dapr\App::create(configure: [
    // custom configuration
]);
use_middleware($app);
```

# Accessing Secrets

You can access secrets easily:

```php
<?php

// retrieve a single secret
$client->getSecret(storeName: 'kubernetes', key: 'test');

// retrieve all secrets
$client->getBulkSecret(storeName: 'kubernetes');
```

# Accessing State

There are several ways to access state. You can access state directly via the client or abstract access via an object.

## Accessing State Directly

```php
['value' => $value, 'etag' => $etag] = $client->getStateAndEtag(storeName: 'statestore', key: 'key', asType: SomeClass::class, consistency: \Dapr\consistency\EventualLastWrite::instance());
$value = $client->getState(storeName: 'statestore', key: 'key', asType: 'string',consistency: \Dapr\consistency\StrongFirstWrite::instance())
```

## Abstract via Object

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
      * @var SomeObject 
      */
    public SomeObject $object_type;
    
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
objects or commit transactions directly.

### Directly with the client

```php
$transaction = [
    \Dapr\Client\StateTransactionRequest::upsert(key: 'key', value: $client->serializer->as_json($new_value)),
    \Dapr\Client\StateTransactionRequest::delete(key: 'key');
];
$client->executeStateTransaction(storeName: 'statestore', operations: $transaction);
```

### Abstracted via an Object

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

You can also call an actor without an interface:

```php
$client->invokeActorMethod(
    httpMethod: 'GET', 
    actor: new \Dapr\Actors\ActorReference(id: 'id', actor_type: 'Counter'), 
    method: 'increment', 
    parameter: 1
 );
```

## Actor Limitations

1. There's no re-entrance to an actor, by default. You'll need to enable it in the `ActorConfig`
   and [in Dapr](https://docs.dapr.io/operations/support/support-preview-features/).
2. By design, static functions don't work.
3. There's overhead cost in calling "getter" functions.

More detail here: https://docs.dapr.io/developing-applications/building-blocks/actors/actors-overview/

# Pub/Sub

Delivering events around your application is an important aspect of any application. This is supported by Dapr, and
implemented in this SDK.

## Publishing

In order to publish an event, you just instantiate the `Topic` object:

```php
<?php
$app = \Dapr\App::create();
$app->get('/publish', function(\Dapr\Client\DaprClient $client) {
    $topic = new \Dapr\PubSub\Topic(pubsub: 'pubsub', topic: 'topic', client: $client);
    $topic->publish(['message' => 'arrive at dawn']);
});
$app->start();
```

or you can use the new client like:

```php
$client->publishEvent(pubsubName: 'pubsub', topicName: 'topic', data: ['message' => 'arrive at dawn'], contentType: 'application/json');
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

## Using the new client

```php
$client = \Dapr\Client\DaprClient::clientBuilder()
    ->withDeserializationConfig($configuration)
    ->withSerializationConfig($configuration)
    ->build()
```

# Development

## Tests

Simply run `composer test` to run the unit tests. You can lint using `composer lint`.

## Integration tests

You need [`docker-compose`](https://docs.docker.com/compose/gettingstarted/) and [`jq`](https://stedolan.github.io/jq/)

Build and start the environment, then run the integration tests and clean up.

```bash
make
```

You should see output like:

```json
{
  "/test/actors": {
    "status": {
      "test completed successfully: ": "✔"
    },
    "results": {
      "Empty actor should have no data: ": "✔",
      "Actor should have data: ": "✔",
      "Reminder should increment: ": "✔",
      "time formats are delivered ok: ": "✔",
      "Timer should increment: ": "✔",
      "[object] saved array should match: ": "✔",
      "[object] saved string should match: ": "✔",
      "actor can return a simple value: ": "✔"
    }
  },
  "/test/binding": {
    "status": {
      "test completed successfully: ": "✔"
    },
    "results": {
      "we should have received at least one cron: ": "✔"
    }
  },
  "/test/invoke": {
    "status": {
      "test completed successfully: ": "✔"
    },
    "results": {
      "Should receive a 200 response: ": "✔",
      "Static function should receive json string: ": "✔"
    }
  },
  "/test/pubsub": {
    "status": {
      "test completed successfully: ": "✔"
    },
    "results": {
      "simple-test": {
        "sub received message: ": "✔",
        "Received this data": {
          "id": "57b4a889-3cbb-4d5d-a6fe-7574b097c34c",
          "source": "dev",
          "specversion": "1.0",
          "type": "com.dapr.event.sent",
          "datacontenttype": "application/json",
          "data": [
            "test_event"
          ],
          "traceid": "00-222a0988ecf9d53a006a35f961229788-6706c11d588c6da2-00"
        },
        "should be valid cloud event: ": "✔"
      },
      "Testing custom cloud event": {
        "sub received message: ": "✔",
        "Received this raw data": {
          "id": "123",
          "source": "http://example.com",
          "specversion": "1.0",
          "type": "com.example.test",
          "datacontenttype": "application/json",
          "subject": "yolo",
          "time": "2021-02-21T09:32:35+00:00Z",
          "data": [
            "yolo"
          ],
          "traceid": "00-222a0988ecf9d53a006a35f961229788-a0880db7ab9a97d3-00"
        },
        "Expecting this data": {
          "id": "123",
          "source": "http://example.com",
          "specversion": "1.0",
          "type": "com.example.test",
          "datacontenttype": "application/json",
          "subject": "yolo",
          "time": "2021-02-21T09:32:35+00:00Z",
          "data": [
            "yolo"
          ]
        },
        "Received this decoded data": {
          "id": "123",
          "source": "http://example.com",
          "specversion": "1.0",
          "type": "com.example.test",
          "datacontenttype": "application/json",
          "subject": "yolo",
          "time": "2021-02-21T09:32:35+00:00Z",
          "data": [
            "yolo"
          ]
        },
        "Event should be the same event we sent, minus the trace id.: ": "✔"
      },
      "Publishing raw event": {
        "sub received message: ": "✔",
        "Received this data": {
          "id": "01e9fef4-7774-4684-9249-cdce032a2713",
          "source": "dev",
          "specversion": "1.0",
          "type": "com.dapr.event.sent",
          "datacontenttype": "application/json",
          "data": {
            "datacontenttype": "text/xml",
            "data": "<note><to>User1</to><from>user2</from><message>hi</message></note>",
            "specversion": "1.0",
            "type": "xml.message",
            "source": "https://example.com/message",
            "subject": "Test XML Message",
            "id": "id-1234-5678-9101",
            "time": "2020-09-23T06:23:21Z"
          },
          "traceid": "00-222a0988ecf9d53a006a35f961229788-d90a5a50ffa8c104-00"
        }
      },
      "Binary response": {
        "raw": {
          "id": "3a5936e4-9bce-41e5-b344-f7e9bfb186f5",
          "source": "dev",
          "specversion": "1.0",
          "type": "com.dapr.event.sent",
          "datacontenttype": "application/json",
          "data": "raw data",
          "traceid": "00-222a0988ecf9d53a006a35f961229788-d579f3a753fa6b45-00"
        },
        "Data properly decoded: ": "✔"
      }
    }
  },
  "/test/state/concurrency": {
    "status": {
      "test completed successfully: ": "✔"
    },
    "results": {
      "initial value correct: ": "✔",
      "Starting from 0: ": "✔",
      "last-write update succeeds: ": "✔",
      "first-write update fails": "✔"
    }
  },
  "/test/state": {
    "status": {
      "test completed successfully: ": "✔"
    },
    "results": {
      "state is empty: ": "✔",
      "initial state is correct: ": "✔",
      "saved correct state: ": "✔",
      "properly loaded saved state: ": "✔",
      "prefix should work: ": "✔",
      "single key read with default: ": "✔",
      "single key write: ": "✔"
    }
  }
}
```
