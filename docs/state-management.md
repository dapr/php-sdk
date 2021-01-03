# State Management

#### Overview of state management

## Introduction

Dapr offers key/value storage for state management. The PHP SDK allows you to leverage
Dapr's [supported state stores](https://docs.dapr.io/operations/components/setup-state-store/supported-state-stores/).

## Defining State

State is defined through the use of a class that inherits the `Dapr\State\State` class. For example, to declare a state
that uses a `helloWorld` key:

```php
class HelloWorldState extends \Dapr\State\State {
    public $helloWorld;
}
```

Then to load the state from the store, we need to instantiate the class and call `load()`:

```php
class HelloWorldState extends \Dapr\State\State {
    public $helloWorld;
}
$state = new HelloWorldState('statestore', new \Dapr\consistency\StrongFirstWrite());
$state->load();
```

If you have any default values defined in your class, they'll survive loading a `null`/non-existent value. Take care
when specifying a type on your state. If a non-nullable type is specified, then an error will be thrown by PHP when you
try to access it with a `null` value.

It is perfectly safe to add behavior to your state classes as well, the state changes will be captured, even in a
transaction.

### State Constructor

```
public function __construct(
        private string $store_name,
        ?Consistency $consistency = null,
        private string $key_prepend = ''
    )
```

Arguments:

- store_name: The store component's name
- consistency: One of `EventualFirstWrite`, `EventualLastWrite`, `StrongFirstWrite`, or `StrongLastWrite`.
  See [the docs](https://docs.dapr.io/reference/api/state_api/#concurrency) for more information.
- key_prepend: A prefix that is used when loading and saving keys, but is removed before storing to the class.

### Loading

```
public function load(?array $metadata = null): void
```

Loads the data from the store, can optionally supply `metadata` which is passed directly to the component.

### Saving

```
public function save_state(): void
```

Saves the data to the store, using the specified concurrency/consistency option passed when instantiated.

Throws `DaprException` if it fails.

## Transactions

You can also interact with state using a transaction instead of transactionless. To begin a transaction,
use `Dapr\State\TransactionalState`:

```php
/**
* @var HelloWorldState $state
 */
$state = \Dapr\State\TransactionalState::begin(HelloWorldState::class, 'statestore', new \Dapr\consistency\StrongFirstWrite());
```

Once you've made your changes to the state, call `commit`

```php
\Dapr\State\TransactionalState::commit($state);
```

Once the transaction is committed, the state may no longer be modified.

### TransactionalState::begin()

```
public static function begin(
        string $type,
        ?string $store_name = null,
        ?Consistency $consistency = null
    ): TransactionalState
```

This wraps the state in a proxy object that keeps track of updates/deletes

Arguments:

- type: The state type to wrap, it will instantiate the type with a singular argument: the state store.
- store_name: The name of the store component
- consistency: The type of consistency

Returns:

A `TransactionalState` object that proxies the actual state object.

### TransactionalState::commit()

```
public static function commit(State|TransactionalState $state, array $metadata = []): bool
```

Arguments:

- state: The TransactionalState object returned from `begin()`.
- metadata: Any metadata to be passed to the component.

Returns:

True if the commit is successful. Throws a `DaprException` on failure.
