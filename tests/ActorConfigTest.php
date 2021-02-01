<?php

require_once __DIR__.'/DaprTests.php';

class ActorConfigTest extends DaprTests
{
    public function testSerialization()
    {
        $serializer = $this->container->get(\Dapr\Serialization\ISerializer::class);
        $config     = new \Dapr\Actors\ActorConfig(
            ['test' => \Fixtures\ITestActor::class],
            new DateInterval('PT1S'),
            new DateInterval('PT2S'),
            new DateInterval('PT3S'),
            true
        );
        $this->assertSame(
            [
                'entities'                => ['test'],
                'actorIdleTimeout'        => '0h0m1s0us',
                'actorScanInterval'       => '0h0m2s0us',
                'drainOngoingCallTimeout' => '0h0m3s0us',
                'drainRebalancedActors'   => true,
            ],
            $serializer->as_array($config)
        );
    }
}
