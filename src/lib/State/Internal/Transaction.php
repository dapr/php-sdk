<?php

namespace Dapr\State\Internal;

use Dapr\Serializer;

class Transaction
{
    private array $transaction = [];
    public array $state;
    private int $counter = 0;
    public bool $is_closed = false;

    public function get_transaction(): array
    {
        $transaction = array_values($this->transaction);
        usort($transaction, fn($a, $b) => $a['order'] <=> $b['order']);

        return array_map(
            function ($a) {
                unset($a['order']);
                if (isset($a['request']['value'])) {
                    $a['request']['value'] = Serializer::as_json($a['request']['value']);
                }

                return $a;
            },
            $transaction
        );
    }

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
