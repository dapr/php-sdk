<?php

namespace Dapr;

use Dapr\exceptions\AccessDenied;
use Dapr\exceptions\NoSecretStore;

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
     */
    public static function retrieve(string $secret_store, string $name, array $parameters = [])
    {
        $result = DaprClient::get(DaprClient::get_api("/secrets/$secret_store/$name", $parameters));
        switch ($result->code) {
            case 500:
                throw new NoSecretStore("Failed to get secret or no secret stores defined");
            case 403:
                throw new AccessDenied("Access denied");
            case 400:
                throw new NoSecretStore("Secret store is missing or misconfigured");
            case 204:
            default:
                throw new NoSecretStore("Secret not found");
            case 200:
                return $result->data;
        }
    }
}
