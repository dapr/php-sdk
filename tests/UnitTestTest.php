<?php

require_once __DIR__.'/DaprTests.php';

use Fixtures\TestActorState;

class UnitTestTest extends DaprTests
{
    public function testActors()
    {
        $state = new class extends TestActorState {
            use \Dapr\Mocks\TestActorState;

            public string $value = '123';
        };
        $this->assertSame('123', $state->value);
        $actor = new \Fixtures\ActorClass('123', $state);
        $actor->a_function('abc');
        $this->assertSame('abc', $state->value);
        $this->assertSame(
            [
                [
                    [
                        'operation' => 'upsert',
                        'request'   => [
                            'key'   => 'value',
                            'value' => 'abc',
                        ],
                    ],
                ],
            ],
            $state->helper_get_transaction()
        );
    }

    public function testActorStateDelete()
    {
        $state = new class extends TestActorState {
            use \Dapr\Mocks\TestActorState;
        };
        $this->assertTrue(isset($state->value));
        unset($state->value);
        $this->assertFalse(isset($state->value));
        $this->assertSame(
            [
                [
                    [
                        'operation' => 'delete',
                        'request'   => [
                            'key' => 'value',
                        ],
                    ],
                ],
            ],
            $state->helper_get_transaction()
        );
        $state->roll_back();
        $this->assertSame([[]], $state->helper_get_transaction());
    }

    public function testActorTimer()
    {
        $state = new class extends TestActorState {
            use \Dapr\Mocks\TestActorState;
        };
        $actor = new class('id', $state) extends \Fixtures\ActorClass {
            use \Dapr\Mocks\TestActor;
        };
        $this->assertSame('id', $actor->get_id());
        $this->assertTrue(
            $actor->create_timer(
                new \Dapr\Actors\Timer('test', new DateInterval('PT1S'), new DateInterval('PT1S'), 'test')
            )
        );
        $this->assertInstanceOf(\Dapr\Actors\Timer::class, $actor->helper_get_timer('test'));
        $this->assertTrue($actor->delete_timer('test'));
        $this->assertNull($actor->helper_get_timer('test'));
    }

    public function testActorReminder()
    {
        $state = new class extends TestActorState {
            use \Dapr\Mocks\TestActorState;
        };
        $actor = new class('id', $state) extends \Fixtures\ActorClass {
            use \Dapr\Mocks\TestActor;
        };
        $this->assertTrue($actor->create_reminder(new \Dapr\Actors\Reminder('test', new DateInterval('PT1S'), [])));
        $this->assertInstanceOf(\Dapr\Actors\Reminder::class, $actor->get_reminder('test'));
        $this->assertTrue($actor->delete_reminder('test'));
        $this->assertNull($actor->get_reminder('test'));
    }

    public function testTransactionalState()
    {
        $state = new class extends \Fixtures\TestState {
            use \Dapr\Mocks\TestTransactionalState;
        };
        $state->begin();
        $state->without_initial = 'test';
        $this->assertSame('test', $state->without_initial);
        $state->commit();
        $state->begin();
        unset($state->with_initial);
        $this->assertFalse(isset($state->with_initial));
        $state->commit();
        $this->assertSame(
            [
                [
                    [
                        'operation' => 'upsert',
                        'request'   => [
                            'key'   => 'without_initial',
                            'value' => 'test',
                        ],
                    ],
                ],
                [
                    [
                        'operation' => 'delete',
                        'request'   => [
                            'key' => 'with_initial',
                        ],
                    ],
                ],
            ],
            $state->helper_get_transactions()
        );
    }
}
