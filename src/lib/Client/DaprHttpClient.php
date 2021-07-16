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

    private Client $httpClient;

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
}
