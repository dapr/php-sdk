<?php

namespace Dapr\Client;

use Dapr\consistency\Consistency;

/**
 * Class StateTransactionRequest
 * @package Dapr\Client
 */
class StateTransactionRequest
{
    /**
     * StateTransactionRequest constructor.
     * @param string $key
     * @param string $value
     * @param 'upsert'|'delete' $operationType
     * @param string $etag
     * @param iterable<string, string> $metadata
     * @param Consistency|null $consistency
     */
    public function __construct(
        public string $key,
        public string $value,
        public string $operationType,
        public string $etag = '',
        public array $metadata = [],
        public Consistency|null $consistency = null
    ) {
    }
}
