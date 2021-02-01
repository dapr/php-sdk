<?php

require_once __DIR__.'/../vendor/autoload.php';
require_once __DIR__.'/../tests/Fixtures/SimpleActor.php';

define('STORE', 'statestore');

use Dapr\Actors\ActorProxy;
use Dapr\Actors\ActorRuntime;
use Dapr\Actors\Generators\ProxyFactory;
use Dapr\Actors\IActor;
use Dapr\Actors\Reminder;
use Dapr\Actors\Timer;
use Dapr\Attributes\FromBody;
use Dapr\Binding;
use Dapr\consistency\StrongFirstWrite;
use Dapr\consistency\StrongLastWrite;
use Dapr\exceptions\SaveStateFailure;
use Dapr\exceptions\StateAlreadyCommitted;
use Dapr\PubSub\CloudEvent;
use Dapr\PubSub\Publish;
use Dapr\Runtime;
use Dapr\State\Attributes\StateStore;
use Dapr\State\StateManager;
use Dapr\State\TransactionalState;
use Psr\Http\Message\ResponseInterface;

use function DI\autowire;

#[StateStore(STORE, StrongFirstWrite::class)]
class TState extends TransactionalState
{
    public int $counter = 0;

    public function increment(int $amount = 1): void
    {
        $this->counter += $amount;
    }
}

$app = \Dapr\App::create(
    configure: fn(\DI\ContainerBuilder $builder) => $builder->addDefinitions(
    [
        \Dapr\Actors\ActorConfig::class   => new class extends \Dapr\Actors\ActorConfig {
            public function __construct()
            {
                $this->actor_name_to_type = ['SimpleActor' => SimpleActor::class];
            }
        },
        \Dapr\PubSub\Subscriptions::class => new class extends \Dapr\PubSub\Subscriptions {
            public function __construct()
            {
                $this->subscriptions = [new \Dapr\PubSub\Subscription('pubsub', 'test', '/testsub')];
            }
        },
        ProxyFactory::class               => autowire(ProxyFactory::class)->constructorParameter(
            'mode',
            ProxyFactory::GENERATED
        ),
    ]
)
);

$app->get(
    '/test/actors',
    function (ActorProxy $actorProxy) {
        $id = uniqid(prefix: 'actor_');

        /**
         * @var ISimpleActor|IActor $actor
         */
        $actor = $actorProxy->get(ISimpleActor::class, $id);
        $body  = [];

        $body = assert_equals($body, 0, $actor->get_count(), 'Empty actor should have no data');
        $actor->increment();
        $body = assert_equals($body, 1, $actor->get_count(), 'Actor should have data');

        $reminder = new Reminder(
            name: 'increment',
            due_time: new DateInterval('PT1S'),
            data: ['amount' => 2],
            period: new DateInterval('PT10M')
        );
        $actor->create_reminder(reminder: $reminder);
        sleep(2);
        $body          = assert_equals($body, 3, $actor->get_count(), 'Reminder should increment');
        $read_reminder = $actor->get_reminder('increment');
        $body          = assert_equals(
            $body,
            $reminder->due_time->format(\Dapr\Formats::FROM_INTERVAL),
            $read_reminder->due_time->format(\Dapr\Formats::FROM_INTERVAL),
            'time formats are delivered ok'
        );

        $timer = new Timer(
            name: 'increment',
            due_time: new DateInterval('PT1S'),
            period: new DateInterval('P2D'),
            callback: 'increment',
            data: 2
        );
        $actor->create_timer(timer: $timer);
        sleep(2);
        $body = assert_equals($body, 5, $actor->get_count(), 'Timer should increment');

        $actor->delete_timer('increment');
        $actor->delete_reminder('increment');
        $actor->delete_reminder('nope');
        $actor->delete_timer('nope');

        $object      = new SimpleObject();
        $object->bar = ['hello', 'world'];
        $object->foo = "hello world";
        $actor->set_object($object);
        $saved_object = $actor->get_object();
        $body         = assert_equals($body, $object->bar, $saved_object->bar, "[object] saved array should match");
        $body         = assert_equals($body, $object->foo, $saved_object->foo, "[object] saved string should match");

        $body = assert_equals($body, true, $actor->a_function(), 'actor can return a simple value');

        return $body;
    }
);

$app->get(
    '/test/state',
    function (StateManager $stateManager) {
        $body  = [];
        $state = new SimpleState();
        $stateManager->save_object($state);
        $body = assert_equals($body, null, $state->data, 'state is empty');
        $body = assert_equals($body, 0, $state->counter, 'initial state is correct');

        $state->data = 'data';
        $stateManager->save_object($state);
        $body = assert_equals($body, 'data', $state->data, 'saved correct state');

        $state = new SimpleState();
        $stateManager->load_object($state);
        $body = assert_equals($body, 'data', $state->data, 'properly loaded saved state');

        $prefix = uniqid();
        $state  = new SimpleState();
        $stateManager->load_object($state, $prefix);
        $body = assert_not_equals($body, 'data', $state->data, 'prefix should work');

        $random_key = uniqid();
        $state      = $stateManager->load_state('statestore', $random_key, 'hello');
        $body       = assert_equals($body, 'hello', $state->value, 'single key read with default');

        $stateManager->save_state('statestore', $state);
        $state2 = $stateManager->load_state('statestore', $random_key, 'world');
        $body   = assert_equals($body, 'hello', $state2->value, 'single key write');

        return $body;
    }
);

$app->get(
    '/test/state/concurrency',
    function (StateManager $stateManager) {
        $last  = new #[StateStore(STORE, StrongLastWrite::class)] class extends SimpleState {
        };
        $first = new #[StateStore(STORE, StrongFirstWrite::class)] class extends SimpleState {
        };
        $body  = [];
        $body  = assert_equals($body, 0, $last->counter, 'initial value correct');
        $stateManager->save_object($last);
        $stateManager->load_object($last);
        $stateManager->load_object($first);
        $body = assert_equals($body, 0, $last->counter, 'Starting from 0');

        $first->counter = 1;
        $last->counter  = 2;
        $stateManager->save_object($last);
        $stateManager->load_object($last);
        $body = assert_equals($body, 2, $last->counter, 'last-write update succeeds');
        $body = assert_throws(
            $body,
            SaveStateFailure::class,
            "first-write update fails",
            function () use ($stateManager, $first) {
                $stateManager->save_object($first);
            }
        );

        return $body;
    }
);

$app->get(
    '/test/state/transactions',
    function (StateManager $stateManager, \DI\Container $container) {
        $reset_state = new TState($container);
        $stateManager->save_object($reset_state);
        ($transaction = new TState($container))->begin();
        $body                 = [];
        $body                 = assert_equals($body, 0, $transaction->counter, 'initial count = 0');
        $transaction->counter += 1;
        $body                 = assert_equals(
            $body,
            1,
            $transaction->counter,
            'counter was incremented in transaction'
        );

        $committed_state = new TState($container);
        $stateManager->load_object($committed_state);
        $body = assert_equals($body, 0, $committed_state->counter, 'counter not incremented outside transaction');

        $transaction->increment(1);
        $stateManager->load_object($committed_state);

        $body = assert_equals($body, 2, $transaction->counter, 'counter was incremented in transaction via function');
        $body = assert_equals($body, 0, $committed_state->counter, 'counter not incremented outside transaction');

        $transaction->commit();
        $stateManager->load_object($committed_state);
        $body = assert_equals($body, 2, $transaction->counter, 'committed transaction can be read from');
        $body = assert_equals($body, 2, $committed_state->counter, 'counter state is stored');
        $body = assert_throws(
            $body,
            StateAlreadyCommitted::class,
            'cannot change committed state',
            function () use ($transaction) {
                $transaction->counter = 5;
            }
        );

        $store = new SimpleState();
        $stateManager->save_object($store);
        ($one = new #[StateStore(STORE, StrongFirstWrite::class)] class($container) extends TState {
        })->begin();
        ($two = new #[StateStore(STORE, StrongLastWrite::class)] class($container) extends TState {
        })->begin();

        $one->counter = 1;
        $one->counter = 3;
        $two->counter = 1;
        $two->counter = 2;
        $two->commit();
        $two->begin();

        $body = assert_equals($body, 2, $two->counter, 'last-write transaction commits');
        $body = assert_throws(
            $body,
            SaveStateFailure::class,
            'fail to commit first-write transaction',
            function () use ($one) {
                $one->commit();
            }
        );
        $one->begin();
        $one = new TState($container);
        $one->begin();
        $body = assert_equals($body, 2, $one->counter, 'first-write transaction failed');

        return $body;
    }
);

$app->get(
    '/test/pubsub',
    function (\DI\Container $container) {
        $publisher = new Publish('pubsub', $container);
        $topic     = $publisher->topic(topic: 'test');
        $body      = [];

        $topic->publish(['test_event']);
        sleep(5);
        $body = assert_equals(
            $body,
            true,
            file_exists('/tmp/sub-received'),
            'sub received message'
        );
        if (file_exists('/tmp/sub-received')) {
            $body["Received this data"] = json_decode($raw_event = file_get_contents('/tmp/sub-received'));
            unlink('/tmp/sub-received');
        }
        $body = assert_equals(
            $body,
            true,
            CloudEvent::parse($raw_event ?? null)->validate(),
            'should be valid cloud event'
        );

        $return = ['simple-test' => $body];
        $body   = [];

        $event                    = new CloudEvent();
        $event->id                = "123";
        $event->source            = "http://example.com";
        $event->type              = "com.example.test";
        $event->data_content_type = 'application/json';
        $event->subject           = 'yolo';
        $event->time              = new DateTime();
        $event->data              = ['yolo'];
        $topic->publish($event);
        sleep(5);
        $body                           = assert_equals(
            $body,
            true,
            file_exists('/tmp/sub-received'),
            'sub received message'
        );
        $body["Received this raw data"] = json_decode($raw_event = file_get_contents('/tmp/sub-received'));
        unlink('/tmp/sub-received');
        //unset($event->time);
        $event->topic                = "test";
        $event->pubsub_name          = "pubsub";
        $body["Expecting this data"] = json_decode($event->to_json());
        $received                    = CloudEvent::parse($raw_event);
        unset($received->trace_id);
        $body['Received this decoded data']   = json_decode($received->to_json());
        $body                                 = assert_equals(
            $body,
            $event->to_json(),
            $received->to_json(),
            'Event should be the same event we sent, minus the trace id.'
        );
        $return['Testing custom cloud event'] = $body;
        $body                                 = [];

        $topic->publish(
            json_decode(
                <<<RAW
{
    "specversion" : "1.0",
    "type" : "xml.message",
    "source" : "https://example.com/message",
    "subject" : "Test XML Message",
    "id" : "id-1234-5678-9101",
    "time" : "2020-09-23T06:23:21Z",
    "datacontenttype" : "text/xml",
    "data" : "<note><to>User1</to><from>user2</from><message>hi</message></note>"
}
RAW
                ,
                true
            )
        );
        sleep(2);
        $body                       = assert_equals(
            $body,
            true,
            file_exists('/tmp/sub-received'),
            'sub received message'
        );
        $body["Received this data"] = json_decode($raw_event = file_get_contents('/tmp/sub-received'));
        unlink('/tmp/sub-received');
        $return['Publishing raw event'] = $body;

        return $return;
    }
);

$app->get(
    '/test/invoke',
    function (\Dapr\DaprClient $client) {
        $body   = [];
        $result = $client->post("/invoke/dev/method/say_something", "My Message");
        $body   = assert_equals($body, 200, $result->code, 'Should receive a 200 response');

        $json = '{"ok": true}';

        $result = $client->post('/invoke/dev/method/test_json', $json);
        $body   = assert_equals($body, 200, $result->code, 'Static function should receive json string');

        return $body;
    }
);
$app->post(
    '/say_something',
    function () {
    }
);
$app->post(
    '/test_json',
    function (#[FromBody] string $body) {
        return json_decode($body);
    }
);

$app->get(
    '/test/binding',
    function () {
        $body      = [];
        $cron_file = sys_get_temp_dir().'/cron';
        //Binding::invoke_output('cron', 'delete');
        $body = assert_equals($body, true, file_exists($cron_file), 'we should have received at least one cron');
        // see https://github.com/dapr/components-contrib/issues/639
        //sleep(1);
        //unlink($cron_file);
        //sleep(1);

        //assert_equals(false, file_exists($cron_file), 'cron should be stopped');
        return $body;
    }
);
$app->get('/cron', fn() => 'hello world');
$app->post(
    '/testsub',
    function (
        #[FromBody] CloudEvent $event,
        \Psr\Http\Message\RequestInterface $request,
        \Psr\Log\LoggerInterface $logger
    ) {
        $logger->critical('Received an event: {subject}', ['subject' => $event->subject]);
        touch('/tmp/sub-received');
        file_put_contents('/tmp/sub-received', $event->to_json());

        return [
            'status' => 'SUCCESS',
        ];
    }
);

$app->get(
    '/do_tests',
    function (\Dapr\DaprClient $client) {
        $test_results = [
            '/test/actors'            => null,
            '/test/binding'           => null,
            '/test/invoke'            => null,
            '/test/pubsub'            => null,
            '/test/state/concurrency' => null,
            '/test/state'             => null,
        ];

        foreach (array_keys($test_results) as $suite) {
            $result               = $client->get('/invoke/dev/method'.$suite);
            $body                 = [];
            $body                 = assert_equals($body, 200, $result->code, 'test completed successfully');
            $test_results[$suite] = [
                'status'  => $body,
                'results' => $result->data,
            ];
        }
        return $test_results;
    }
);

$app->start();
die();

#[StateStore(STORE, StrongFirstWrite::class)]
class SimpleState
{
    public mixed $data = null;

    public int $counter = 0;

    public function increment(int $amount = 1): void
    {
        $this->counter += $amount;
    }
}

ActorRuntime::register_actor(SimpleActor::class);
Subscribe::to_topic('pubsub', 'test', 'testsub');
Runtime::register_method('do_tests', 'do_tests', 'GET');
Runtime::register_method(
    'say_something',
    function ($message) {
        assert_equals('My Message', $message);
    }
);

class MethodHandler
{
    public static function test_static($input)
    {
        assert_equals('{"ok": true}', $input);
    }

    public function test_instance($input)
    {
        assert_equals('{"ok": true}', $input);
    }
}

Runtime::register_method('test_static', [MethodHandler::class, 'test_static'], 'POST');
Runtime::register_method('test_instance', [new MethodHandler(), 'test_instance'], 'POST');
Runtime::register_method('test_inline', fn($input) => assert_equals('{"ok": true}', $input), 'POST');
Binding::register_input_binding(
    'cron',
    function () {
        file_put_contents(sys_get_temp_dir().'/cron', 'true');
    }
);

$uri         = $_SERVER['REQUEST_URI'];
$http_method = $_SERVER['REQUEST_METHOD'];
header('Content-Type: application/json');
$result = Runtime::get_handler_for_route($http_method, $uri)();
http_response_code($result['code']);
if (isset($result['body'])) {
    echo $result['body'];
}
die();

function assert_equals(array $body, $expected, $actual, $message = null): array
{
    if ($actual === $expected) {
        $body[$message ? "$message: " : ''] = "✔";
    } else {
        $body[$message ? "$message: " : ''] = "❌";
    }

    return $body;
}

function assert_not_equals(array $body, $expected, $actual, $message = null): array
{
    if ($actual !== $expected) {
        $body[$message ? "$message: " : ''] = "✔";
    } else {
        $body[$message ? "$message: " : ''] = "❌";
    }

    return $body;
}

function assert_throws(array $body, $exception, $message, $callback): array
{
    try {
        $callback();
        $body[$message] = "❌";
        throw new Exception("Expected $exception, but was not thrown\n");
    } catch (Exception $ex) {
        $body[$message] = "✔";
    }

    return $body;
}

function test_bindings()
{
    $cron_file = sys_get_temp_dir().'/cron';
    //Binding::invoke_output('cron', 'delete');
    assert_equals(true, file_exists($cron_file), 'we should have received at least one cron');
    // see https://github.com/dapr/components-contrib/issues/639
    //sleep(1);
    //unlink($cron_file);
    //sleep(1);

    //assert_equals(false, file_exists($cron_file), 'cron should be stopped');
}

function do_tests()
{
    ob_start();
    $result         = exec_tests();
    $output         = ob_get_clean();
    $result['body'] = $output.($result['body'] ?? '');

    return $result;
}

function exec_tests(ResponseInterface $response)
{
    $response = $response->withAddedHeader('Content-Type', 'text/html; charset=UTF-8');
    header('Content-Type: text/html; charset=UTF-8');
    $tests = [
        'state_test'                => [
            'description' => 'Test setting and getting state',
        ],
        'state_concurrency'         => [
            'description' => 'Tests concurrency of state changes',
        ],
        'transaction_test'          => [
            'description' => 'Test transactional state',
        ],
        'multiple_transactions'     => [
            'description' => 'Test multiple concurrent transactions',
        ],
        'test_actor'                => [
            'description' => 'Testing some basic actors',
        ],
        'test_pubsub'               => [
            'description' => 'Testing publish/subscribe pattern',
        ],
        'test_invoke_serialization' => [
            'description' =>
                'See <a href="https://v1-rc2.docs.dapr.io/developing-applications/sdks/serialization/">the docs</a>',
        ],
        'test_bindings'             => [
            'description' => 'test bindings',
        ],
    ];

    ?>
    <html lang="en-us">
    <head>
        <title>Testing</title>
    </head>
    <body>
    <h1>
        Simple Integration Test Runner (<?= gethostname() ?>)
    </h1>
    <?php
    foreach ($tests as $test => $meta) {
        echo "<h2>".ucwords(str_replace('_', ' ', $test))."</h2>";
        echo "<p>".($meta['description'] ?? '').'</p>';
        echo "<pre>";
        try {
            $test();
        } catch (Exception $exception) {
            return [
                'code' => 500,
                'body' => $exception->getMessage(),
            ];
        }
        echo "</pre>";
    }
    ?>
    <h1>All Tests PASSED</h1>
    </body>
    </html>
    <?php
    return [
        'code' => 200,
    ];
}
