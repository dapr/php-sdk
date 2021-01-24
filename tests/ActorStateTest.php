<?php

use Dapr\Actors\ActorState;
use Dapr\Actors\Internal\KeyResponse;

require_once __DIR__.'/DaprTests.php';

class ActorStateTest extends DaprTests
{
    public function get_state(string $type, string $id)
    {
        return new class($type, $id) extends ActorState {
            public string $state = 'initial';
        };
    }

    public function testSaveEmptyTransaction()
    {
        $state = $this->get_state('type', uniqid());
        $state->save_state();
        $this->assertTrue(true); // no exception thrown
    }

    public function testSave() {
        $state= $this->get_state('actor', 'id');
        $state->state = 'ok';

        \Dapr\DaprClient::register_post("/actors/actor/id/state", 204, '', [
            [
                'operation' => 'upsert',
                'request' => [
                    'key' => 'state',
                    'value' => 'ok'
                ]
            ]
        ]);

        $state->save_state();

        unset($state->state);

        \Dapr\DaprClient::register_post("/actors/actor/id/state", 204, '', [
            [
                'operation' => 'delete',
                'request' => [
                    'key' => 'state',
                ]
            ]
        ]);

        $state->save_state();
    }

    public function testSaveInvalidProp()
    {
        $state = $this->get_state('type', uniqid());
        $this->expectException(InvalidArgumentException::class);
        $state->no_prop = true;
    }

    public function testLoadingValue()
    {
        $id    = uniqid();
        $state = $this->get_state('type', $id);

        \Dapr\DaprClient::register_get(
            "/actors/type/$id/state/state",
            KeyResponse::SUCCESS,
            'hello world'
        );

        $this->assertSame('hello world', $state->state);
        $this->assertSame('hello world', $state->state);
    }

    public function testLoadingNoValue() {
        $id = uniqid();
        $state = $this->get_state('type', $id);

        \Dapr\DaprClient::register_get("/actors/type/$id/state/state", KeyResponse::KEY_NOT_FOUND, '');

        $this->assertSame('initial', $state->state);
        $this->assertSame('initial', $state->state);
    }

    public function testLoadingNoActor() {
        $id = uniqid();
        $state = $this->get_state('nope', $id);

        \Dapr\DaprClient::register_get("/actors/nope/$id/state/state", KeyResponse::ACTOR_NOT_FOUND, '');

        $this->expectException(\Dapr\exceptions\DaprException::class);

        $state->state;
    }

    public function testIsSet() {
        $id = uniqid();
        $state = $this->get_state('type', $id);

        \Dapr\DaprClient::register_get("/actors/type/$id/state/state", KeyResponse::SUCCESS, 'test');

        $this->assertTrue(isset($state->state));
        $this->assertTrue(isset($state->state));

        $state->roll_back();

        \Dapr\DaprClient::register_get("/actors/type/$id/state/state", KeyResponse::SUCCESS, null);

        $this->assertFalse(isset($state->state));
    }
}
