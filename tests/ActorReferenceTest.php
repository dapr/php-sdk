<?php

use Dapr\Actors\ActorAddress;
use Dapr\Actors\ActorReference;
use Dapr\Actors\Generators\ProxyFactory;
use Fixtures\ITestActor;

/**
 * Class ActorReferenceTest
 */
class ActorReferenceTest extends DaprTests
{
    private ProxyFactory $proxy_factory;

    public function testInvalidInterface()
    {
        $this->expectException(ReflectionException::class);
        $reference = new ActorReference('id', 'type');
        $reference->bind('no_exist_interface', new ProxyFactory($this->container, ProxyFactory::GENERATED));
    }

    public function testExtractActorFromDynamicProxy()
    {
        $factory   = new ProxyFactory($this->container, ProxyFactory::DYNAMIC);
        $actor     = $factory->get_generator(ITestActor::class, 'TestActor')->get_proxy('id');
        $reference = ActorReference::get($actor);
        $this->assertEquals(
            new ActorReference('id', 'TestActor'),
            $reference
        );
    }

    public function testExtractActorFromGeneratedProxy()
    {
        $factory   = new ProxyFactory($this->container, ProxyFactory::GENERATED);
        $actor     = $factory->get_generator(ITestActor::class, 'TestActor')->get_proxy('id');
        $reference = ActorReference::get($actor);
        $this->assertEquals(
            new ActorReference('id', 'TestActor'),
            $reference
        );
    }

    public function testBind()
    {
        $reference     = new ActorReference('id', 'TestActor');
        $actor         = $reference->bind(
            ITestActor::class,
            new ProxyFactory($this->container, ProxyFactory::GENERATED)
        );
        $new_reference = ActorReference::get($actor);
        $this->assertEquals($reference, $new_reference);
    }
}
