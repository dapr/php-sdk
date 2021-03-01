<?php

use Dapr\Actors\ActorState;
use Dapr\Actors\Internal\KeyResponse;
use Dapr\exceptions\DaprException;
use DI\Container;
use DI\DependencyException;
use DI\NotFoundException;
use JetBrains\PhpStorm\Pure;

require_once __DIR__.'/DaprTests.php';

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
        $state = $this->get_started_state('type', uniqid());
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
    private function get_started_state(string $type, string $id): ActorState
    {
        $state = $this->get_state();
        $this->begin_transaction($state, $type, $id);

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
    private function begin_transaction(ActorState $state, string $type, string $id)
    {
        $reflection = new ReflectionClass($state);
        $reflection = $reflection->getParentClass();
        $method     = $reflection->getMethod('begin_transaction');
        $method->setAccessible(true);
        $method->invoke($state, $type, $id);
    }

    /**
     * @throws DaprException
     * @throws DependencyException
     * @throws NotFoundException
     * @throws ReflectionException
     */
    public function testSave()
    {
        $state        = $this->get_started_state('actor', 'id');
        $state->state = 'ok';

        $this->get_client()->register_post(
            "/actors/actor/id/state",
            204,
            '',
            [
                [
                    'operation' => 'upsert',
                    'request'   => [
                        'key'   => 'state',
                        'value' => 'ok',
                    ],
                ],
            ]
        );

        $state->save_state();

        unset($state->state);

        $this->get_client()->register_post(
            "/actors/actor/id/state",
            204,
            '',
            [
                [
                    'operation' => 'delete',
                    'request'   => [
                        'key' => 'state',
                    ],
                ],
            ]
        );

        $state->save_state();
    }

    /**
     * @throws ReflectionException
     */
    public function testSaveInvalidProp()
    {
        $state = $this->get_started_state('type', uniqid());
        $this->expectException(InvalidArgumentException::class);
        $state->no_prop = true;
    }

    /**
     * @throws ReflectionException
     */
    public function testLoadingValue()
    {
        $id    = uniqid();
        $state = $this->get_started_state('type', $id);

        $this->get_client()->register_get(
            "/actors/type/$id/state/state",
            KeyResponse::SUCCESS,
            'hello world'
        );

        $this->assertSame('hello world', $state->state);
        $this->assertSame('hello world', $state->state);
    }

    /**
     * @throws ReflectionException
     */
    public function testLoadingNoValue()
    {
        $id    = uniqid();
        $state = $this->get_started_state('type', $id);

        $this->get_client()->register_get("/actors/type/$id/state/state", KeyResponse::KEY_NOT_FOUND, '');

        $this->assertSame('initial', $state->state);
        $this->assertSame('initial', $state->state);
    }

    /**
     * @throws ReflectionException
     */
    public function testLoadingNoActor()
    {
        $id    = uniqid();
        $state = $this->get_started_state('nope', $id);

        $this->get_client()->register_get("/actors/nope/$id/state/state", KeyResponse::ACTOR_NOT_FOUND, '');

        $this->expectException(DaprException::class);

        $state->state;
    }

    /**
     * @throws ReflectionException
     */
    public function testIsSet()
    {
        $id    = uniqid();
        $state = $this->get_started_state('type', $id);

        $this->get_client()->register_get("/actors/type/$id/state/state", KeyResponse::SUCCESS, 'test');

        $this->assertTrue(isset($state->state));
        $this->assertTrue(isset($state->state));

        $state->roll_back();

        $this->get_client()->register_get("/actors/type/$id/state/state", KeyResponse::SUCCESS, null);

        $this->assertFalse(isset($state->state));
    }
}
