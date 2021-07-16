<?php

use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

/**
 * Class HealthTest
 */
class HealthTest extends DaprTests
{
    public function testIsHealthy()
    {
        $container = $this->get_http_client_stack(
            [
                new Response(200)
            ]
        );
        $client = $this->get_new_client_with_http($container->client);
        $this->assertTrue($client->isDaprHealthy());
        $request = $container->history[0]['request'];
        $this->assertRequestUri('/v1.0/healthz', $request);
    }

    public function testIsNotHealthy() {
        $container = $this->get_http_client_stack(
            [
                new Response(500)
            ]
        );
        $client = $this->get_new_client_with_http($container->client);
        $this->assertFalse($client->isDaprHealthy());
        $request = $container->history[0]['request'];
        $this->assertRequestUri('/v1.0/healthz', $request);
    }

    public function testTimeout() {
        $container = $this->get_http_client_stack(
            [
                new RequestException('timed out', new Request('GET', 'test'))
            ]
        );
        $client = $this->get_new_client_with_http($container->client);
        $this->assertFalse($client->isDaprHealthy());
        $request = $container->history[0]['request'];
        $this->assertRequestUri('/v1.0/healthz', $request);
    }
}
