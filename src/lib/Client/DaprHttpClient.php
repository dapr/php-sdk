<?php

namespace Dapr\Client;

use Dapr\Deserialization\IDeserializer;
use Dapr\Middleware\Defaults\ActorToken;
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
    use HttpTokenTrait;

    protected Client $httpClient;

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
        $options = [
            'base_uri' => $this->baseHttpUri,
            'allow_redirects' => false,
            'headers' => [
                'User-Agent' => 'dapr-sdk-php/v1.2 http/1',
                'Accept' => 'application/json',
                'Content-Type' => 'application/json'
            ]
        ];

        if ($this->getDaprToken() !== null) {
            $options['headers']['dapr-api-token'] = $this->getDaprToken();
        }

        if (!empty(ActorToken::$token)) {
            $options['headers']['Dapr-Reentrancy-Id'] = &ActorToken::$token;
        }

        $this->httpClient = new Client($options);
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

    protected function getHttpClient(): Client
    {
        return $this->httpClient;
    }
}
