<?php

use GuzzleHttp\Psr7\Response;

require_once __DIR__ . '/DaprTests.php';

/**
 * Class DeleteStateTest
 */
class DeleteStateTest extends DaprTests
{
    public function testDeleteStateWithoutEtag(): void
    {
        $stack = $this->get_http_client_stack([new Response(204)]);
        $client = $this->get_new_client_with_http($stack->client);

        $client->deleteState(storeName: 'store', key: 'key');

        $request = $this->get_last_request($stack);
        $this->assertRequestMethod('DELETE', $request);
        $this->assertRequestUri('/v1.0/state/store/key', $request);
        $this->assertFalse($request->hasHeader('If-Match'));
    }

    public function testTryDeleteStateWithEtag(): void
    {
        $stack = $this->get_http_client_stack([new Response(204)]);
        $client = $this->get_new_client_with_http($stack->client);

        $client->tryDeleteState(storeName: 'store', key: 'key', etag: 'abc123');

        $request = $this->get_last_request($stack);
        $this->assertRequestMethod('DELETE', $request);
        $this->assertRequestUri('/v1.0/state/store/key', $request);
        $this->assertRequestHasHeaders(['If-Match' => 'abc123'], $request);
    }
}
