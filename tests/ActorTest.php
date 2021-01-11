<?php

use Dapr\Actors\ActorRuntime;
use Fixtures\ActorClass;

require_once __DIR__.'/DaprTests.php';
require_once __DIR__.'/Fixtures/Actor.php';
require_once __DIR__.'/Fixtures/BrokenActor.php';

class ActorTest extends DaprTests
{
    public function testActorInvoke()
    {
        $id = uniqid();
        ActorRuntime::register_actor( ActorClass::class);
        $this->assertState(
            [
                ['upsert' => ['value', 'new value']],
            ],
            $id
        );
        $this->set_body(['new value']);
        $result = ActorRuntime::handle_invoke(
            ActorRuntime::extract_parts_from_request('PUT', "/actors/TestActor/$id/method/a_function")
        );
        $this->assertSame(200, $result['code']);
        $this->assertTrue(\Dapr\Deserializer::maybe_deserialize(json_decode($result['body'])));
    }

    private function inject_state($state_array, $id)
    {
        $state = [];
        foreach ($state_array as $key => $value) {
            if (is_numeric($key)) {
                $state[] = ['key' => $value];
            } else {
                $state[] = ['key' => $value, 'data' => $value, 'etag' => 1];
            }
        }
        \Dapr\DaprClient::register_post(
            '/state/store/bulk',
            code: 200,
            response_data: $state,
            expected_request: [
                'keys'        => ["TestActor||$id||value"],
                'parallelism' => 10,
            ]
        );
    }

    private function assertState($transactions, $id)
    {
        $return = [];
        foreach ($transactions as $transaction) {
            foreach ($transaction as $operation => $transform) {
                [$key, $value] = $transform;
                $return[] = [
                    'operation' => $operation,
                    'request'   => [
                        'key'   => $key,
                        'value' => $value,
                    ],
                ];
            }
        }
        \Dapr\DaprClient::register_post("/actors/TestActor/$id/state", 201, [], $return);
    }

    public function testActorRuntime()
    {
        $id = uniqid();
        ActorRuntime::register_actor( ActorClass::class);
        $this->assertState(
            [
                ['upsert' => ['value', 'new value']],
            ],
            $id
        );
        $this->set_body(['new value']);
        $result = \Dapr\Runtime::get_handler_for_route('PUT', "/actors/TestActor/$id/method/a_function")();
        $this->assertSame(200, $result['code']);
        $this->assertTrue(\Dapr\Deserializer::maybe_deserialize(json_decode($result['body'])));
    }

    public function testActorProxy()
    {
        $id = uniqid();

        /**
         * @var \Fixtures\ITestActor $proxy
         */
        $proxy = \Dapr\Actors\ActorProxy::get(\Fixtures\ITestActor::class, $id);

        $this->assertSame($id, $proxy->get_id());
        \Dapr\DaprClient::register_get(
            "/actors/TestActor/$id/reminders/reminder",
            200,
            [
                "dueTime" => '1s',
                'period'  => '10s',
                'data'    => "[0]",
            ]
        );
        $reminder = $proxy->get_reminder('reminder');
        $this->assertSame(1, $reminder->due_time->s);
        $this->assertSame(10, $reminder->period->s);
        $this->assertSame([0], $reminder->data);

        \Dapr\DaprClient::register_post(
            "/actors/TestActor/$id/timers/timer",
            200,
            [],
            [
                'dueTime'  => '0h0m1s0us',
                'period'   => '0h0m1s0us',
                'callback' => 'callback',
                'data'     => null,
            ]
        );
        $proxy->create_timer(
            new \Dapr\Actors\Timer('timer', new DateInterval('PT1S'), new DateInterval('PT1S'), 'callback')
        );

        \Dapr\DaprClient::register_post(
            "/actors/TestActor/$id/reminders/reminder",
            200,
            [],
            [
                'dueTime' => '0h0m1s0us',
                'period'  => '0h0m1s0us',
                'data'    => 'null',
            ]
        );
        $proxy->create_reminder(
            new \Dapr\Actors\Reminder(
                'reminder', new DateInterval('PT1S'), period: new DateInterval('PT1S'), data: null
            )
        );

        \Dapr\DaprClient::register_delete("/actors/TestActor/$id/timers/timer", 204);
        $proxy->delete_timer('timer');

        \Dapr\DaprClient::register_delete("/actors/TestActor/$id/reminders/reminder", 204);
        $proxy->delete_reminder('reminder');

        \Dapr\DaprClient::register_post(
            path: "/actors/TestActor/$id/method/a_function",
            code: 200,
            response_data: true,
            expected_request: [
                'value' => null,
            ]
        );
        $proxy->a_function(null);
    }

    public function testCannotManuallyActivate()
    {
        $id = uniqid();

        /**
         * @var \Fixtures\ITestActor $proxy
         */
        $proxy = \Dapr\Actors\ActorProxy::get(\Fixtures\ITestActor::class, $id);
        $this->expectException(LogicException::class);
        $proxy->on_activation();
    }

    public function testCannotManuallyDeactivate()
    {
        $id = uniqid();

        /**
         * @var \Fixtures\ITestActor $proxy
         */
        $proxy = \Dapr\Actors\ActorProxy::get(\Fixtures\ITestActor::class, $id);
        $this->expectException(LogicException::class);
        $proxy->on_deactivation();
    }

    public function testCannotManuallyRemind()
    {
        $id = uniqid();

        /**
         * @var \Fixtures\ITestActor $proxy
         */
        $proxy = \Dapr\Actors\ActorProxy::get(\Fixtures\ITestActor::class, $id);
        $this->expectException(LogicException::class);
        $proxy->remind('', '');
    }

    public function testNoDaprType() {
        $id = uniqid();
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('IBrokenActor must have a DaprType attribute');
        $proxy = \Dapr\Actors\ActorProxy::get(IBrokenActor::class, $id);
    }
}
