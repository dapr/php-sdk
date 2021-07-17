<?php

require_once __DIR__ . '/Fixtures/TestState.php';

use Dapr\consistency\StrongFirstWrite;
use Dapr\consistency\StrongLastWrite;
use Dapr\State\Attributes\StateStore;
use Dapr\State\IManageState;
use Dapr\State\StateItem;
use Dapr\State\StateManager;
use DI\DependencyException;
use DI\NotFoundException;
use Fixtures\TestState;
use GuzzleHttp\Psr7\Response;

class StateTest extends DaprTests
{
    /**
     * @throws DependencyException
     * @throws NotFoundException
     */
    public function testLoadObject()
    {
        $stack = $this->get_http_client_stack(
            [
                new Response(
                    200, body: json_encode(
                           [
                               ['key' => 'with_initial'],
                               ['key' => 'without_initial'],
                               ['key' => 'complex'],
                           ]
                       )
                ),
                new Response(
                    200, body: json_encode(
                           [
                               ['key' => 'ok_with_initial', 'data' => 'hello world', 'etag' => 1],
                               ['key' => 'ok_without_initial'],
                               ['key' => 'ok_complex'],
                           ]
                       )
                )
            ]
        );
        $client = $this->get_new_client_with_http($stack->client);
        $state = $this->container->get(TestState::class);
        $state_manager = new StateManager($client);
        $state_manager->load_object($state, metadata: ['test' => 'meta']);
        $this->assertSame('initial', $state->with_initial);

        $request = $stack->history[0]['request'];
        $this->assertRequestUri('/v1.0/state/store/bulk', $request);
        $this->assertRequestBody(
            json_encode(
                [
                    'keys' => ['with_initial', 'without_initial', 'complex'],
                    'parallelism' => 10,
                ]
            ),
            $request
        );
        $this->assertRequestMethod('POST', $request);
        $this->assertRequestQueryString('metadata.test=meta', $request);

        $state = $this->container->get(TestState::class);
        $state_manager->load_object($state, 'ok_');
        $this->assertSame('hello world', $state->with_initial);

        $request = $stack->history[1]['request'];
        $this->assertRequestUri('/v1.0/state/store/bulk', $request);
        $this->assertRequestQueryString('', $request);
        $this->assertRequestBody(
            json_encode(
                [
                    'keys' => ['ok_with_initial', 'ok_without_initial', 'ok_complex'],
                    'parallelism' => 10,
                ]
            ),
            $request
        );
    }

    /**
     * @throws DependencyException
     * @throws NotFoundException
     */
    public function testLoadState()
    {
        $stack = $this->get_http_client_stack(
            [
                new Response(200, headers: ['Etag' => ['abc']], body: '"data"'),
                new Response(204, body: '1')
            ]
        );
        $client = $this->get_new_client_with_http($stack->client);
        $state_manager = new StateManager($client);
        $data = $state_manager->load_state('store', 'a-key', default_value: 1, metadata: ['test' => 'meta']);
        $this->assertEquals(
            new StateItem('a-key', 'data', new StrongLastWrite(), 'abc', ['test' => 'meta']),
            $data
        );
        $request = $stack->history[0]['request'];
        $this->assertRequestQueryString('metadata.test=meta', $request);
        $this->assertRequestMethod('GET', $request);
        $this->assertRequestUri('/v1.0/state/store/a-key', $request);
        $this->assertRequestBody('', $request);

        $this->assertEquals(
            new StateItem('a-key', 1, new StrongLastWrite(), null, ['test' => 'meta']),
            $state_manager->load_state('store', 'a-key', default_value: 1, metadata: ['test' => 'meta'])
        );
        $request = $stack->history[1]['request'];
        $this->assertRequestQueryString('metadata.test=meta', $request);
        $this->assertRequestUri('/v1.0/state/store/a-key', $request);
        $this->assertRequestBody('', $request);
    }

    /**
     * @throws DependencyException
     * @throws NotFoundException
     */
    public function testSaveObject()
    {
        $state = $this->container->get(TestState::class);
        $stack = $this->get_http_client_stack(
            [
                new Response(
                    200, body: json_encode(
                           [
                               ['key' => 'with_initial', 'data' => 'initial', 'etag' => 24],
                               ['key' => 'without_initial'],
                               ['key' => 'complex'],
                           ]
                       )
                ),
                new Response(204)
            ]
        );
        $client = $this->get_new_client_with_http($stack->client);
        $state_manager = new StateManager($client);

        $state_manager->load_object($state);

        $request = $stack->history[0]['request'];
        $this->assertRequestBody(
            json_encode(
                [
                    'keys' => ['with_initial', 'without_initial', 'complex'],
                    'parallelism' => 10,
                ]
            ),
            $request
        );
        $this->assertRequestUri('/v1.0/state/store/bulk', $request);

        $state_manager->save_object($state);
        $request = $stack->history[1]['request'];
        $this->assertRequestUri('/v1.0/state/store', $request);
        $this->assertRequestBody(
            json_encode(
                [
                    [
                        'key' => 'with_initial',
                        'value' => 'initial',
                        'etag' => '24',
                        'options' => [
                            'consistency' => 'eventual',
                            'concurrency' => 'last-write',
                        ],
                    ],
                    [
                        'key' => 'without_initial',
                        'value' => null,
                    ],
                    [
                        'key' => 'complex',
                        'value' => null,
                    ],
                ]
            ),
            $request
        );
    }

    /**
     * @throws DependencyException
     * @throws NotFoundException
     */
    public function testSaveSate()
    {
        $stack = $this->get_http_client_stack(
            [
                new Response(200)
            ]
        );
        $client = $this->get_new_client_with_http($stack->client);
        $state_manager = new StateManager($client);
        $state_manager->save_state(
            'store',
            new StateItem('a-key', 'a-value', new StrongLastWrite(), '123', ['ok' => 'test'])
        );
        $request = $stack->history[0]['request'];
        $this->assertRequestUri('/v1.0/state/store', $request);
        $this->assertRequestBody(
            json_encode(
                [
                    [
                        'key' => 'a-key',
                        'value' => 'a-value',
                        'etag' => '123',
                        'options' => [
                            'consistency' => 'strong',
                            'concurrency' => 'last-write',
                        ],
                        'metadata' => [
                            'ok' => 'test',
                        ],
                    ],
                ]
            ),
            $request
        );
    }

    /**
     * @throws DependencyException
     * @throws NotFoundException
     */
    public function testNotAbleToLoadState()
    {
        $state = new class {
            public $never;
        };
        $state_manager = $this->container->get(IManageState::class);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Tried to load state without a Dapr\State\Attributes\StateStore attribute');

        $state_manager->load_object($state);
    }

    /**
     * @throws DependencyException
     * @throws NotFoundException
     */
    public function testSetToNull()
    {
        $state = new #[StateStore('store', StrongFirstWrite::class)] class {
            public $null = 1;
        };
        $stack = $this->get_http_client_stack(
            [
                new Response(
                    200, body: json_encode(
                           [
                               ['key' => 'null', 'etag' => 1, 'data' => null],
                           ]
                       )
                )
            ]
        );
        $client = $this->get_new_client_with_http($stack->client);
        $state_manager = new StateManager($client);
        $state_manager->load_object($state, parallelism: 12);

        $request = $stack->history[0]['request'];
        $this->assertRequestBody(
            json_encode(
                [
                    'keys' => ['null'],
                    'parallelism' => 12,
                ]
            ),
            $request
        );
        $this->assertRequestUri('/v1.0/state/store/bulk', $request);
    }

    public function testDeleteState()
    {
        $stack = $this->get_http_client_stack(
            [
                new Response(204)
            ]
        );
        $client = $this->get_new_client_with_http($stack->client);
        $state_manager = new StateManager($client);
        $state_manager->delete_keys('store', ['key1']);
        $request = $stack->history[0]['request'];
        $this->assertRequestUri('/v1.0/state/store/key1', $request);
        $this->assertRequestMethod('DELETE', $request);
    }
}
