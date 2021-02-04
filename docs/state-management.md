# State Management

#### Overview of state management

## Introduction

Dapr offers key/value storage for state management. The PHP SDK allows you to leverage
Dapr's [supported state stores](https://docs.dapr.io/operations/components/setup-state-store/supported-state-stores/).

## Defining State

State is defined through the use of any Plain Old PHP Object (POPO) and an attribute to declare which state store it
belongs to. Each property is stored as a key and are not lazily loaded or saved.

```php
#[\Dapr\State\Attributes\StateStore('statestore', \Dapr\consistency\EventualFirstWrite::class)]
class HelloWorldState {
    public function __construct(public $hello_world) {}
}
```

Then to load the state from the store, we need to use the `StateManager`:

```php
#[\Dapr\State\Attributes\StateStore('statestore', \Dapr\consistency\EventualFirstWrite::class)]
class HelloWorldState {
    public function __construct(public $hello_world = 'hello world') {}
}
$app->get('/', function(\Dapr\State\StateManager $stateManager) {
    $state = new HelloWorldState();
    $stateManager->load_object($state);
});
```

If you have any default values defined in your class, they'll survive loading a non-existent value. Take care when
specifying a type on your state. If a non-nullable type is specified, then an error will be thrown by PHP when you try
to access it with a `null` value.

It is perfectly safe to add behavior to your state classes as well, the state changes will be captured, even in a
transaction.

## Transactions

You can also interact with state using a transaction instead of transactionless. To use state in a transaction, you must
extend `\Dapr\State\TransactionalState` with your state class. You can still use it as normal state too.

```php
#[\Dapr\State\Attributes\StateStore('statestore', \Dapr\consistency\EventualFirstWrite::class)]
class HelloWorldState extends \Dapr\State\TransactionalState {
    public function __construct(public $hello_world = 'hello world') {
        parent::__construct();
    }
}
$app->get('/', function(\DI\FactoryInterface $factory) {
    $state = $factory->make(HelloWorldState::class);
    $state->begin();
    $state->hello_world = 'goodbye';
    $state->commit();
});
```

Once you've made your changes to the state, call `commit`. Once the transaction is committed, the state may no longer be
modified.

### TransactionalState::begin()

```
public function begin(int $parallelism = 10, ?array $metadata = null): void
```

This starts a new transaction. If called on a (un)committed object, starts a new transaction.

Arguments:

- parallelism: Set how many keys to load concurrently.
- metadata: optional, component specific metadata.

### TransactionalState::commit()

```
public function commit(?array $metadata = null): void
```

Arguments:

- metadata: Optional, component specific metadata.

Returns:

Throws a `DaprException` on failure.
