<?php

use Dapr\exceptions\DaprException;
use Dapr\exceptions\StateAlreadyCommitted;
use DI\DependencyException;
use DI\NotFoundException;
use Fixtures\TestObj;
use Fixtures\TestState;

class TransactionalStateTest extends DaprTests
{
    /**
     * @throws DependencyException
     * @throws NotFoundException
     */
    public function testBegin()
    {
        $this->register_simple_load();
        $state = $this->container->make(TestState::class);
        $state->begin();
        $this->assertSame('initial', $state->with_initial);
    }

    /**
     * @throws DependencyException
     * @throws NotFoundException
     */
    private function register_simple_load()
    {
        $client = $this->get_client();
        $client->register_post(
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

    /**
     * @throws DependencyException
     * @throws NotFoundException
     * @throws StateAlreadyCommitted
     * @throws DaprException
     */
    public function testEmptyCommit()
    {
        $this->register_simple_load();
        $state = $this->container->get(TestState::class);
        $state->begin();
        $state->commit();
    }

    /**
     * @throws DependencyException
     * @throws NotFoundException
     */
    public function testInvalidKey()
    {
        $this->register_simple_load();
        $state = $this->container->get(TestState::class);
        $state->begin();

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
        $this->register_simple_load();
        $state = $this->container->get(TestState::class);
        $state->begin();

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
        $this->get_client()->register_post(
            '/state/store/bulk',
            200,
            [
                ['key' => 'with_initial'],
                ['key' => 'without_initial', 'data' => 1, 'etag' => 1],
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

        $state = $this->container->get(TestState::class);
        $state->begin();
        $state->set_something();
        unset($state->with_initial);
        $state->complex      = new TestObj();
        $state->complex->foo = "bar";
        $state->complex      = new TestObj();
        $state->complex->foo = "baz";

        $this->get_client()->register_post(
            '/state/store/transaction',
            201,
            null,
            [
                'operations' => [
                    [
                        'operation' => 'upsert',
                        'request'   => [
                            'key'     => 'without_initial',
                            'value'   => 'something',
                            'etag'    => '1',
                            'options' => [
                                'consistency' => 'eventual',
                                'concurrency' => 'last-write',
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
                            'key'   => 'complex',
                            'value' => [
                                'foo' => 'baz',
                            ],
                        ],
                    ],
                ],
                'metadata'   => [
                    'test' => true,
                ],
            ]
        );
        $state->commit(['test' => true]);

        $this->expectException(StateAlreadyCommitted::class);
        $state->commit();
    }
}
