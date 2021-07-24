<?php

use Dapr\Actors\ActorReference;
use Dapr\Actors\ActorState;
use Dapr\Actors\Internal\Caches\CacheInterface;
use Dapr\Actors\Internal\Caches\MemoryCache;
use Dapr\Actors\Internal\KeyResponse;
use Dapr\Client\DaprClient;
use Dapr\exceptions\DaprException;
use DI\Container;
use DI\DependencyException;
use DI\NotFoundException;
use GuzzleHttp\Psr7\Response;
use JetBrains\PhpStorm\Pure;

require_once __DIR__ . '/DaprTests.php';

/**
 * Class ActorStateTest
 */
class ActorStateTest extends DaprTests
{
    /**
     * @throws DaprException
     * @throws DependencyException
     * @throws NotFoundException|ReflectionException
     */
    public function testSaveEmptyTransaction()
    {
        $stack = $this->get_http_client_stack([]);
        $client = $this->get_new_client_with_http($stack->client);
        $state = $this->get_started_state('type', uniqid(), $client);
        $state->save_state();
        $this->assertTrue(true); // no exception thrown
    }

    /**
     * @param string $type
     * @param string $id
     *
     * @return ActorState
     * @throws ReflectionException
     */
    private function get_started_state(string $type, string $id, DaprClient $client): ActorState
    {
        $state = $this->get_state();
        $this->begin_transaction(
            $state,
            new ActorReference($id, $type),
            $client,
            new MemoryCache(new ActorReference($type, $id), 'test')
        );

        return $state;
    }

    private function get_state(): ActorState
    {
        return new class($this->container) extends ActorState {
            #[Pure] public function __construct(Container $container)
            {
                parent::__construct($container, $container);
            }

            public string $state = 'initial';
        };
    }

    /**
     * @param ActorState $state
     * @param string $type
     * @param string $id
     *
     * @throws ReflectionException
     */
    private function begin_transaction(
        ActorState $state,
        ActorReference $reference,
        DaprClient $client,
        CacheInterface $cache
    ) {
        $reflection = new ReflectionClass($state);
        $reflection = $reflection->getParentClass();
        $method = $reflection->getMethod('begin_transaction');
        $method->setAccessible(true);
        $method->invoke($state, $reference, $client, $cache);
    }

    /**
     * @throws DaprException
     * @throws DependencyException
     * @throws NotFoundException
     * @throws ReflectionException
     */
    public function testSave()
    {
        $stack = $this->get_http_client_stack([new Response(204), new Response(204)]);
        $client = $this->get_new_client_with_http($stack->client);
        $state = $this->get_started_state('actor', 'id', $client);
        $state->state = 'ok';

        $state->save_state();
        $request = $this->get_last_request($stack);
        $this->assertRequestUri('/v1.0/actors/actor/id/state', $request);
        $this->assertRequestBody(
            json_encode(
                [
                    [
                        'operation' => 'upsert',
                        'request' => [
                            'key' => 'state',
                            'value' => 'ok',
                        ],
                    ],
                ]
            ),
            $request
        );

        unset($state->state);

        $state->save_state();
        $request = $this->get_last_request($stack);
        $this->assertRequestUri('/v1.0/actors/actor/id/state', $request);
        $this->assertRequestBody(
            json_encode(
                [
                    [
                        'operation' => 'delete',
                        'request' => [
                            'key' => 'state',
                        ],
                    ],
                ]
            ),
            $request
        );
    }

    /**
     * @throws ReflectionException
     */
    public function testSaveInvalidProp()
    {
        $state = $this->get_started_state('type', uniqid(), $this->get_new_client());
        $this->expectException(InvalidArgumentException::class);
        $state->no_prop = true;
    }

    /**
     * @throws ReflectionException
     */
    public function testLoadingValue()
    {
        $stack = $this->get_http_client_stack([new Response(KeyResponse::SUCCESS, body: '"hello world"')]);
        $client = $this->get_new_client_with_http($stack->client);
        $id = uniqid();
        $state = $this->get_started_state('type', $id, $client);

        $this->assertSame('hello world', $state->state);
        $this->assertSame('hello world', $state->state);

        $request = $this->get_last_request($stack);
        $this->assertRequestUri("/v1.0/actors/type/$id/state/state", $request);
        $this->assertRequestMethod('GET', $request);
    }

    /**
     * @throws ReflectionException
     */
    public function testLoadingNoValue()
    {
        $stack = $this->get_http_client_stack([new Response(KeyResponse::KEY_NOT_FOUND)]);
        $client = $this->get_new_client_with_http($stack->client);
        $id = uniqid();
        $state = $this->get_started_state('type', $id, $client);

        $this->assertSame('initial', $state->state);
        $this->assertSame('initial', $state->state);
        $request = $this->get_last_request($stack);
        $this->assertRequestMethod('GET', $request);
        $this->assertRequestUri("/v1.0/actors/type/$id/state/state", $request);
    }

    /**
     * @throws ReflectionException
     */
    public function testLoadingNoActor()
    {
        $stack = $this->get_http_client_stack([new Response(KeyResponse::ACTOR_NOT_FOUND)]);
        $client = $this->get_new_client_with_http($stack->client);
        $id = uniqid();
        $state = $this->get_started_state('nope', $id, $client);

        $this->expectException(DaprException::class);

        $state->state;
    }

    /**
     * @throws ReflectionException
     */
    public function testIsSet()
    {
        $stack = $this->get_http_client_stack(
            [new Response(KeyResponse::SUCCESS, body: '"test"'), new Response(KeyResponse::SUCCESS, body: 'null')]
        );
        $client = $this->get_new_client_with_http($stack->client);
        $id = uniqid();
        $state = $this->get_started_state('type', $id, $client);

        $this->assertTrue(isset($state->state));
        $this->assertTrue(isset($state->state));
        $request = $this->get_last_request($stack);
        $this->assertRequestUri("/v1.0/actors/type/$id/state/state", $request);
        $this->assertRequestMethod('GET', $request);

        $state->roll_back();

        $this->assertFalse(isset($state->state));
        $request = $this->get_last_request($stack);
        $this->assertRequestMethod('GET', $request);
        $this->assertRequestUri("/v1.0/actors/type/$id/state/state", $request);
    }
}
