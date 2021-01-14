<?php

namespace Dapr\State\Internal;

use Dapr\Serialization\Serializer;

/**
 * Class Transaction
 * @package Dapr\State\Internal
 */
class Transaction
{
    /**
     * @var array[] The current transactions
     */
    private array $transaction = [];

    /**
     * @var mixed[] The current state
     */
    public array $state = [];

    /**
     * @var int Consistent counter for determining order of the transaction
     */
    private int $counter = 0;

    /**
     * @var bool Whether the transaction is closed
     */
    public bool $is_closed = false;

    /**
     * Get the ordered transaction to commit
     *
     * @return array[]
     */
    public function get_transaction(): array
    {
        $transaction = array_values($this->transaction);
        usort($transaction, fn($a, $b) => $a['order'] <=> $b['order']);

        return array_map(
            function ($a) {
                unset($a['order']);
                if (isset($a['request']['value'])) {
                    $a['request']['value'] = Serializer::as_array($a['request']['value']);
                }

                return $a;
            },
            $transaction
        );
    }

    /**
     * Upsert a value in a transaction
     *
     * @param string $key
     * @param mixed $value
     */
    public function upsert(string $key, mixed $value): void
    {
        $this->state[$key]       = $value;
        $this->transaction[$key] = [
            'order'     => $this->counter++,
            'operation' => 'upsert',
            'request'   => [
                'key'   => $key,
                'value' => $value,
            ],
        ];
    }

    /**
     * Delete a value in a transaction
     *
     * @param string $key
     */
    public function delete(string $key): void
    {
        unset($this->state[$key]);
        $this->transaction[$key] = [
            'order'     => $this->counter++,
            'operation' => 'delete',
            'request'   => [
                'key' => $key,
            ],
        ];
    }
}
