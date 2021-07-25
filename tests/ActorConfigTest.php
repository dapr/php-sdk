<?php

use Dapr\Actors\ActorConfig;
use Dapr\Serialization\ISerializer;
use Fixtures\ITestActor;

require_once __DIR__ . '/DaprTests.php';

/**
 * Class ActorConfigTest
 */
class ActorConfigTest extends DaprTests
{
    public function testSerialization()
    {
        $serializer = $this->container->get(ISerializer::class);
        $config = new ActorConfig(
            [ITestActor::class],
            new DateInterval('PT1S'),
            new DateInterval('PT2S'),
            new DateInterval('PT3S'),
            true
        );
        $this->assertSame(
            [
                'entities' => ['TestActor'],
                'actorIdleTimeout' => '0h0m1s0us',
                'actorScanInterval' => '0h0m2s0us',
                'drainOngoingCallTimeout' => '0h0m3s0us',
                'drainRebalancedActors' => true,
            ],
            $serializer->as_array($config)
        );
    }

    public function testReentrancy()
    {
        $serializer = $this->container->get(ISerializer::class);
        $config = new ActorConfig(
            [ITestActor::class],
            reentrantConfig: new \Dapr\Actors\ReentrantConfig(12)
        );
        $this->assertSame(
            [
                'entities' => ['TestActor'],
                'reentrancy' => [
                    'enabled' => true,
                    'maxStackDepth' => 12
                ]
            ],
            $serializer->as_array($config)
        );
    }
}
