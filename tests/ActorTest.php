<?php

use Dapr\Actors\ActorConfig;
use Dapr\Actors\ActorRuntime;
use Dapr\Actors\Attributes\DaprType;
use Dapr\Actors\Generators\FileGenerator;
use Dapr\Actors\Generators\IGenerateProxy;
use Dapr\Actors\Generators\ProxyFactory;
use Dapr\Actors\IActor;
use Dapr\Actors\Reminder;
use Dapr\Actors\Timer;
use Dapr\exceptions\DaprException;
use Dapr\exceptions\Http\NotFound;
use Dapr\exceptions\SaveStateFailure;
use DI\DependencyException;
use DI\NotFoundException;
use Fixtures\ActorClass;
use Fixtures\ITestActor;
use JetBrains\PhpStorm\ArrayShape;
use JetBrains\PhpStorm\Pure;

require_once __DIR__.'/DaprTests.php';
require_once __DIR__.'/Fixtures/Actor.php';
require_once __DIR__.'/Fixtures/BrokenActor.php';
require_once __DIR__.'/Fixtures/GeneratedProxy.php';

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
        $result  = $runtime->resolve_actor(
            'TestActor',
            $id,
            fn($actor) => $runtime->do_method($actor, 'a_function', 'new value')
        );
        $this->assertTrue($result);
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
            $reflection     = new ReflectionClass($name);
            $attr           = $reflection->getAttributes(DaprType::class)[0];
            $implementation = $name;
            $name           = $attr->newInstance()->type;
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
        $result  = $runtime->resolve_actor(
            'TestActor',
            $id,
            fn($actor) => $runtime->do_method($actor, 'a_function', 'new value')
        );
        $this->assertTrue($result);
    }

    #[ArrayShape([
        'Dynamic Mode'   => "array",
        'Generated Mode' => "array",
        'Cached Mode'    => "array",
        'Only Existing'  => "array",
    ])] public function getModes(): array
    {
        return [
            'Dynamic Mode'   => [ProxyFactory::DYNAMIC],
            'Generated Mode' => [ProxyFactory::GENERATED],
            'Cached Mode'    => [ProxyFactory::GENERATED_CACHED],
            'Only Existing'  => [ProxyFactory::ONLY_EXISTING],
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

        /**
         * @var ITestActor|IActor $proxy
         */
        $proxy = $this->get_actor_generator($mode, ITestActor::class, 'TestActor')->get_proxy($id);

        $this->assertSame($id, $proxy->get_id());
        $this->get_client()->register_get(
            "/actors/TestActor/$id/reminders/reminder",
            200,
            [
                "dueTime" => '1s',
                'period'  => '10s',
                'data'    => "[0]",
            ]
        );
        $reminder = $proxy->get_reminder('reminder', $this->get_client());
        $this->assertSame(1, $reminder->due_time->s);
        $this->assertSame(10, $reminder->period->s);
        $this->assertSame([0], $reminder->data);

        $this->get_client()->register_post(
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
            new Timer('timer', new DateInterval('PT1S'), new DateInterval('PT1S'), 'callback'),
            $this->get_client()
        );

        $this->get_client()->register_post(
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
            new Reminder(
                'reminder', new DateInterval('PT1S'), data: null, period: new DateInterval('PT1S')
            ),
            $this->get_client()
        );

        $this->get_client()->register_delete("/actors/TestActor/$id/timers/timer", 204);
        $proxy->delete_timer('timer', $this->get_client());

        $this->get_client()->register_delete("/actors/TestActor/$id/reminders/reminder", 204);
        $proxy->delete_reminder('reminder', $this->get_client());

        $this->get_client()->register_post(
            path: "/actors/TestActor/$id/method/a_function",
            code: 200,
            response_data: ['true'],
            expected_request: null
        );
        $proxy->a_function(null);

        $this->get_client()->register_post(
            path: "/actors/TestActor/$id/method/a_function",
            code: 200,
            response_data: ['true'],
            expected_request: "ok"
        );
        $proxy->a_function('ok');
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
    private function get_actor_generator(int $mode, string $interface, string $type): IGenerateProxy
    {
        $factory = new ProxyFactory($this->container, $mode);

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
        $proxy = $this->get_actor_generator($mode, ITestActor::class, 'TestActor')->get_proxy($id);
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
        $proxy = $this->get_actor_generator($mode, ITestActor::class, 'TestActor')->get_proxy($id);
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
        $proxy = $this->get_actor_generator($mode, ITestActor::class, 'TestActor')->get_proxy($id);
        $this->expectException(LogicException::class);
        $proxy->remind('', new Reminder('', new DateInterval('PT10S'), ''));
    }

    /**
     * @throws DependencyException
     * @throws NotFoundException
     */
    public function testCachedGeneratorGenerates()
    {
        $cache = sys_get_temp_dir().'/dapr-proxy-cache/dapr_proxy_GCached';
        if (file_exists($cache)) {
            unlink($cache);
        }
        $this->assertFalse(file_exists($cache));
        $proxy = $this->get_actor_generator(ProxyFactory::GENERATED_CACHED, ITestActor::class, 'GCached');
        $proxy->get_proxy('hi');
        $this->assertTrue(file_exists($cache));
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
        $take_snapshot   = false;
        if ($take_snapshot) {
            file_put_contents(__DIR__.'/Fixtures/GeneratedProxy.php', $generated_class);
        }
        $expected_proxy = file_get_contents(__DIR__.'/Fixtures/GeneratedProxy.php');
        $this->assertSame($expected_proxy, $generated_class);
    }
}
