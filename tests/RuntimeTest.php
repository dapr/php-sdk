<?php

use Dapr\Actors\ActorRuntime;
use Dapr\PubSub\Subscribe;
use Dapr\Runtime;

class RuntimeTest extends DaprTests
{
    public function testConfig()
    {
        ActorRuntime::register_actor(\Fixtures\ActorClass::class);
        ActorRuntime::set_drain_timeout(new DateInterval('PT10S'));
        ActorRuntime::set_idle_timeout(new DateInterval('PT10M'));
        ActorRuntime::set_scan_interval(new DateInterval('PT5S'));
        ActorRuntime::do_drain_actors(true);
        $expected_config = [
            'entities'                => [
                'TestActor',
            ],
            'drainOngoingCallTimeout' => '0h0m10s0us',
            'actorIdleTimeout'        => '0h10m0s0us',
            'actorScanInterval'       => '0h0m5s0us',
            'drainRebalancedActors'   => true,
        ];
        $this->assertSame($expected_config, $this->deserialize(ActorRuntime::handle_config()['body']));
        $result = Runtime::get_handler_for_route('GET', '/dapr/config')();
        $this->assertSame($expected_config, $this->deserialize($result['body']));
    }

    public function testSubscribe()
    {
        Subscribe::to_topic(
            'pubsub',
            'topic',
            function () {
            }
        );
        $expected_subscribe = [
            [
                'pubsubname' => 'pubsub',
                'topic'      => 'topic',
                'route'      => '/dapr/runtime/sub/topic',
            ],
        ];
        $this->assertSame($expected_subscribe, $this->deserialize(Subscribe::get_subscriptions()['body']));
        $result = Runtime::get_handler_for_route('GET', '/dapr/subscribe')();
        $this->assertSame($expected_subscribe, $this->deserialize($result['body']));
    }

    public function testHealthCheck()
    {
        $called_health = false;
        $health_check  = function () use (&$called_health) {
            $called_health = true;
        };
        Runtime::add_health_check($health_check);
        $result = Runtime::get_handler_for_route('GET', '/healthz')();
        $this->assertSame(['code' => 200], $result);
        $this->assertTrue($called_health);
    }

    public function testFailedHealthCheck()
    {
        $health_check = function () {
            throw new LogicException('test');
        };
        Runtime::add_health_check($health_check);
        $result = Runtime::get_handler_for_route('GET', '/healthz')();
        $this->assertSame(
            [
                'code' => 500,
                'body' => json_encode(
                    [
                        'message'   => 'test',
                        'errorCode' => LogicException::class,
                        'file'      => __FILE__,
                        'line'      => 65,
                        'inner'     => null,
                    ]
                ),
            ],
            $result
        );
    }

    public function testMethods()
    {
        $called_method = false;
        Runtime::register_method(
            method_name: 'test',
            http_method: 'GET',
            callback: function () use (&$called_method) {
            $called_method = true;

            return 'hello world';
        }
        );
        $this->set_body(null);
        $result = Runtime::get_handler_for_route('GET', '/test')();
        $this->assertSame(200, $result['code']);
        $this->assertSame('hello world', $this->deserialize($result['body']));
        $this->assertTrue($called_method);
    }

    public function testUnknownMethod()
    {
        $result = Runtime::get_handler_for_route('GET', '/none')();
        $this->assertSame(404, $result['code']);
    }

    public function testInvoke()
    {
        $this->get_client()->register_post('/invoke/appid/method/method', 200, null, 'hello');
        $result = Runtime::invoke_method('appid', 'method', 'hello');
        $this->assertSame(200, $result->code);
        $this->assertSame(null, $result->data);
    }
}
