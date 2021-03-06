<?php

require_once __DIR__.'/Fixtures/TestState.php';

use Dapr\consistency\StrongFirstWrite;
use Dapr\consistency\StrongLastWrite;
use Dapr\State\Attributes\StateStore;
use Dapr\State\IManageState;
use Dapr\State\StateItem;
use DI\DependencyException;
use DI\NotFoundException;
use Fixtures\TestState;

class StateTest extends DaprTests
{
    /**
     * @throws DependencyException
     * @throws NotFoundException
     */
    public function testLoadObject()
    {
        $state  = $this->container->get(TestState::class);
        $client = $this->get_client();
        $client->register_post(
            '/state/store/bulk?test=meta',
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
        $state_manager = $this->container->get(IManageState::class);
        $state_manager->load_object($state, metadata: ['test' => 'meta']);
        $this->assertSame('initial', $state->with_initial);

        $state = $this->container->get(TestState::class);
        $client->register_post(
            '/state/store/bulk',
            code: 200,
            response_data: [
            ['key' => 'ok_with_initial', 'data' => 'hello world', 'etag' => 1],
            ['key' => 'ok_without_initial'],
            ['key' => 'ok_complex'],
        ],
            expected_request: [
                'keys'        => ['ok_with_initial', 'ok_without_initial', 'ok_complex'],
                'parallelism' => 10,
            ]
        );
        $state_manager->load_object($state, 'ok_');
        $this->assertSame('hello world', $state->with_initial);
    }

    /**
     * @throws DependencyException
     * @throws NotFoundException
     */
    public function testLoadState()
    {
        $client        = $this->get_client();
        $state_manager = $this->container->get(IManageState::class);
        $client->register_get('/state/store/a-key?test=meta', 200, 'data');
        $this->assertEquals(
            new StateItem('a-key', 'data', new StrongLastWrite(), null, []),
            $state_manager->load_state('store', 'a-key', default_value: 1, metadata: ['test' => 'meta'])
        );
        $client->register_get('/state/store/a-key?test=meta', 204, '');
        $this->assertEquals(
            new StateItem('a-key', 1, new StrongLastWrite(), null, []),
            $state_manager->load_state('store', 'a-key', default_value: 1, metadata: ['test' => 'meta'])
        );
    }

    /**
     * @throws DependencyException
     * @throws NotFoundException
     */
    public function testSaveObject()
    {
        $state         = $this->container->get(TestState::class);
        $client        = $this->get_client();
        $state_manager = $this->container->get(IManageState::class);
        $client->register_post(
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

        $state_manager->load_object($state);

        $client->register_post(
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
        $state_manager->save_object($state);
    }

    /**
     * @throws DependencyException
     * @throws NotFoundException
     */
    public function testSaveSate()
    {
        $client        = $this->get_client();
        $state_manager = $this->container->get(IManageState::class);
        $client->register_post(
            '/state/store',
            200,
            null,
            [
                [
                    'key'      => 'a-key',
                    'value'    => 'a-value',
                    'etag'     => '123',
                    'options'  => [
                        'consistency' => 'strong',
                        'concurrency' => 'last-write',
                    ],
                    'metadata' => [
                        'ok' => 'test',
                    ],
                ],
            ]
        );
        $state_manager->save_state(
            'store',
            new StateItem('a-key', 'a-value', new StrongLastWrite(), '123', ['ok' => 'test'])
        );
    }

    /**
     * @throws DependencyException
     * @throws NotFoundException
     */
    public function testNotAbleToLoadState()
    {
        $state         = new class {
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
        $state         = new #[StateStore('store', StrongFirstWrite::class)] class {
            public $null = 1;
        };
        $client        = $this->get_client();
        $state_manager = $this->container->get(IManageState::class);

        $client->register_post(
            '/state/store/bulk',
            200,
            [
                ['key' => 'null', 'etag' => 1, 'data' => null],
            ],
            [
                'keys'        => ['null'],
                'parallelism' => 12,
            ]
        );

        $state_manager->load_object($state, parallelism: 12);

        $this->assertNull($state->null);
    }

    public function testDeleteState()
    {
        $client        = $this->get_client();
        $state_manager = $this->container->get(IManageState::class);
        $client->register_delete('/state/store/key1', 204);
        $state_manager->delete_keys('store', ['key1']);
    }
}
