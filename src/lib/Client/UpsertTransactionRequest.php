<?php

namespace Dapr\Client;

use Dapr\consistency\Consistency;

/**
 * Class UpsertTransactionRequest
 * @package Dapr\Client
 */
class UpsertTransactionRequest extends StateTransactionRequest {
    public string $operationType = 'upsert';

    public function __construct(
        public string $key,
        public array|string|null $value,
        public string $etag = '',
        public array $metadata = [],
        public ?Consistency $consistency = null
    ) {
    }
}
