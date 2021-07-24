<?php

// @codeCoverageIgnoreStart

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../tests/Fixtures/SimpleActor.php';

define('STORE', 'statestore');

use Dapr\Actors\ActorReference;
use Dapr\Actors\Generators\ProxyFactory;
use Dapr\Actors\IActor;
use Dapr\Actors\Reminder;
use Dapr\Actors\Timer;
use Dapr\App;
use Dapr\Attributes\FromBody;
use Dapr\Client\AppId;
use Dapr\consistency\StrongFirstWrite;
use Dapr\consistency\StrongLastWrite;
use Dapr\DaprClient;
use Dapr\exceptions\SaveStateFailure;
use Dapr\exceptions\StateAlreadyCommitted;
use Dapr\Formats;
use Dapr\PubSub\CloudEvent;
use Dapr\PubSub\Subscription;
use Dapr\PubSub\Topic;
use Dapr\State\Attributes\StateStore;
use Dapr\State\FileWriter;
use Dapr\State\StateManager;
use Dapr\State\TransactionalState;
use DI\ContainerBuilder;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

use function DI\factory;

#[StateStore(STORE, StrongFirstWrite::class)]
class TState extends TransactionalState
{
    public int $counter = 0;

    public function increment(int $amount = 1): void
    {
        $this->counter += $amount;
    }
}

$app = App::create(
    configure: fn(ContainerBuilder $builder) => $builder->addDefinitions(
    [
        'dapr.subscriptions' => factory(
            fn(ContainerInterface $container) => [
                new Subscription('pubsub', 'test', '/testsub'),
            ]
        ),
        'dapr.actors' => [SimpleActor::class],
    ]
)
);

$app->get(
    '/test/actors',
    function (ProxyFactory $proxyFactory, DaprClient $client, LoggerInterface $logger) {
        $id = uniqid(prefix: 'actor_');
        $reference = new ActorReference($id, 'SimpleActor');

        /**
         * @var ISimpleActor|IActor $actor
         */
        $actor = $reference->bind(ISimpleActor::class, $proxyFactory);
        $body = [];

        $logger->critical('Created actor proxy');
        $body = assert_equals($body, 0, $actor->get_count(), 'Empty actor should have no data');
        $actor->increment();
        $body = assert_equals($body, 1, $actor->get_count(), 'Actor should have data');
        $logger->critical('Incremented actor');

        // get the actor proxy again
        $reference = ActorReference::get($actor);
        $actor = $reference->bind(ISimpleActor::class, $proxyFactory);

        $reminder = new Reminder(
            name: 'increment',
            due_time: new DateInterval('PT1S'),
            data: ['amount' => 2],
            period: new DateInterval('PT10M')
        );
        $actor->create_reminder($reminder);
        $logger->critical('Created reminder');
        sleep(2);
        $body = assert_equals($body, 3, $actor->get_count(), 'Reminder should increment');
        $read_reminder = $actor->get_reminder('increment');
        $logger->critical('Got reminder');
        $body = assert_equals(
            $body,
            $reminder->due_time->format(Formats::FROM_INTERVAL),
            $read_reminder->due_time->format(Formats::FROM_INTERVAL),
            'time formats are delivered ok'
        );

        $timer = new Timer(
            name: 'increment',
            due_time: new DateInterval('PT1S'),
            period: new DateInterval('P2D'),
            callback: 'increment',
            data: 2
        );
        $actor->create_timer($timer);
        $logger->critical('Created timer');
        sleep(2);
        $body = assert_equals($body, 5, $actor->get_count(), 'Timer should increment');

        $actor->delete_timer('increment');
        $actor->delete_reminder('increment');
        $actor->delete_reminder('nope');
        $actor->delete_timer('nope');
        $logger->critical('Cleaned up');

        $object = new SimpleObject();
        $object->bar = ['hello', 'world'];
        $object->foo = "hello world";
        $actor->set_object($object);
        $saved_object = $actor->get_object();
        $body = assert_equals($body, $object->bar, $saved_object->bar, "[object] saved array should match");
        $body = assert_equals($body, $object->foo, $saved_object->foo, "[object] saved string should match");

        $body = assert_equals($body, true, $actor->a_function(), 'actor can return a simple value');

        return $body;
    }
);

$app->get(
    '/test/state',
    function (StateManager $stateManager) {
        $body = [];
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
        $state = new SimpleState();
        $stateManager->load_object($state, $prefix);
        $body = assert_not_equals($body, 'data', $state->data, 'prefix should work');

        $random_key = uniqid();
        $state = $stateManager->load_state('statestore', $random_key, 'hello');
        $body = assert_equals($body, 'hello', $state->value, 'single key read with default');

        $stateManager->save_state('statestore', $state);
        $state2 = $stateManager->load_state('statestore', $random_key, 'world');
        $body = assert_equals($body, 'hello', $state2->value, 'single key write');

        return $body;
    }
);

$app->get(
    '/test/state/concurrency',
    function (StateManager $stateManager) {
        $last = new #[StateStore(STORE, StrongLastWrite::class)] class extends SimpleState {
        };
        $first = new #[StateStore(STORE, StrongFirstWrite::class)] class extends SimpleState {
        };
        $body = [];
        $body = assert_equals($body, 0, $last->counter, 'initial value correct');
        $stateManager->save_object($last);
        $stateManager->load_object($last);
        $stateManager->load_object($first);
        $body = assert_equals($body, 0, $last->counter, 'Starting from 0');

        $first->counter = 1;
        $last->counter = 2;
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
        $reset_state = $container->make(TState::class);
        $stateManager->save_object($reset_state);
        ($transaction = $container->make(TState::class))->begin();
        $body = [];
        $body = assert_equals($body, 0, $transaction->counter, 'initial count = 0');
        $transaction->counter += 1;
        $body = assert_equals(
            $body,
            1,
            $transaction->counter,
            'counter was incremented in transaction'
        );

        $committed_state = $container->make(TState::class);
        $stateManager->load_object($committed_state);
        $body = assert_equals($body, 0, $committed_state->counter, 'counter not incremented outside transaction');

        $transaction->increment();
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
        ($one = new #[StateStore(STORE, StrongFirstWrite::class)] class($container, $container) extends TState {
        })->begin();
        ($two = new #[StateStore(STORE, StrongLastWrite::class)] class($container, $container) extends TState {
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
        $one = new TState($container, $container);
        $one->begin();
        $body = assert_equals($body, 2, $one->counter, 'first-write transaction failed');

        return $body;
    }
);

$app->get(
    '/test/pubsub',
    function (\Dapr\Client\DaprClient $client) {
        $topic = new Topic('pubsub', 'test', $client);
        $body = [];

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
        $body = [];

        $event = new CloudEvent();
        $event->id = "123";
        $event->source = "http://example.com";
        $event->type = "com.example.test";
        $event->data_content_type = 'application/json';
        $event->subject = 'yolo';
        $event->time = new DateTime();
        $event->data = ['yolo'];
        $topic->publish($event);
        sleep(5);
        $body = assert_equals(
            $body,
            true,
            file_exists('/tmp/sub-received'),
            'sub received message'
        );
        $body["Received this raw data"] = json_decode($raw_event = file_get_contents('/tmp/sub-received'));
        unlink('/tmp/sub-received');
        //unset($event->time);
        $event->topic = "test";
        $event->pubsub_name = "pubsub";
        $body["Expecting this data"] = json_decode($event->to_json());
        $received = CloudEvent::parse($raw_event);
        unset($received->trace_id);
        $body['Received this decoded data'] = json_decode($received->to_json());
        $body = assert_equals(
            $body,
            $event->to_json(),
            $received->to_json(),
            'Event should be the same event we sent, minus the trace id.'
        );
        $return['Testing custom cloud event'] = $body;
        $body = [];

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
        $body = assert_equals(
            $body,
            true,
            file_exists('/tmp/sub-received'),
            'sub received message'
        );
        $body["Received this data"] = json_decode($raw_event = file_get_contents('/tmp/sub-received'));
        unlink('/tmp/sub-received');
        $return['Publishing raw event'] = $body;

        $topic->publish('raw data', content_type: 'application/octet-stream');
        sleep(2);
        $return['Binary response'] = ['raw' => json_decode($raw_event = file_get_contents('/tmp/sub-received'), true)];
        unlink('/tmp/sub-received');
        $return['Binary response'] = assert_equals(
            $return['Binary response'],
            'raw data',
            $return['Binary response']['raw']['data'],
            'Data properly decoded'
        );

        return $return;
    }
);

$app->get(
    '/test/invoke',
    function (\Dapr\Client\DaprClient $client) {
        $body = [];
        $result = $client->invokeMethod('POST', new AppId('dev'), 'say_something', 'My Message');
        $body = assert_equals($body, 200, $result->getStatusCode(), 'Should receive a 200 response');

        $json = '{"ok": true}';

        $result = $client->invokeMethod('POST', new AppId('dev'), 'test_json', $json);
        $body = assert_equals($body, 200, $result->getStatusCode(), 'Static function should receive json string');

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
        $body = [];
        $cron_file = sys_get_temp_dir() . '/cron';
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
$app->post('/cron', fn() => touch(sys_get_temp_dir() . '/cron'));
$app->post(
    '/testsub',
    function (
        #[FromBody] CloudEvent $event,
        LoggerInterface $logger
    ) {
        $logger->critical('Received an event: {subject}', ['subject' => $event->subject]);
        touch('/tmp/sub-received');
        FileWriter::write('/tmp/sub-received', $event->to_json());

        return [
            'status' => 'SUCCESS',
        ];
    }
);

$app->get(
    '/do_tests',
    function (\Dapr\Client\DaprClient $client) {
        while (true) {
            sleep(1);
            if ($client->isDaprHealthy()) {
                $meta = $client->getMetadata();
                error_log(print_r($meta, true));
                if (!empty($meta->actors)) {
                    break;
                }
            }
        }

        $test_results = [
            'test/actors' => null,
            'test/binding' => null,
            'test/invoke' => null,
            'test/pubsub' => null,
            'test/state/concurrency' => null,
            'test/state' => null,
        ];
        $appId = new AppId('dev');

        $has_failed = false;

        foreach (array_keys($test_results) as $suite) {
            $result = $client->invokeMethod('GET', $appId, $suite);
            $body = [];
            $body = assert_equals($body, 200, $result->getStatusCode(), 'test completed successfully');
            $all_results = json_decode($result->getBody()->getContents(), true);
            foreach ($all_results as $test => $assertion) {
                if (!$has_failed && ($assertion === null || (is_string($assertion) && str_contains($assertion, '❌')))) {
                    $has_failed = true;
                }
            }
            $test_results[$suite] = [
                'status' => $body,
                'results' => $all_results,
            ];
        }

        $client->shutdown(afterRequest: false);

        while ($client->isDaprHealthy()) {
            sleep(1);
            error_log('waiting for daprd shutdown...');
        }

        return new \Nyholm\Psr7\Response($has_failed ? 500 : 200, body: json_encode($test_results));
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
    } catch (Exception) {
        $body[$message] = "✔";
    }

    return $body;
}
