<?php

namespace Dapr\Client;

use Dapr\Deserialization\IDeserializer;
use Dapr\exceptions\DaprException;
use Dapr\Serialization\ISerializer;
use GuzzleHttp\Client;
use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Class DaprHttpClient
 * @package Dapr\Client
 */
class DaprHttpClient extends DaprClient
{
    use HttpStateTrait;
    use HttpSecretsTrait;
    use HttpInvokeTrait;

    private Client $httpClient;

    public function __construct(private string $baseHttpUri, IDeserializer $deserializer, ISerializer $serializer)
    {
        parent::__construct($deserializer, $serializer);
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

    /**
     * @throws DaprException
     */
    public function invokeMethod(
        string $httpMethod,
        string $appId,
        string $methodName,
        mixed $data = null,
        array $metadata = []
    ): ResponseInterface {
        return $this->handlePromise(
            $this->invokeMethodAsync($httpMethod, $appId, $methodName, $data, $metadata)
        )->wait();
    }

    public function invokeMethodAsync(
        string $httpMethod,
        string $appId,
        string $methodName,
        mixed $data = null,
        array $metadata = []
    ): PromiseInterface {
        $options = [];
        if (!empty($data)) {
            $options['body'] = $this->serializer->as_json($data);
        }
        $options['headers'] = $metadata;
        $appId = rawurlencode($appId);
        $methodName = rawurlencode($methodName);
        return $this->handlePromise(
            $this->httpClient->requestAsync($httpMethod, "/v1.0/invoke/$appId/method/$methodName", $options)
        );
    }
}
