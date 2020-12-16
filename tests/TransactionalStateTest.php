<?php

use Dapr\State\TransactionalState;
use Fixtures\TestState;

class TransactionalStateTest extends DaprTests
{
    public function testBegin()
    {
        $this->register_simple_load();
        $state = TransactionalState::begin(TestState::class, 'store');
        $this->assertSame('initial', $state->with_initial);
    }

    private function register_simple_load()
    {
        \Dapr\DaprClient::register_post(
            '/state/store/bulk',
            200,
            [
                ['key' => 'with_initial'],
                ['key' => 'without_initial'],
                ['key' => 'complex'],
            ],
            [
                'keys'        => [
                    'with_initial',
                    'without_initial',
                    'complex',
                ],
                'parallelism' => 10,
            ]
        );
    }

    public function testEmptyCommit()
    {
        $this->register_simple_load();
        $state = TransactionalState::begin(TestState::class, 'store');
        TransactionalState::commit($state);
    }

    public function testCommit()
    {
        $this->register_simple_load();
        /**
         * @var TestState $state
         */
        $state = TransactionalState::begin(TestState::class, 'store');
        $state->set_something();
        unset($state->with_initial);
        $state->complex      = new \Fixtures\TestObj();
        $state->complex->foo = "bar";
        $state->complex      = new \Fixtures\TestObj();
        $state->complex->foo = "baz";

        \Dapr\DaprClient::register_post(
            '/state/store/transaction',
            201,
            null,
            [
                'operations' => [
                    [
                        'operation' => 'upsert',
                        'request'   => [
                            'key'   => 'complex',
                            'value' => [
                                '$type' => 'Fixtures\TestObj',
                                '$obj'  => [
                                    'foo' => 'baz',
                                ],
                            ],
                        ],
                    ],
                    [
                        'operation' => 'delete',
                        'request'   => [
                            'key' => 'with_initial',
                        ],
                    ],
                    [
                        'operation' => 'upsert',
                        'request'   => [
                            'key'   => 'without_initial',
                            'value' => 'something',
                        ],
                    ],
                ],
            ],
            function ($data) {
                unset($data['metadata']);

                return $data;
            }
        );
        TransactionalState::commit($state);

        $this->expectException(\Dapr\exceptions\StateAlreadyCommitted::class);
        TransactionalState::commit($state);
    }
}
