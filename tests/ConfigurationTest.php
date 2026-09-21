<?php

use Dapr\Configuration\ConfigurationItem;
use Dapr\Configuration\ConfigurationUpdate;
use GuzzleHttp\Psr7\Response;

require_once __DIR__ . '/DaprTests.php';

/**
 * Class ConfigurationTest
 */
class ConfigurationTest extends DaprTests
{
    public function testGetConfiguration(): void
    {
        $stack = $this->get_http_client_stack(
            [
                new Response(
                    200,
                    body: json_encode(
                        [
                            'orderId1' => ['value' => '101', 'version' => 'v1', 'metadata' => null],
                            'orderId2' => ['value' => '102', 'version' => '', 'metadata' => ['env' => 'test']],
                        ]
                    )
                ),
            ]
        );
        $client = $this->get_new_client_with_http($stack->client);

        /** @var ConfigurationItem[] $result */
        $result = $client->getConfiguration('configstore', ['orderId1', 'orderId2']);

        $request = $this->get_last_request($stack);
        $this->assertRequestMethod('GET', $request);
        $this->assertRequestUri('/v1.0/configuration/configstore', $request);
        $this->assertRequestQueryString('key=orderId1&key=orderId2', $request);

        $this->assertCount(2, $result);
        $this->assertInstanceOf(ConfigurationItem::class, $result['orderId1']);
        $this->assertSame('101', $result['orderId1']->value);
        $this->assertSame('v1', $result['orderId1']->version);
        $this->assertNull($result['orderId1']->metadata);
        $this->assertSame('102', $result['orderId2']->value);
        $this->assertSame(['env' => 'test'], $result['orderId2']->metadata);
    }

    public function testGetConfigurationAllKeys(): void
    {
        $stack = $this->get_http_client_stack([new Response(200, body: '{}')]);
        $client = $this->get_new_client_with_http($stack->client);

        $result = $client->getConfiguration('configstore');

        $request = $this->get_last_request($stack);
        $this->assertRequestUri('/v1.0/configuration/configstore', $request);
        $this->assertRequestQueryString('', $request);
        $this->assertSame([], $result);
    }

    public function testGetConfigurationWithMetadata(): void
    {
        $stack = $this->get_http_client_stack([new Response(200, body: '{}')]);
        $client = $this->get_new_client_with_http($stack->client);

        $client->getConfiguration('configstore', ['orderId1'], ['ttl' => '10']);

        $request = $this->get_last_request($stack);
        $this->assertRequestQueryString('key=orderId1&metadata.ttl=10', $request);
    }

    public function testGetConfigurationSparseItem(): void
    {
        $stack = $this->get_http_client_stack(
            [new Response(200, body: json_encode(['orderId1' => ['value' => '101']]))]
        );
        $client = $this->get_new_client_with_http($stack->client);

        $result = $client->getConfiguration('configstore', ['orderId1']);

        $this->assertSame('101', $result['orderId1']->value);
        $this->assertSame('', $result['orderId1']->version);
        $this->assertNull($result['orderId1']->metadata);
    }

    public function testSubscribeConfiguration(): void
    {
        $stack = $this->get_http_client_stack([new Response(200, body: json_encode(['id' => 'sub-id']))]);
        $client = $this->get_new_client_with_http($stack->client);

        $id = $client->subscribeConfiguration('configstore', ['orderId1']);

        $request = $this->get_last_request($stack);
        $this->assertRequestMethod('GET', $request);
        $this->assertRequestUri('/v1.0/configuration/configstore/subscribe', $request);
        $this->assertRequestQueryString('key=orderId1', $request);
        $this->assertSame('sub-id', $id);
    }

    public function testUnsubscribeConfiguration(): void
    {
        $stack = $this->get_http_client_stack([new Response(200, body: json_encode(['ok' => true]))]);
        $client = $this->get_new_client_with_http($stack->client);

        $ok = $client->unsubscribeConfiguration('configstore', 'sub-id');

        $request = $this->get_last_request($stack);
        $this->assertRequestMethod('GET', $request);
        $this->assertRequestUri('/v1.0/configuration/configstore/sub-id/unsubscribe', $request);
        $this->assertTrue($ok);
    }

    public function testUnsubscribeConfigurationFailure(): void
    {
        $stack = $this->get_http_client_stack(
            [new Response(200, body: json_encode(['ok' => false, 'message' => 'subscription not found']))]
        );
        $client = $this->get_new_client_with_http($stack->client);

        $this->assertFalse($client->unsubscribeConfiguration('configstore', 'nope'));
    }

    public function testConfigurationUpdateParse(): void
    {
        $update = ConfigurationUpdate::parse(
            json_encode(
                [
                    'id' => 'sub-id',
                    'items' => [
                        'orderId1' => ['value' => '103', 'version' => 'v2', 'metadata' => null],
                        'orderId2' => ['value' => '104'],
                    ],
                ]
            )
        );

        $this->assertSame('sub-id', $update->id);
        $this->assertCount(2, $update->items);
        $this->assertInstanceOf(ConfigurationItem::class, $update->items['orderId1']);
        $this->assertSame('103', $update->items['orderId1']->value);
        $this->assertSame('v2', $update->items['orderId1']->version);
        $this->assertSame('104', $update->items['orderId2']->value);
        $this->assertSame('', $update->items['orderId2']->version);
        $this->assertNull($update->items['orderId2']->metadata);
    }
}
