<?php

namespace Dapr\Client;

use Dapr\Deserialization\IDeserializer;
use Dapr\Serialization\ISerializer;
use GuzzleHttp\Client;
use Psr\Log\LoggerInterface;

/**
 * Class DaprHttpClient
 * @package Dapr\Client
 */
class DaprHttpClient extends DaprClient
{
    use HttpStateTrait;
    use HttpSecretsTrait;
    use HttpInvokeTrait;
    use HttpPubSubTrait;
    use HttpBindingTrait;
    use HttpActorTrait;

    protected Client $httpClient;

    protected function getHttpClient(): Client {
        return $this->httpClient;
    }

    public function __construct(
        private string $baseHttpUri,
        IDeserializer $deserializer,
        ISerializer $serializer,
        LoggerInterface $logger
    ) {
        parent::__construct($deserializer, $serializer, $logger);
        if (str_ends_with($this->baseHttpUri, '/')) {
            $this->baseHttpUri = rtrim($this->baseHttpUri, '/');
        }
        $this->httpClient = new Client(
            [
                'base_uri' => $this->baseHttpUri,
                'allow_redirects' => false,
                'headers' => [
                    'User-Agent' => 'DaprPHPSDK/v2.0',
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json'
                ]
            ]
        );
    }

    public function isDaprHealthy(): bool
    {
        try {
            $result = $this->httpClient->get('/v1.0/healthz');
            if (204 === $result->getStatusCode()) {
                return true;
            }
            return false;
        } catch (\Throwable $exception) {
            return false;
        }
    }

    public function getMetadata(): MetadataResponse|null
    {
        try {
            $result = $this->httpClient->get('/v1.0/metadata');
            return $this->deserializer->from_json(MetadataResponse::class, $result->getBody()->getContents());
        } catch (\Throwable $exception) {
            return null;
        }
    }

    public function shutdown(bool $afterRequest = true): void
    {
        $shutdown = fn() => $this->httpClient->post('/v1.0/shutdown');
        if ($afterRequest) {
            register_shutdown_function($shutdown);
            return;
        }

        $shutdown();
    }
}
