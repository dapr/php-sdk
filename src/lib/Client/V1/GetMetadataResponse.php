<?php

namespace Dapr\Client\V1;

/**
 * Class GetMetadataResponse
 * @package Dapr\Client\V1
 */
class GetMetadataResponse
{
    public function __construct(
        public string $id,
        public array $active_actors_count,
        public array $registered_components,
        public array $metadata
    ) {
    }
}
