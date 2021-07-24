<?php

use Dapr\Actors\ActorConfig;
use Dapr\Actors\ActorRuntime;
use Dapr\Actors\Attributes\DaprType;
use Dapr\Actors\Generators\CachedGenerator;
use Dapr\Actors\Generators\FileGenerator;
use Dapr\Actors\Generators\IGenerateProxy;
use Dapr\Actors\Generators\ProxyFactory;
use Dapr\Actors\IActor;
use Dapr\Actors\Reminder;
use Dapr\Actors\Timer;
use Dapr\exceptions\DaprException;
use Dapr\exceptions\Http\NotFound;
use Dapr\exceptions\SaveStateFailure;
use Dapr\State\FileWriter;
use DI\DependencyException;
use DI\NotFoundException;
use Fixtures\ActorClass;
use Fixtures\ITestActor;
use GuzzleHttp\Psr7\Response;
use JetBrains\PhpStorm\ArrayShape;
use JetBrains\PhpStorm\Pure;

use function DI\autowire;

require_once __DIR__ . '/DaprTests.php';
require_once __DIR__ . '/Fixtures/Actor.php';
require_once __DIR__ . '/Fixtures/BrokenActor.php';
require_once __DIR__ . '/Fixtures/GeneratedProxy.php';

/**
 * Class ActorTest
 */
class ActorTest extends DaprTests
{
    /**
     * @throws ReflectionException
     * @throws DependencyException
     * @throws NotFoundException
     * @throws NotFound
     * @throws SaveStateFailure
     */
    public function testActorInvoke()
    {
        $id = uniqid();
        $this->register_actor('TestActor', ActorClass::class);
        $this->assertState(
            [
                ['upsert' => ['value', 'new value']],
            ],
            $id
        );
        $runtime = $this->container->get(ActorRuntime::class);
        $result = $runtime->resolve_actor(
            'TestActor',
            $id,
            fn($actor) => $runtime->do_method($actor, 'a_function', 'new value')
        );
        $this->assertSame(['new value'], $result);
    }

    /**
     * @param string $name
     * @param string|null $implementation
     *
     * @throws ReflectionException
     */
    private function register_actor(string $name, ?string $implementation = null)
    {
        if ($implementation === null) {
            $reflection = new ReflectionClass($name);
            $attr = $reflection->getAttributes(DaprType::class)[0];
            $implementation = $name;
            $name = $attr->newInstance()->type;
        }
        $config = [$name => $implementation];
        $this->container->set(
            ActorConfig::class,
            new class($config) extends ActorConfig {
                #[Pure] public function __construct(
                    array $actor_name_to_type = [],
                ) {
                    parent::__construct($actor_name_to_type);
                }
            }
        );
    }

    /**
     * @param $transactions
     * @param $id
     *
     * @throws DependencyException
     * @throws NotFoundException
     */
    private function assertState($transactions, $id)
    {
        $return = [];
        foreach ($transactions as $transaction) {
            foreach ($transaction as $operation => $transform) {
                [$key, $value] = $transform;
                $return[] = [
                    'operation' => $operation,
                    'request' => [
                        'key' => $key,
                        'value' => $value,
                    ],
                ];
            }
        }
        $this->get_client()->register_post("/actors/TestActor/$id/state", 201, [], $return);
    }

    /**
     * @throws DependencyException
     * @throws NotFound
     * @throws NotFoundException
     * @throws ReflectionException
     * @throws SaveStateFailure
     */
    public function testActorRuntime()
    {
        $id = uniqid();
        $this->register_actor(ActorClass::class);
        $this->assertState(
            [
                ['upsert' => ['value', 'new value']],
            ],
            $id
        );
        $runtime = $this->container->get(ActorRuntime::class);
        $result = $runtime->resolve_actor(
            'TestActor',
            $id,
            fn($actor) => $runtime->do_method($actor, 'a_function', 'new value')
        );
        $this->assertSame(['new value'], $result);
        $runtime->resolve_actor('TestActor', $id, fn($actor) => $runtime->deactivate_actor($actor, 'TestActor'));
    }

    #[ArrayShape([
        'Dynamic Mode' => "array",
        'Generated Mode' => "array",
        'Cached Mode' => "array",
        'Only Existing' => "array",
    ])] public function getModes(): array
    {
        return [
            'Dynamic Mode' => [ProxyFactory::DYNAMIC],
            'Generated Mode' => [ProxyFactory::GENERATED],
            'Cached Mode' => [ProxyFactory::GENERATED_CACHED],
            'Only Existing' => [ProxyFactory::ONLY_EXISTING],
        ];
    }

    /**
     * @dataProvider getModes
     *
     * @param int $mode
     *
     * @throws DaprException
     * @throws DependencyException
     * @throws NotFoundException
     */
    public function testActorProxy(int $mode)
    {
        $id = uniqid();

        $cache_dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('dapr_test_cache_') . DIRECTORY_SEPARATOR;
        $this->createBuilder([CachedGenerator::class => autowire()->method('set_cache_dir', $cache_dir)]);
        $type = uniqid('TestActor_');

        if ($mode === ProxyFactory::ONLY_EXISTING) {
            // make sure the actor has been loaded
            $this->get_actor_generator(ProxyFactory::GENERATED_CACHED, ITestActor::class, $type, $this->get_new_client())->get_proxy($id);
        }
        $stack = $this->get_http_client_stack(
            [
                new Response(
                    200, body: json_encode(
                           [
                               'dueTime' => '1s',
                               'period' => '10s',
                               'data' => '[0]'
                           ]
                       )
                ),
                new Response(200),
                new Response(200),
                new Response(204),
                new Response(204),
                new Response(200, body: '["true"]'),
                new Response(200, body: '["true"]'),
            ]
        );
        $client = $this->get_new_client_with_http($stack->client);

        $get_last_request = function () use ($stack) {
            static $id = 0;
            return $stack->history[$id++]['request'];
        };

        /**
         * @var ITestActor|IActor $proxy
         */
        $proxy = $this->get_actor_generator($mode, ITestActor::class, $type, $client)->get_proxy($id);
        $this->assertSame($id, $proxy->get_id());

        $reminder = $proxy->get_reminder('reminder');
        $this->assertEquals(
            new Reminder('reminder', new DateInterval('PT1S'), [0], new DateInterval('PT10S')),
            $reminder
        );
        $request = $get_last_request();
        $this->assertRequestBody('', $request);
        $this->assertRequestUri("/v1.0/actors/{$type}/{$id}/reminders/reminder", $request);
        $this->assertRequestMethod('GET', $request);

        $proxy->create_timer(
            new Timer('timer', new DateInterval('PT1S'), new DateInterval('PT1S'), 'callback')
        );
        $request = $get_last_request();
        $this->assertRequestBody(
            json_encode(
                [
                    'dueTime' => '0h0m1s0us',
                    'period' => '0h0m1s0us',
                    'callback' => 'callback',
                    'data' => null
                ]
            ),
            $request
        );
        $this->assertRequestUri("/v1.0/actors/{$type}/{$id}/timers/timer", $request);
        $this->assertRequestMethod('POST', $request);

        $proxy->create_reminder(
            new Reminder(
                'reminder', new DateInterval('PT1S'), data: null, period: new DateInterval('PT1S'), repetitions: 4
            )
        );
        $request = $get_last_request();
        $this->assertRequestBody(
            json_encode(
                [
                    'dueTime' => '0h0m1s0us',
                    'period' => 'R4/PT1S',
                    'data' => 'null'
                ]
            ),
            $request
        );
        $this->assertRequestUri("/v1.0/actors/{$type}/{$id}/reminders/reminder", $request);
        $this->assertRequestMethod('POST', $request);

        $proxy->delete_timer('timer');
        $request = $get_last_request();
        $this->assertRequestUri("/v1.0/actors/$type/$id/timers/timer", $request);
        $this->assertRequestMethod('DELETE', $request);

        $proxy->delete_reminder('reminder');
        $request = $get_last_request();
        $this->assertRequestUri("/v1.0/actors/$type/$id/reminders/reminder", $request);
        $this->assertRequestMethod('DELETE', $request);

        $result = $proxy->a_function(null);
        $this->assertSame(['true'], $result);
        $request = $get_last_request();
        $this->assertRequestUri("/v1.0/actors/$type/$id/method/a_function", $request);
        $this->assertRequestBody('null', $request);
        $this->assertRequestMethod('POST', $request);

        $result = $proxy->a_function('ok');
        $this->assertSame(['true'], $result);
        $request = $get_last_request();
        $this->assertRequestUri("/v1.0/actors/$type/$id/method/a_function", $request);
        $this->assertRequestMethod('POST', $request);
        $this->assertRequestBody('"ok"', $request);
    }

    /**
     * @param int $mode
     * @param string $interface
     * @param string $type
     *
     * @return IGenerateProxy
     * @throws DependencyException
     * @throws NotFoundException
     */
    private function get_actor_generator(
        int $mode,
        string $interface,
        string $type,
        \Dapr\Client\DaprClient $client
    ): IGenerateProxy {
        $factory = new ProxyFactory($mode, $client);

        return $factory->get_generator($interface, $type);
    }

    /**
     * @param $mode
     *
     * @dataProvider getModes
     * @throws DependencyException
     * @throws NotFoundException
     */
    public function testCannotManuallyActivate($mode)
    {
        $id = uniqid();

        /**
         * @var ITestActor $proxy
         */
        $proxy = $this->get_actor_generator($mode, ITestActor::class, 'TestActor', $this->get_new_client())->get_proxy(
            $id
        );
        $this->expectException(LogicException::class);
        $proxy->on_activation();
    }

    /**
     * @param $mode
     *
     * @dataProvider getModes
     * @throws DependencyException
     * @throws NotFoundException
     */
    public function testCannotManuallyDeactivate($mode)
    {
        $id = uniqid();

        /**
         * @var ITestActor $proxy
         */
        $proxy = $this->get_actor_generator($mode, ITestActor::class, 'TestActor', $this->get_new_client())->get_proxy(
            $id
        );
        $this->expectException(LogicException::class);
        $proxy->on_deactivation();
    }

    /**
     * @dataProvider getModes
     *
     * @param $mode
     *
     * @throws DependencyException
     * @throws NotFoundException
     */
    public function testCannotManuallyRemind($mode)
    {
        $id = uniqid();

        /**
         * @var ITestActor|IActor $proxy
         */
        $proxy = $this->get_actor_generator($mode, ITestActor::class, 'TestActor', $this->get_new_client())->get_proxy(
            $id
        );
        $this->expectException(LogicException::class);
        $proxy->remind('', new Reminder('', new DateInterval('PT10S'), ''));
    }

    /**
     * @throws DependencyException
     * @throws NotFoundException
     */
    public function testCachedGeneratorGenerates()
    {
        $cache_dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'dapr-proxy-cache'.DIRECTORY_SEPARATOR;
        $cache = $cache_dir . '/dapr_proxy_GCached';
        if (file_exists($cache)) {
            unlink($cache);
        }
        $this->assertFalse(file_exists($cache), 'cache should be deleted');
        $proxy = $this->get_actor_generator(
            ProxyFactory::GENERATED_CACHED,
            ITestActor::class,
            'GCached',
            $this->get_new_client()
        );
        $proxy->get_proxy('hi');
        $this->assertTrue(file_exists($cache), 'cache should exist');
        unlink($cache);
    }

    /**
     * Take a snapshot of the generated class
     *
     * @throws DependencyException
     * @throws NotFoundException
     * @throws ReflectionException
     */
    public function testGeneratedClassIsCorrect()
    {
        $generated_class = (string)FileGenerator::generate(ITestActor::class, $this->container);
        $take_snapshot = false;
        if ($take_snapshot) {
            FileWriter::write(__DIR__ . '/Fixtures/GeneratedProxy.php', $generated_class);
        }
        $expected_proxy = file_get_contents(__DIR__ . '/Fixtures/GeneratedProxy.php');
        $this->assertSame($expected_proxy, $generated_class);
    }
}
