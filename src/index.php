<?php

require_once __DIR__.'/../vendor/autoload.php';

define('STORE', 'statestore');

use Dapr\Actors\Actor;
use Dapr\Actors\ActorProxy;
use Dapr\Actors\ActorRuntime;
use Dapr\Actors\ActorState;
use Dapr\Actors\IActor;
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
use Dapr\State\State;
use Dapr\State\TransactionalState;

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

interface ISimpleActor extends IActor
{
    public const DAPR_TYPE = 'SimpleActor';

    function increment($amount = 1);

    function get_count(): int;

    function set_object(SimpleObject $object): void;

    function get_object(): SimpleObject;
}

class SimpleObject
{
    public string $foo = "";
    public array $bar = [];
}

class SimpleActorState extends State
{
    /**
     * @property int
     */
    public int $count = 0;

    public SimpleObject $complex_object;
}

class SimpleActor implements ISimpleActor
{
    use Actor;
    use ActorState;

    public const STATE_TYPE = [
        'store'       => 'statestore',
        'type'        => SimpleActorState::class,
        'consistency' => StrongLastWrite::class,
    ];

    /**
     * SimpleActor constructor.
     *
     * @param string $id
     * @param SimpleActorState $state
     */
    public function __construct(private string $id, private $state)
    {
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

    public function on_activation(): void
    {
    }

    public function on_deactivation(): void
    {
    }

    public function get_id(): mixed
    {
        return $this->id;
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
}

class SimpleState extends State
{
    public $data;

    public int $counter = 0;

    public function increment(int $amount = 1): void
    {
        $this->counter += $amount;
    }
}

ActorRuntime::register_actor('SimpleActor', SimpleActor::class);
Subscribe::to_topic('pubsub', 'test', 'testsub');
Runtime::register_method('do_tests', 'do_tests', 'GET');
Runtime::register_method(
    'say_something',
    function ($message) {
        assert_equals('My Message', $message);
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
    $state = new SimpleState(store_name: STORE);
    $state->save_state();
    assert_equals(null, $state->data, 'state is empty');
    assert_equals(0, $state->counter, 'initial state is correct');

    $state->data = 'data';
    $state->save_state();
    assert_equals('data', $state->data, 'saved correct state');

    $result = State::get_single(store_name: STORE, key: 'data');
    assert_equals($state->data, $result->data, 'single state loading works');

    $state = new SimpleState(store_name: STORE);
    $state->load();
    assert_equals('data', $state->data, 'properly loaded saved state');
}

function state_concurrency(): void
{
    $last  = new SimpleState(store_name: STORE, consistency: new StrongLastWrite);
    $first = new SimpleState(store_name: STORE, consistency: new StrongFirstWrite);
    $last->save_state();
    $last->load();
    $first->load();
    assert_equals(0, $last->counter, 'Starting from 0');

    $first->counter = 1;
    $last->counter  = 2;
    $last->save_state();
    $last->load();
    assert_equals(2, $last->counter, 'last-write update succeeds');
    assert_throws(
        SaveStateFailure::class,
        "first-write update fails",
        function () use ($first) {
            $first->save_state();
        }
    );
}

function transaction_test(): void
{
    $stored          = new SimpleState(store_name: STORE);
    $stored->counter = 0;
    $stored->save_state();

    /**
     * @var SimpleState $transaction
     */
    $transaction = TransactionalState::begin(type: SimpleState::class, store_name: STORE);
    assert_equals(0, $transaction->counter, 'initial count = 0');
    $transaction->counter += 1;
    $stored->load();

    assert_equals(1, $transaction->counter, 'counter was incremented in transaction');
    assert_equals(0, $stored->counter, 'counter not incremented outside transaction');

    $transaction->increment(1);
    $stored->load();

    assert_equals(2, $transaction->counter, 'counter was incremented in transaction via function');
    assert_equals(0, $stored->counter, 'counter not incremented outside transaction');

    TransactionalState::commit(state: $transaction);
    $stored->load();
    assert_equals(2, $transaction->counter, 'committed transaction can be read from');
    assert_equals(2, $stored->counter, 'counter state is stored');
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
    $store = new SimpleState(store_name: STORE);
    $store->save_state();
    /**
     * @var SimpleState
     */
    $one = TransactionalState::begin(type: SimpleState::class, store_name: STORE, consistency: new StrongFirstWrite);

    /**
     * @var SimpleState
     */
    $two = TransactionalState::begin(type: SimpleState::class, store_name: STORE, consistency: new StrongLastWrite);

    $first_etag = $one->counter__etag;

    $one->counter = 1;
    $one->counter = 3;
    $two->counter = 1;
    $two->counter = 2;
    TransactionalState::commit(state: $two);
    /**
     * @var SimpleState
     */
    $two = TransactionalState::begin(type: SimpleState::class, store_name: STORE, consistency: new StrongLastWrite);

    assert_not_equals($first_etag, $two->counter__etag, 'etag should change');
    assert_equals(2, $two->counter, 'last-write transaction commits');
    assert_throws(
        SaveStateFailure::class,
        'fail to commit first-write transaction',
        function () use ($one) {
            TransactionalState::commit(state: $one);
        }
    );
    /**
     * @var SimpleState
     */
    $one = TransactionalState::begin(type: SimpleState::class, store_name: STORE, consistency: new StrongFirstWrite);
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
        $read_reminder->due_time->format(\Dapr\Formats::FROM_INTERVAL)
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
}


function do_tests()
{
    header('Content-Type: text/html; charset=UTF-8');
    $tests = [
        'state_test'             => [
            'description' => 'Test setting and getting state',
        ],
        'state_concurrency'      => [
            'description' => 'Tests concurrency of state changes',
        ],
        'transaction_test'       => [
            'description' => 'Test transactional state',
        ],
        'multiple_transactions'  => [
            'description' => 'Test multiple concurrent transactions',
        ],
        'test_actor'             => [
            'description' => 'Testing some basic actors',
        ],
        'test_pubsub'            => [
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
