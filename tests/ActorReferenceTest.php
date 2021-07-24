<?php

use Dapr\Actors\ActorAddress;
use Dapr\Actors\ActorReference;
use Dapr\Actors\Generators\ProxyFactory;
use Dapr\Deserialization\IDeserializer;
use Dapr\Serialization\ISerializer;
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
        $reference->bind('no_exist_interface', new ProxyFactory(ProxyFactory::GENERATED, $this->get_new_client()));
    }

    public function testExtractActorFromDynamicProxy()
    {
        $factory   = new ProxyFactory(ProxyFactory::DYNAMIC, $this->get_new_client());
        $actor     = $factory->get_generator(ITestActor::class, 'TestActor')->get_proxy('id');
        $reference = ActorReference::get($actor);
        $this->assertEquals(
            new ActorReference('id', 'TestActor'),
            $reference
        );
    }

    public function testExtractActorFromGeneratedProxy()
    {
        $factory   = new ProxyFactory(ProxyFactory::GENERATED, $this->get_new_client());
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
            new ProxyFactory(ProxyFactory::GENERATED, $this->get_new_client())
        );
        $new_reference = ActorReference::get($actor);
        $this->assertEquals($reference, $new_reference);
    }

    public function testGetters()
    {
        $reference = new ActorReference('id', 'TestActor');
        $this->assertSame('id', $reference->get_actor_id());
        $this->assertSame('TestActor', $reference->get_actor_type());
    }

    public function testSerialization()
    {
        $reference  = new ActorReference('id', 'TestActor');
        $serializer = $this->container->get(ISerializer::class);
        $this->assertSame(
            json_encode(['ActorId' => 'id', 'ActorType' => 'TestActor']),
            $serializer->as_json($reference)
        );
    }

    public function testDeserialization()
    {
        $reference    = new ActorReference('id', 'TestActor');
        $deserializer = $this->container->get(IDeserializer::class);
        $this->assertEquals(
            $reference,
            $deserializer->from_json(
                ActorReference::class,
                json_encode(['ActorId' => 'id', 'ActorType' => 'TestActor'])
            )
        );
    }
}
