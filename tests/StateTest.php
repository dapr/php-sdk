<?php

require_once __DIR__.'/Fixtures/TestState.php';

use Dapr\State\State;

class StateTest extends DaprTests
{
    public function testRetrievingSingleState()
    {
        \Dapr\DaprClient::register_get(
            '/state/store/key',
            200,
            [
                'location' => 42,
            ]
        );
        $state = State::get_single('store', 'key');
        $this->assertSame(42, $state->key['location']);

        \Dapr\DaprClient::register_post(
            '/state/store',
            204,
            null,
            [
                [
                    'key'   => 'key',
                    'value' => ['location' => 42],
                ],
            ]
        );
        $state->save_state();

        \Dapr\DaprClient::register_post(
            '/state/store/bulk',
            200,
            [
                [
                    'key'  => 'key',
                    'data' => 42,
                    'etag' => 1,
                ],
            ],
            [
                'keys'        => ['key'],
                'parallelism' => 10,
            ]
        );
        $state->load();
    }

    public function testSingleGetNoState()
    {
        \Dapr\DaprClient::register_get('/state/store/nope', 204, null);
        $state = State::get_single('store', 'nope');
        $this->assertEmpty($state->nope);
    }

    public function testLoadState()
    {
        $state = new \Fixtures\TestState('store');
        \Dapr\DaprClient::register_post(
            '/state/store/bulk',
            200,
            [
                ['key' => 'with_initial'],
                ['key' => 'without_initial'],
                ['key' => 'complex'],
            ],
            expected_request: [
                'keys'        => ['with_initial', 'without_initial', 'complex'],
                'parallelism' => 10,
            ]
        );
        $state->load();
        $this->assertSame('initial', $state->with_initial);

        $state = new \Fixtures\TestState('store', new \Dapr\consistency\StrongFirstWrite());
        \Dapr\DaprClient::register_post(
            '/state/store/bulk',
            code: 200,
            response_data: [
            ['key' => 'with_initial', 'data' => 'hello world', 'etag' => 1],
            ['key' => 'without_initial'],
            ['key' => 'complex'],
        ],
            expected_request: [
                'keys'        => ['with_initial', 'without_initial', 'complex'],
                'parallelism' => 10,
            ]
        );
        $state->load();
        $this->assertSame('hello world', $state->with_initial);
        $this->assertSame('strong', $state->with_initial__options['consistency']);
    }

    public function testSaveState()
    {
        $state                        = new \Fixtures\TestState('store', new \Dapr\consistency\StrongFirstWrite());
        $state->with_initial__etag    = 24;
        $state->with_initial__options = [
            'consistency' => 'strong',
            'concurrency' => 'first-write',
        ];

        \Dapr\DaprClient::register_post(
            '/state/store',
            204,
            null,
            [
                [
                    'key'     => 'with_initial',
                    'value'   => 'initial',
                    'etag'    => 24,
                    'options' => [
                        'consistency' => 'strong',
                        'concurrency' => 'first-write',
                    ],
                ],
                [
                    'key'   => 'without_initial',
                    'value' => null,
                ],
                [
                    'key'   => 'complex',
                    'value' => null,
                ],
            ]
        );
        $state->save_state();
    }
}
