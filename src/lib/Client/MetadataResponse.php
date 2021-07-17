<?php

namespace Dapr\Client;

use Dapr\Deserialization\Attributes\ArrayOf;

/**
 * Class MetadataResponse
 * @package Dapr\Client
 */
class MetadataResponse
{
    /**
     * MetadataResponse constructor.
     * @param string $id
     * @param RegisteredActor[] $actors
     * @param array $extended
     * @param RegisteredComponent[] $components
     */
    public function __construct(
        public string $id,
        #[ArrayOf(RegisteredActor::class)]
        public array $actors,
        public array $extended,
        #[ArrayOf(RegisteredComponent::class)]
        public array $components
    ) {
    }
}

/**
 * Class RegisteredActor
 * @package Dapr\Client
 */
class RegisteredActor
{
    public function __construct(public string $type, public int $count)
    {
    }
}

class RegisteredComponent
{
    public function __construct(public string $name, public string $type, public string $version)
    {
    }
}
