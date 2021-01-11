# Unit Testing

#### A guide to unit testing with the PHP-SDK

## Actors

Unit testing actors is made straightforward by being able to easily mock state and the actor being a regular class. The
best way to mock state is to use anonymous classes to set the initial state, create the actor, invoke the method, then
assert actor/state properties.

Here is an example unit test:

```php
class CounterTest extends \PHPUnit\Framework\TestCase {
    public function testIncrements() {
        $state = new class extends CounterState {
            use \Dapr\Mocks\TestActorState;
            
            public int $count = 10;
        };
        $actor_id = uniqid();
        $actor = new class($actor_id, $state) extends Counter {
            use \Dapr\Mocks\TestActor;
        };
        $actor->increment(1);
        $this->assertSame(11, $state->count);
    }
}
```

The mock traits override the API calls and expose some helper functions, prefixed with `helper_` so you know they don't
exist in production.

### TestActorState::helper_get_transaction()

Returns the transaction that would be sent to the API.

### TestActor::helper_get_timer()

Returns a timer or null if there isn't a timer.

## Transactional State

Testing transactional state is very similar to testing actor state. Instead of using the `TestActorState` trait, you'll
use the `TestTransactionalState` trait. Here's an example unit test:

```php
class PaymentTest extends \PHPUnit\Framework\TestCase {
    public function testReceivePayment() {
        $state = new class extends PaymentState {
            use \Dapr\Mocks\TestTransactionalState;
        };
        handle_payment($state);
        $state->commit();
        $this->assertSame([
            [
                ['operation' => 'delete', 'request' => ['key' => 'outstanding']]
            ]
        ], $state->helper_get_transactions());
    }
}
```

The `TestTransactionalState` trait exposes a `helper_get_transactions` function that returns committed transactions.
