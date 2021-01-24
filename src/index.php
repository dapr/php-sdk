<?php

require_once __DIR__.'/../vendor/autoload.php';

define('STORE', 'statestore');

use Dapr\Actors\Actor;
use Dapr\Actors\ActorProxy;
use Dapr\Actors\ActorRuntime;
use Dapr\Actors\ActorState;
use Dapr\Actors\Attributes\DaprType;
use Dapr\Actors\Reminder;
use Dapr\Actors\Timer;
use Dapr\consistency\StrongFirstWrite;
use Dapr\consistency\StrongLastWrite;
use Dapr\exceptions\SaveStateFailure;
use Dapr\exceptions\StateAlreadyCommitted;
use Dapr\PubSub\CloudEvent;
use Dapr\PubSub\Publish;
use Dapr\PubSub\Subscribe;
use Dapr\Runtime;
use Dapr\State\Attributes\StateStore;
use Dapr\State\State;
use Dapr\State\TransactionalState;
use Monolog\Handler\ErrorLogHandler;
use Monolog\Logger;

$logger  = new Logger('dapr');
$handler = new ErrorLogHandler(level: Logger::INFO);
$logger->pushHandler($handler);
$logger->pushProcessor(new \Monolog\Processor\PsrLogMessageProcessor());
$logger->pushProcessor(new \Monolog\Processor\IntrospectionProcessor());
Runtime::set_logger($logger);

function testsub(): void
{
    touch('/tmp/sub-received');
    file_put_contents('/tmp/sub-received', file_get_contents('php://input'));
    echo json_encode(
        [
            'status' => 'SUCCESS',
        ]
    );
}

#[DaprType('SimpleActor')]
interface ISimpleActor
{
    function increment($amount = 1);

    function get_count(): int;

    function set_object(SimpleObject $object): void;

    function get_object(): SimpleObject;

    function a_function(): bool;
}

class SimpleObject
{
    public string $foo = "";
    public array $bar = [];
}

class SimpleActorState extends ActorState
{
    /**
     * @property int
     */
    public int $count = 0;

    public SimpleObject $complex_object;
}

#[DaprType('SimpleActor')]
class SimpleActor extends Actor
{
    /**
     * SimpleActor constructor.
     *
     * @param string $id
     * @param SimpleActorState $state
     */
    public function __construct(protected string $id, private SimpleActorState $state)
    {
        parent::__construct($id);
    }

    public function remind(string $name, $data): void
    {
        switch ($name) {
            case 'increment':
                $this->increment($data['amount'] ?? 1);
                break;
        }
    }

    /**
     * @param int $amount
     *
     * @return void
     */
    public function increment($amount = 1)
    {
        $this->state->count += $amount;
    }

    public function get_count(): int
    {
        return $this->state->count ?? 0;
    }

    function set_object(SimpleObject $object): void
    {
        $this->state->complex_object = $object;
    }

    function get_object(): SimpleObject
    {
        return $this->state->complex_object;
    }

    function a_function(): bool
    {
        return true;
    }
}

#[StateStore(STORE, StrongFirstWrite::class)]
class SimpleState
{
    public $data;

    public int $counter = 0;

    public function increment(int $amount = 1): void
    {
        $this->counter += $amount;
    }
}

#[StateStore(STORE, StrongFirstWrite::class)]
class TState extends TransactionalState
{
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

$uri         = $_SERVER['REQUEST_URI'];
$http_method = $_SERVER['REQUEST_METHOD'];
header('Content-Type: application/json');
$result = Runtime::get_handler_for_route($http_method, $uri)();
http_response_code($result['code']);
if (isset($result['body'])) {
    echo $result['body'];
}
die();

function assert_equals($expected, $actual, $message = null): void
{
    echo $message ? "$message: " : '';
    if ($actual === $expected) {
        echo "✔\n";
    } else {
        echo "❌\n";
        throw new Exception("Expected $expected, but got $actual.\n");
    }
}

function assert_not_equals($expected, $actual, $message = null): void
{
    echo $message ? "$message: " : '';
    if ($actual !== $expected) {
        echo "✔\n";
    } else {
        echo "❌\n";
        throw new Exception("Expected $actual to not equal $expected.\n");
    }
}

function assert_throws($exception, $message, $callback): void
{
    echo "$message: ";
    try {
        $callback();
        echo "❌\n";
        throw new Exception("Expected $exception, but was not thrown\n");
    } catch (Exception $ex) {
        echo "✔\n";
    }
}

function state_test(): void
{
    $state = new SimpleState();
    State::save_state($state);
    assert_equals(null, $state->data, 'state is empty');
    assert_equals(0, $state->counter, 'initial state is correct');

    $state->data = 'data';
    State::save_state($state);
    assert_equals('data', $state->data, 'saved correct state');

    $state = new SimpleState();
    State::load_state($state);
    assert_equals('data', $state->data, 'properly loaded saved state');
}

function state_concurrency(): void
{
    $last  = new #[StateStore(STORE, StrongLastWrite::class)] class extends SimpleState {
    };
    $first = new #[StateStore(STORE, StrongFirstWrite::class)] class extends SimpleState {
    };
    assert_equals(0, $last->counter, 'initial value correct');
    State::save_state($last);
    State::load_state($last);
    State::load_state($first);
    assert_equals(0, $last->counter, 'Starting from 0');

    $first->counter = 1;
    $last->counter  = 2;
    State::save_state($last);
    State::load_state($last);
    assert_equals(2, $last->counter, 'last-write update succeeds');
    assert_throws(
        SaveStateFailure::class,
        "first-write update fails",
        function () use ($first) {
            State::save_state($first);
        }
    );
}

function transaction_test(): void
{
    $reset_state = new TState();
    State::save_state($reset_state);
    ($transaction = new TState())->begin();
    assert_equals(0, $transaction->counter, 'initial count = 0');
    $transaction->counter += 1;
    assert_equals(1, $transaction->counter, 'counter was incremented in transaction');

    $committed_state = new TState();
    State::load_state($committed_state);
    assert_equals(0, $committed_state->counter, 'counter not incremented outside transaction');

    $transaction->increment(1);
    State::load_state($committed_state);

    assert_equals(2, $transaction->counter, 'counter was incremented in transaction via function');
    assert_equals(0, $committed_state->counter, 'counter not incremented outside transaction');

    $transaction->commit();
    State::load_state($committed_state);
    assert_equals(2, $transaction->counter, 'committed transaction can be read from');
    assert_equals(2, $committed_state->counter, 'counter state is stored');
    assert_throws(
        StateAlreadyCommitted::class,
        'cannot change committed state',
        function () use ($transaction) {
            $transaction->counter = 5;
        }
    );
}

function multiple_transactions(): void
{
    $store = new SimpleState();
    State::save_state($store);
    ($one = new #[StateStore(STORE, StrongFirstWrite::class)] class extends TState {
    })->begin();
    ($two = new #[StateStore(STORE, StrongLastWrite::class)] class extends TState {
    })->begin();

    $one->counter = 1;
    $one->counter = 3;
    $two->counter = 1;
    $two->counter = 2;
    $two->commit();
    $two->begin();

    assert_equals(2, $two->counter, 'last-write transaction commits');
    assert_throws(
        SaveStateFailure::class,
        'fail to commit first-write transaction',
        function () use ($one) {
            $one->commit();
        }
    );
    $one->begin();
    $one = new TState();
    $one->begin();
    assert_equals(2, $one->counter, 'first-write transaction failed');
}

function test_actor(): void
{
    $id = uniqid(prefix: 'actor_');

    /**
     * @var ISimpleActor $actor
     */
    $actor = ActorProxy::get(interface: ISimpleActor::class, id: $id);

    assert_equals(0, $actor->get_count(), 'Empty actor should have no data');
    $actor->increment();
    assert_equals(1, $actor->get_count(), 'Actor should have data');

    $reminder = new Reminder(
        name: 'increment',
        due_time: new DateInterval('PT1S'),
        data: ['amount' => 2],
        period: new DateInterval('PT10M')
    );
    $actor->create_reminder(reminder: $reminder);
    sleep(2);
    assert_equals(3, $actor->get_count(), 'Reminder should increment');
    $read_reminder = $actor->get_reminder('increment');
    assert_equals(
        $reminder->due_time->format(\Dapr\Formats::FROM_INTERVAL),
        $read_reminder->due_time->format(\Dapr\Formats::FROM_INTERVAL),
        'time formats are delivered ok'
    );

    $timer = new Timer(
        name: 'increment',
        due_time: new DateInterval('PT1S'),
        period: new DateInterval('P2D'),
        callback: 'increment',
        data: [2]
    );
    $actor->create_timer(timer: $timer);
    sleep(2);
    assert_equals(5, $actor->get_count(), 'Timer should increment');

    $actor->delete_timer('increment');
    $actor->delete_reminder('increment');
    $actor->delete_reminder('nope');
    $actor->delete_timer('nope');

    $object      = new SimpleObject();
    $object->bar = ['hello', 'world'];
    $object->foo = "hello world";
    $actor->set_object($object);
    $saved_object = $actor->get_object();
    assert_equals($object->bar, $saved_object->bar, "[object] saved array should match");
    assert_equals($object->foo, $saved_object->foo, "[object] saved string should match");

    assert_equals(true, $actor->a_function(), 'actor can return a simple value');
}

function test_pubsub(): void
{
    $publisher = new Publish('pubsub');
    $topic     = $publisher->topic(topic: 'test');

    $topic->publish(['test_event']);
    sleep(2);
    assert_equals(true, file_exists('/tmp/sub-received'), 'sub received message');
    echo "Received this data:\n";
    echo json_encode(json_decode($raw_event = file_get_contents('/tmp/sub-received')), JSON_PRETTY_PRINT)."\n";
    unlink('/tmp/sub-received');
    assert_equals(true, CloudEvent::parse($raw_event)->validate(), 'should be valid cloud event');


    echo "\n\nTesting custom cloud event";
    $event                    = new CloudEvent();
    $event->id                = "123";
    $event->source            = "http://example.com";
    $event->type              = "com.example.test";
    $event->data_content_type = 'application/json';
    $event->subject           = 'yolo';
    $event->time              = new DateTime();
    $event->data              = ['yolo'];
    $topic->publish($event);
    sleep(2);
    assert_equals(true, file_exists('/tmp/sub-received'), 'sub received message');
    echo "Received this data:\n";
    echo json_encode(json_decode($raw_event = file_get_contents('/tmp/sub-received')), JSON_PRETTY_PRINT)."\n";
    unlink('/tmp/sub-received');
    unset($event->time);
    $received = CloudEvent::parse($raw_event);
    assert_equals(
        $event->to_json(),
        $received->to_json(),
        'Event should be the same event we sent, but without time, apparently'
    );

    echo "\n\nPublishing raw event";
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
    assert_equals(true, file_exists('/tmp/sub-received'), 'sub received message');
    echo "Received this data:\n";
    echo json_encode(json_decode($raw_event = file_get_contents('/tmp/sub-received')), JSON_PRETTY_PRINT)."\n";
    unlink('/tmp/sub-received');
}

function test_invoke_serialization()
{
    $result = Runtime::invoke_method('dev', 'say_something', 'My Message');
    assert_equals(200, $result->code, 'Should receive a 200 response');

    $json = '{"ok": true}';

    $result = Runtime::invoke_method('dev', 'test_static', $json);
    assert_equals(200, $result->code, 'Static function should receive json string');
    $result = Runtime::invoke_method('dev', 'test_instance', $json);
    assert_equals(200, $result->code, 'Instance function should receive json string');
    $result = Runtime::invoke_method('dev', 'test_inline', $json);
    assert_equals(200, $result->code, 'Closure should receive json string');
}


function do_tests()
{
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
        $test();
        echo "</pre>";
    }
    ?>
    <h1>All Tests PASSED</h1>
    </body>
    </html>
    <?php
}
