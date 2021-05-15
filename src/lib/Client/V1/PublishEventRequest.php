<?php

namespace Dapr\Client\V1;

/**
 * Class PublishEventRequest
 * @package Dapr\Client\V1
 */
class PublishEventRequest
{
    public function __construct(
        public string $pubsub_name,
        public string $topic,
        public string $data,
        public string $data_content_type,
        public array $metadata = []
    ) {
    }
}
