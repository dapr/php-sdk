<?php

use Dapr\exceptions\DaprException;
use Dapr\SecretManager;
use DI\DependencyException;
use DI\NotFoundException;

require_once __DIR__ . '/DaprTests.php';

/**
 * Class SecretTest
 */
class SecretTest extends DaprTests
{
    /**
     * @throws DependencyException
     * @throws NotFoundException
     * @throws DaprException
     */
    public function testRetrieveSecret()
    {
        $container = $this->get_http_client_stack(
            [
                new \GuzzleHttp\Psr7\Response(200, body: json_encode(['secret' => 'my_secret']))
            ]
        );
        $client = $this->get_new_client_with_http($container->client);
        $secret = $client->getSecret('store', 'test', ['meta' => 'data']);
        $this->assertSame(['secret' => 'my_secret'], $secret);

        $request = $container->history[0]['request'];
        $this->assertRequestQueryString('metadata.meta=data', $request);
        $this->assertRequestUri('/v1.0/secrets/store/test', $request);
        $this->assertRequestMethod('GET', $request);
        $this->assertRequestBody('', $request);

        $this->get_client()->register_get(
            '/secrets/store/test',
            200,
            [
                'secret' => 'my_secret',
            ]
        );
        $secretManager = $this->container->get(SecretManager::class);
        $secret = $secretManager->retrieve('store', 'test');
        $this->assertSame(['secret' => 'my_secret'], $secret);
    }

    /**
     * @throws DaprException
     * @throws DependencyException
     * @throws NotFoundException
     */
    public function testGetAllSecrets()
    {
        $container = $this->get_http_client_stack(
            [
                new \GuzzleHttp\Psr7\Response(
                    200,
                    body: json_encode(['secret1' => ['key1' => 'value1'], 'secret2' => ['key2' => 'value2']])
                )
            ]
        );
        $client = $this->get_new_client_with_http($container->client);
        $secrets = $client->getBulkSecret('store', ['meta' => 'data']);
        $this->assertSame(['secret1' => ['key1' => 'value1'], 'secret2' => ['key2' => 'value2']], $secrets);
        $request = $container->history[0]['request'];
        $this->assertRequestQueryString('metadata.meta=data', $request);
        $this->assertRequestUri('/v1.0/secrets/store/bulk', $request);
        $this->assertRequestMethod('GET', $request);
        $this->assertRequestBody('', $request);

        $this->get_client()->register_get(
            '/secrets/store/bulk',
            200,
            ['secret1' => ['key1' => 'value1'], 'secret2' => ['key2' => 'value2']]
        );
        $secretManager = $this->container->get(SecretManager::class);
        $secrets = $secretManager->all('store');
        $this->assertSame(['secret1' => ['key1' => 'value1'], 'secret2' => ['key2' => 'value2']], $secrets);
    }

    /**
     * @throws DaprException
     * @throws DependencyException
     * @throws NotFoundException
     */
    public function testSecretNoExist()
    {
        $container = $this->get_http_client_stack(
            [
                new \GuzzleHttp\Psr7\Response(
                    204
                )
            ]
        );
        $client = $this->get_new_client_with_http($container->client);
        $secret = $client->getSecret('store', 'test', ['meta' => 'data']);
        $this->assertNull($secret);
        $request = $container->history[0]['request'];
        $this->assertRequestQueryString('metadata.meta=data', $request);
        $this->assertRequestUri('/v1.0/secrets/store/test', $request);
        $this->assertRequestMethod('GET', $request);
        $this->assertRequestBody('', $request);

        $this->get_client()->register_get('/secrets/store/test', 204, null);
        $secretManager = $this->container->get(SecretManager::class);
        $secret = $secretManager->retrieve('store', 'test');
        $this->assertSame(null, $secret);
    }
}
