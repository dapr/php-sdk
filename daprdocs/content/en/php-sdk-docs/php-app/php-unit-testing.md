---
type: docs
title: "Unit Testing"
linkTitle: "Unit Testing"
weight: 1000
description: Unit Testing
no_list: true
---

Unit, functional, and integration tests are first-class citizens with the PHP SDK. Using the DI container, mocks, stubs,
and the provided `\Dapr\Mocks\TestClient` allows you to have very fine-grained tests.

## Testing Actors

With actors, there are two things we're interested in while the actor is under test:

1. The returned result based on an initial state
2. The resulting state based on the initial state

{{< tabs "integration test with TestClient" "unit test" >}}

{{% codetab %}}

Here's an example test a very simple actor that updates its state and returns a specific value:

```php
<?php

// TestState.php

class TestState extends \Dapr\Actors\ActorState
{
    public int $number;
}

// TestActor.php

#[\Dapr\Actors\Attributes\DaprType('TestActor')]
class TestActor extends \Dapr\Actors\Actor
{
    public function __construct(string $id, private TestState $state)
    {
        parent::__construct($id);
    }

    public function oddIncrement(): bool
    {
        if ($this->state->number % 2 === 0) {
            return false;
        }
        $this->state->number += 1;

        return true;
    }
}

// TheTest.php

class TheTest extends \PHPUnit\Framework\TestCase
{
    private \DI\Container $container;

    public function setUp(): void
    {
        parent::setUp();
        // create a default app and extract the DI container from it
        $app = \Dapr\App::create(
            configure: fn(\DI\ContainerBuilder $builder) => $builder->addDefinitions(
            ['dapr.actors' => [TestActor::class]],
            [\Dapr\DaprClient::class => \DI\autowire(\Dapr\Mocks\TestClient::class)]
        ));
        $app->run(fn(\DI\Container $container) => $this->container = $container);
    }

    public function testIncrementsWhenOdd()
    {
        $id      = uniqid();
        $runtime = $this->container->get(\Dapr\Actors\ActorRuntime::class);
        $client  = $this->getClient();

        // return the current state from http://localhost:1313/reference/api/actors_api/
        $client->register_get("/actors/TestActor/$id/state/number", code: 200, data: 3);

        // ensure it increments from http://localhost:1313/reference/api/actors_api/
        $client->register_post(
            "/actors/TestActor/$id/state",
            code: 204,
            response_data: null,
            expected_request: [
                [
                    'operation' => 'upsert',
                    'request'   => [
                        'key'   => 'number',
                        'value' => 4,
                    ],
                ],
            ]
        );

        $result = $runtime->resolve_actor(
            'TestActor',
            $id,
            fn($actor) => $runtime->do_method($actor, 'oddIncrement', null)
        );
        $this->assertTrue($result);
    }

    private function getClient(): \Dapr\Mocks\TestClient
    {
        return $this->container->get(\Dapr\DaprClient::class);
    }
}
```

{{% /codetab %}}
{{% codetab %}}

```php
<?php

// TestState.php

class TestState extends \Dapr\Actors\ActorState
{
    public int $number;
}

// TestActor.php

#[\Dapr\Actors\Attributes\DaprType('TestActor')]
class TestActor extends \Dapr\Actors\Actor
{
    public function __construct(string $id, private TestState $state)
    {
        parent::__construct($id);
    }

    public function oddIncrement(): bool
    {
        if ($this->state->number % 2 === 0) {
            return false;
        }
        $this->state->number += 1;

        return true;
    }
}

// TheTest.php

class TheTest extends \PHPUnit\Framework\TestCase
{
    public function testNotIncrementsWhenEven() {
        $container = new \DI\Container();
        $state = new TestState($container, $container);
        $state->number = 4;
        $id = uniqid();
        $actor = new TestActor($id, $state);
        $this->assertFalse($actor->oddIncrement());
        $this->assertSame(4, $state->number);
    }
}
```

{{% /codetab %}}

{{< /tabs >}}

