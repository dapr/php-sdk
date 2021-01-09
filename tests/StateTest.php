<?php

require_once __DIR__.'/Fixtures/TestState.php';

use Dapr\State\State;

class StateTest extends DaprTests
{
    public function testLoadState()
    {
        $state = new \Fixtures\TestState();
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
        State::load_state($state);
        $this->assertSame('initial', $state->with_initial);

        $state = new \Fixtures\TestState;
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
        State::load_state($state);
        $this->assertSame('hello world', $state->with_initial);
    }

    public function testSaveState()
    {
        $state                        = new \Fixtures\TestState();
        \Dapr\DaprClient::register_post(
            '/state/store/bulk',
            code: 200,
            response_data: [
            ['key' => 'with_initial', 'data' => 'initial', 'etag' => 24],
            ['key' => 'without_initial'],
            ['key' => 'complex'],
        ],
            expected_request: [
                'keys'        => ['with_initial', 'without_initial', 'complex'],
                'parallelism' => 10,
            ]
        );

        State::load_state($state);

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
                        'consistency' => 'eventual',
                        'concurrency' => 'last-write',
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
        State::save_state($state);
    }

    public function test_not_able_to_load_state() {
        $state = new class {
            public $never;
        };

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Tried to load state without a Dapr\State\Attributes\StateStore attribute');

        State::load_state($state);
    }
}
