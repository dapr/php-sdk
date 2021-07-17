<?php

use Dapr\exceptions\DaprException;
use Dapr\exceptions\StateAlreadyCommitted;
use DI\DependencyException;
use DI\NotFoundException;
use Fixtures\TestObj;
use Fixtures\TestState;
use GuzzleHttp\Psr7\Response;

require_once __DIR__ . '/DaprTests.php';
require_once __DIR__ . '/Fixtures/TestState.php';
require_once __DIR__ . '/Fixtures/TestObj.php';

class TransactionalStateTest extends DaprTests
{
    /**
     * @throws DependencyException
     * @throws NotFoundException
     */
    public function testBegin()
    {
        $stack = $this->setupInitialLoad();
        $state = $this->container->make(TestState::class);
        $state->begin();
        $this->assertSame('initial', $state->with_initial);
        $this->assertInitialLoad($stack);
    }

    private function setupInitialLoad(array $initial = []): \Dapr\Mocks\MockedHttpClientContainer
    {
        $stack = $this->get_http_client_stack(
            empty($initial) ?
                [
                    new Response(
                        200, body: json_encode(
                               [
                                   ['key' => 'with_initial'],
                                   ['key' => 'without_initial'],
                                   ['key' => 'complex'],
                               ]
                           )
                    )
                ] :
                $initial
        );
        $client = $this->get_new_client_with_http($stack->client);
        $this->container->set(\Dapr\Client\DaprClient::class, $client);
        return $stack;
    }

    private function assertInitialLoad(\Dapr\Mocks\MockedHttpClientContainer $stack): void
    {
        $request = $stack->history[0]['request'];
        $this->assertRequestUri('/v1.0/state/store/bulk', $request);
        $this->assertRequestBody(
            json_encode(
                [
                    'keys' => [
                        'with_initial',
                        'without_initial',
                        'complex',
                    ],
                    'parallelism' => 10,
                ]
            ),
            $request
        );
    }

    /**
     * @throws DependencyException
     * @throws NotFoundException
     * @throws StateAlreadyCommitted
     * @throws DaprException
     */
    public function testEmptyCommit()
    {
        $stack = $this->setupInitialLoad();
        $state = $this->container->get(TestState::class);
        $state->begin();
        $state->commit();
        $this->assertInitialLoad($stack);
    }

    /**
     * @throws DependencyException
     * @throws NotFoundException
     */
    public function testInvalidKey()
    {
        $stack = $this->setupInitialLoad([]);
        $state = $this->container->get(TestState::class);
        $state->begin();
        $this->assertInitialLoad($stack);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('not_exist on Fixtures\TestState is not defined and thus will not be stored.');

        $state->not_exist = true;
    }

    /**
     * @throws DependencyException
     * @throws NotFoundException
     */
    public function testIsSet()
    {
        $stack = $this->setupInitialLoad([]);
        $state = $this->container->get(TestState::class);
        $state->begin();

        $this->assertInitialLoad($stack);

        $this->assertFalse(isset($state->complex));
        $this->assertTrue(isset($state->with_initial));

        $state->complex = new TestObj();
        $this->assertTrue(isset($state->complex));
    }

    /**
     * @throws DaprException
     * @throws DependencyException
     * @throws NotFoundException
     * @throws StateAlreadyCommitted
     */
    public function testCommit()
    {
        $stack = $this->setupInitialLoad(
            [
                new Response(
                    200, body: json_encode(
                           [
                               ['key' => 'with_initial'],
                               ['key' => 'without_initial', 'data' => 1, 'etag' => '1'],
                               ['key' => 'complex'],
                           ]
                       )
                ),
                new Response(201)
            ]
        );

        $state = $this->container->get(TestState::class);
        $state->begin();

        $request = $stack->history[0]['request'];
        $this->assertRequestUri('/v1.0/state/store/bulk', $request);
        $this->assertRequestBody(
            json_encode(
                [
                    'keys' => [
                        'with_initial',
                        'without_initial',
                        'complex',
                    ],
                    'parallelism' => 10,
                ]
            ),
            $request
        );

        $state->set_something();
        unset($state->with_initial);
        $state->complex = new TestObj();
        $state->complex->foo = "bar";
        $state->complex = new TestObj();
        $state->complex->foo = "baz";

        $state->commit(['test' => true]);
        $request = $stack->history[1]['request'];
        $this->assertRequestUri('/v1.0/state/store/transaction', $request);
        $this->assertRequestBody(
            json_encode(
                [
                    'operations' => [
                        [
                            'operation' => 'upsert',
                            'request' => [
                                'key' => 'without_initial',
                                'value' => 'something',
                                'etag' => '1',
                                'options' => [
                                    'consistency' => 'eventual',
                                    'concurrency' => 'last-write',
                                ],
                            ],
                        ],
                        [
                            'operation' => 'delete',
                            'request' => [
                                'key' => 'with_initial',
                            ],
                        ],
                        [
                            'operation' => 'upsert',
                            'request' => [
                                'key' => 'complex',
                                'value' => [
                                    'foo' => 'baz',
                                ],
                            ],
                        ],
                    ],
                    'metadata' => [
                        'test' => true,
                    ],
                ]
            ),
            $request
        );

        $this->expectException(StateAlreadyCommitted::class);
        $state->commit();
    }

    /**
     * @throws DependencyException
     * @throws NotFoundException
     */
    private function register_simple_load()
    {
        return;
        $client = $this->get_client();
        $client->register_post(
            '/state/store/bulk',
            200,
            response_data: [
                ['key' => 'with_initial'],
                ['key' => 'without_initial'],
                ['key' => 'complex'],
            ],
            expected_request: [
                'keys' => [
                    'with_initial',
                    'without_initial',
                    'complex',
                ],
                'parallelism' => 10,
            ]
        );
    }
}
