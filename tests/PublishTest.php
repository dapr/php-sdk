<?php

use Dapr\PubSub\CloudEvent;
use Dapr\PubSub\Publish;
use Dapr\PubSub\Topic;
use DI\DependencyException;
use DI\NotFoundException;

require_once __DIR__ . '/DaprTests.php';

/**
 * Class PublishTest
 */
class PublishTest extends DaprTests
{
    /**
     * @throws DependencyException
     * @throws NotFoundException
     */
    public function testSimplePublish()
    {
        $container = $this->get_http_client_stack(
            [
                new \GuzzleHttp\Psr7\Response(204)
            ]
        );
        $client = $this->get_new_client_with_http($container->client);
        $topic = new Topic('pubsub', 'topic', $client);
        $topic->publish(['my' => 'event'], ['test' => 'meta']);

        $request = $container->history[0]['request'];
        $this->assertRequestMethod('POST', $request);
        $this->assertRequestUri('/v1.0/publish/pubsub/topic', $request);
        $this->assertRequestQueryString('metadata.test=meta', $request);
        $this->assertRequestHasHeaders(['Content-Type' => 'application/json'], $request);
        $this->assertRequestBody(json_encode(['my' => 'event']), $request);
    }

    public function testBinaryPublish()
    {
        $container = $this->get_http_client_stack(
            [
                new \GuzzleHttp\Psr7\Response(204),
            ]
        );
        $client = $this->get_new_client_with_http($container->client);
        $topic = new Topic('pubsub', 'test', $client);
        $topic->publish('data', content_type: 'application/octet-stream');

        $request = $container->history[0]['request'];
        $this->assertRequestMethod('POST', $request);
        $this->assertRequestUri('/v1.0/publish/pubsub/test', $request);
        $this->assertRequestQueryString('', $request);
        $this->assertRequestHasHeaders(['Content-Type' => 'application/octet-stream'], $request);
        $this->assertRequestBody('"data"', $request);
    }

    /**
     * @throws DependencyException
     * @throws NotFoundException
     */
    public function testCloudEventPublish()
    {
        $container = $this->get_http_client_stack([new \GuzzleHttp\Psr7\Response(204)]);
        $client = $this->get_new_client_with_http($container->client);

        $event = new CloudEvent();
        $event->data = ['my' => 'event'];
        $event->type = 'type';
        $event->subject = 'subject';
        $event->id = 'id';
        $event->data_content_type = 'application/json';
        $event->source = 'source';
        $event->time = new DateTime('2020-12-12T20:47:00+00:00Z');

        $topic = new Topic('pubsub', 'test', $client);
        $topic->publish($event);

        $publisher = $this->container->make(Publish::class, ['pubsub' => 'pubsub']);
        $this->get_client()->register_post(
            '/publish/pubsub/topic',
            200,
            null,
            [
                'id' => 'id',
                'source' => 'source',
                'specversion' => '1.0',
                'type' => 'type',
                'datacontenttype' => 'application/json',
                'subject' => 'subject',
                'time' => '2020-12-12T20:47:00+00:00Z',
                'data' => [
                    'my' => 'event',
                ],
            ]
        );
        $publisher->topic('topic')->publish($event);

        $request = $container->history[0]['request'];
        $this->assertRequestMethod('POST', $request);
        $this->assertRequestUri('/v1.0/publish/pubsub/test', $request);
        $this->assertRequestQueryString('', $request);
        $this->assertRequestHasHeaders(['Content-Type' => 'application/cloudevents+json'], $request);
        $this->assertRequestBody(
            '{"id":"id","source":"source","specversion":"1.0","type":"type","datacontenttype":"application\/json","subject":"subject","time":"2020-12-12T20:47:00+00:00Z","data":{"my":"event"}}',
            $request
        );
    }

    /**
     * @throws Exception
     */
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
        $event = CloudEvent::parse($eventjson);
        $this->assertTrue($event->validate());
        $this->assertSame('https://example.com/message', $event->source);
    }

    public function testParsingBinaryEvent()
    {
        $eventjson = <<<JSON
{
    "specversion" : "1.0",
    "type" : "xml.message",
    "source" : "https://example.com/message",
    "subject" : "Test binary Message",
    "id" : "id-1234-5678-9101",
    "time" : "2020-09-23T06:23:21Z",
    "datacontenttype" : "application/octet-stream",
    "data" : "ZGF0YQ==",
    "data_base64": "ZGF0YQ=="
}
JSON;
        $event = CloudEvent::parse($eventjson);
        $this->assertTrue($event->validate());
        $this->assertSame('data', $event->data);
    }
}
