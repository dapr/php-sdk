<?php

namespace Dapr\Client;

use Dapr\consistency\Consistency;

/**
 * Class StateTransactionRequest
 * @package Dapr\Client
 */
abstract class StateTransactionRequest
{
    public static function upsert(
        string $key,
        array|string|null $value,
        string $etag = '',
        array $metadata = [],
        Consistency|null $consistency = null
    ): UpsertTransactionRequest {
        return new UpsertTransactionRequest(
            key:         $key,
            value:       $value,
            etag:        $etag,
            metadata:    $metadata,
            consistency: $consistency
        );
    }

    public static function delete(
        string $key,
        string $etag = '',
        array $metadata = [],
        Consistency|null $consistency = null
    ): DeleteTransactionRequest {
        return new DeleteTransactionRequest(key: $key, etag: $etag, metadata: $metadata, consistency: $consistency);
    }
}
