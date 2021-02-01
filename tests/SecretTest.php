<?php

use Dapr\exceptions\DaprException;
use Dapr\SecretManager;
use DI\DependencyException;
use DI\NotFoundException;

require_once __DIR__.'/DaprTests.php';

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
        $this->get_client()->register_get(
            '/secrets/store/test',
            200,
            [
                'secret' => 'my_secret',
            ]
        );
        $secretManager = $this->container->get(SecretManager::class);
        $secret        = $secretManager->retrieve('store', 'test');
        $this->assertSame(['secret' => 'my_secret'], $secret);
    }

    /**
     * @throws DaprException
     * @throws DependencyException
     * @throws NotFoundException
     */
    public function testGetAllSecrets()
    {
        $this->get_client()->register_get(
            '/secrets/store/bulk',
            200,
            ['secret1' => ['key1' => 'value1'], 'secret2' => ['key2' => 'value2']]
        );
        $secretManager = $this->container->get(SecretManager::class);
        $secrets       = $secretManager->all('store');
        $this->assertSame(['secret1' => ['key1' => 'value1'], 'secret2' => ['key2' => 'value2']], $secrets);
    }

    /**
     * @throws DaprException
     * @throws DependencyException
     * @throws NotFoundException
     */
    public function testSecretNoExist()
    {
        $this->get_client()->register_get('/secrets/store/test', 204, null);
        $secretManager = $this->container->get(SecretManager::class);
        $secret = $secretManager->retrieve('store', 'test');
        $this->assertSame(null, $secret);
    }
}
