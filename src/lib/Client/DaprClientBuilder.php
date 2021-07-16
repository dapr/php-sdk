<?php

namespace Dapr\Client;

use Dapr\Deserialization\DeserializationConfig;
use Dapr\Deserialization\Deserializer;
use Dapr\Serialization\SerializationConfig;
use Dapr\Serialization\Serializer;
use JetBrains\PhpStorm\Pure;
use Psr\Log\LoggerInterface;

/**
 * Class DaprClientBuilder
 * @package Dapr\Client
 */
class DaprClientBuilder
{
    public function __construct(
        private string $defaultHttpHost,
        private DeserializationConfig $deserializationConfig,
        private SerializationConfig $serializationConfig,
        private LoggerInterface $logger
    ) {
    }

    #[Pure]
    public function useHttpClient(
        string $httpHost
    ): self {
        return new self($httpHost, $this->deserializationConfig, $this->serializationConfig, $this->logger);
    }

    public function withSerializationConfig(SerializationConfig $serializationConfig): self
    {
        return new self($this->defaultHttpHost, $this->deserializationConfig, $serializationConfig, $this->logger);
    }

    public function withDeserializationConfig(DeserializationConfig $deserializationConfig): self
    {
        return new self($this->defaultHttpHost, $deserializationConfig, $this->serializationConfig, $this->logger);
    }

    public function withLogger(LoggerInterface $logger): self
    {
        return new self($this->defaultHttpHost, $this->deserializationConfig, $this->serializationConfig, $logger);
    }

    public function build(): DaprClient
    {
        return new DaprHttpClient(
            $this->defaultHttpHost,
            new Deserializer($this->deserializationConfig, $this->logger),
            new Serializer($this->serializationConfig, $this->logger),
            $this->logger
        );
    }
}
