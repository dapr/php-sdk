<?php

namespace Dapr\Client;

use Dapr\consistency\Consistency;

/**
 * Class DeleteTransactionRequest
 * @package Dapr\Client
 */
class DeleteTransactionRequest extends StateTransactionRequest
{
    public string $operationType = 'delete';

    public function __construct(
        public string $key,
        public string $etag = '',
        public array $metadata = [],
        public ?Consistency $consistency = null
    ) {
    }
}
