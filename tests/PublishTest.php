<?php

use Dapr\PubSub\Subscribe;

require_once __DIR__.'/DaprTests.php';

class PublishTest extends DaprTests
{
    public function testSimplePublish()
    {
        $publisher = $this->container->make(\Dapr\PubSub\Publish::class, ['pubsub' => 'pubsub']);
        $this->get_client()->register_post(
            '/publish/pubsub/topic',
            200,
            null,
            [
                'my' => 'event',
            ]
        );
        $publisher->topic('topic')->publish(['my' => 'event']);
    }

    public function testCloudEventPublish()
    {
        $publisher                = $this->container->make(\Dapr\PubSub\Publish::class, ['pubsub' => 'pubsub']);
        $event                    = new \Dapr\PubSub\CloudEvent();
        $event->data              = ['my' => 'event'];
        $event->type              = 'type';
        $event->subject           = 'subject';
        $event->id                = 'id';
        $event->data_content_type = 'application/json';
        $event->source            = 'source';
        $event->time              = new DateTime('2020-12-12T20:47:00+00:00Z');
        $this->get_client()->register_post(
            '/publish/pubsub/topic',
            200,
            null,
            [
                'id'              => 'id',
                'source'          => 'source',
                'specversion'     => '1.0',
                'type'            => 'type',
                'datacontenttype' => 'application/json',
                'subject'         => 'subject',
                'time'            => '2020-12-12T20:47:00+00:00Z',
                'data'            => [
                    'my' => 'event',
                ],
            ]
        );
        $publisher->topic('topic')->publish($event);
    }

    public function testParsingCloudEvent()
    {
        $eventjson = <<<JSON
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
JSON;
        $event     = \Dapr\PubSub\CloudEvent::parse($eventjson);
        $this->assertTrue($event->validate());
        $this->assertSame('https://example.com/message', $event->source);
    }

    public function testSubscibe()
    {
        $topic          = uniqid();
        $eventjson      = <<<JSON
{
    "specversion" : "1.0",
    "type" : "xml.message",
    "source" : "https://example.com/message",
    "subject" : "Test XML Message",
    "id" : "id-1234-5678-9101",
    "datacontenttype" : "application/json",
    "data" : "{\"hello\": \"world\"}}"
}
JSON;
        $event          = \Dapr\PubSub\CloudEvent::parse($eventjson);
        $called_handler = false;
        $handler        = function (\Dapr\PubSub\CloudEvent $event) use (&$called_handler, $eventjson) {
            $called_handler = true;
            $expected_event = json_decode($eventjson, true);
            $event          = json_decode($event->to_json(), true);
            ksort($event);
            ksort($expected_event);
            $this->assertSame($expected_event, $event);

            return [
                'status' => 'SUCCESS',
            ];
        };
        Subscribe::to_topic('pubsub', $topic, $handler);
        $result = Subscribe::handle_subscription($topic, $event);
        $this->assertTrue($called_handler);
        $this->assertSame(200, $result['code']);
        $this->assertSame(['status' => 'SUCCESS'], $this->deserialize($result['body']));

        $called_handler = false;
        $this->set_body($event->to_array());
        $result = \Dapr\Runtime::get_handler_for_route('PUT', '/dapr/runtime/sub/'.$topic)();
        $this->assertTrue($called_handler);
        $this->assertSame(200, $result['code']);
        $this->assertSame(['status' => 'SUCCESS'], $this->deserialize($result['body']));
    }
}
