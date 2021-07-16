<?php

namespace Dapr;

use Dapr\exceptions\DaprException;
use JetBrains\PhpStorm\Deprecated;
use Psr\Log\LoggerInterface;

/**
 * Enables reading Dapr secrets
 * @see https://v1-rc1.docs.dapr.io/reference/api/secrets_api/
 * @package Dapr
 */
#[Deprecated(since: '1.2.0')]
class SecretManager
{
    public function __construct(protected DaprClient $client, protected LoggerInterface $logger)
    {
    }

    /**
     * Get all defined secrets in a secret store
     *
     * @param string $secret_store The secret store name
     *
     * @return array The secrets
     * @throws DaprException
     */
    public function all(string $secret_store): array
    {
        $this->logger->debug('Retrieving all secrets from {secret_store}', ['secret_store' => $secret_store]);
        $result = $this->client->get("/secrets/$secret_store/bulk");
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
    private function handle_response_code(int $code): void
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
     * Retrieve a secret from the store.
     *
     * @param string $secret_store The store to get the secret from.
     * @param string $name The name of the secret to get.
     * @param array $parameters Optional parameters for the secret store
     *
     * @return array
     * @throws DaprException
     */
    public function retrieve(string $secret_store, string $name, array $parameters = []): ?array
    {
        $this->logger->debug(
            'Retrieving secret {name} from {secret_store}',
            ['name' => $name, 'secret_store' => $secret_store]
        );
        $result = $this->client->get("/secrets/$secret_store/$name", $parameters);
        $this->handle_response_code($result->code);

        return $result->data;
    }
}
