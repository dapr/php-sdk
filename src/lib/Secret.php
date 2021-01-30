<?php

namespace Dapr;

use Dapr\exceptions\DaprException;

/**
 * Enables reading Dapr secrets
 * @see https://v1-rc1.docs.dapr.io/reference/api/secrets_api/
 * @package Dapr
 */
abstract class Secret
{
    /**
     * Retrieve a secret from the store.
     *
     * @param string $secret_store The store to get the secret from.
     * @param string $name The name of the secret to get.
     * @param array $parameters Optional parameters for the secret store
     *
     * @return array
     * @throws DaprException
     */
    public static function retrieve(string $secret_store, string $name, array $parameters = [])
    {
        global $dapr_container;
        Runtime::$logger?->debug(
            'Retrieving secret {name} from {secret_store}',
            ['name' => $name, 'secret_store' => $secret_store]
        );
        $client = $dapr_container->get(DaprClient::class);
        $result = $client->get($client->get_api_path("/secrets/$secret_store/$name", $parameters));
        self::handle_response_code($result->code);

        return $result->data;
    }

    /**
     * Throw exceptions on errors
     *
     * @param int $code
     *
     * @throws DaprException
     */
    private static function handle_response_code(int $code)
    {
        switch ($code) {
            case 200:
            case 204:
                return;
            case 400:
                throw new DaprException('Secret store missing or not configured');
            case 403:
                throw new DaprException('Access denied');
            case 500:
                throw new DaprException('Failed to get secret or no secret store defined');
        }
    }

    /**
     * Get all defined secrets in a secret store
     *
     * @param string $secret_store The secret store name
     *
     * @return array The secrets
     * @throws DaprException
     */
    public static function all(string $secret_store)
    {
        Runtime::$logger?->debug('Retrieving all secrets from {secret_store}', ['secret_store' => $secret_store]);
        $result = DaprClient::get(DaprClient::get_api("/secrets/$secret_store/bulk"));
        self::handle_response_code($result->code);

        return $result->data;
    }
}
