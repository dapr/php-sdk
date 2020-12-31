<?php

namespace Dapr;

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
        return $result->data;
    }
}
