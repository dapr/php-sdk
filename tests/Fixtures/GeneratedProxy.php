<?php

/**
 * This file was automatically generated.
 */

namespace Dapr\Proxies;
use Dapr\Actors\ActorTrait;
use Dapr\Actors\Attributes\DaprType;
use Dapr\Actors\IActor;
use Swytch\Actors\Devices\IDeviceActor;

#[DaprType('TestActor')]
class dapr_proxy_TestActor  implements \Fixtures\ITestActor,IActor
{
	use ActorTrait;

	public string $id;

    public function a_function($value): bool
    {
        global $dapr_container;
        $data = [$value];
        // inline function: get name
        $class      = new \ReflectionClass($this);
        $attributes = $class->getAttributes(\Dapr\Actors\Attributes\DaprType::class);
        if ( ! empty($attributes)) {
            $type = $attributes[0]->newInstance()->type;
        } else {
            $type = $class->getShortName();
        }
        // end function
        $id           = $this->get_id();
        $serializer   = $dapr_container->get(\Dapr\Serialization\Serializer::class);
        $deserializer = $dapr_container->get(\Dapr\Deserialization\Deserializer::class);
        $client       = $dapr_container->get(\Dapr\DaprClient::class);
        $result       = $client->post(
            $client->get_api_path("/actors/$type/$id/method/a_function"),
            $serializer->as_array($data[0] ?? null)
        );
        $result->data = $deserializer->detect_from_method(
            $class->getMethod('a_function'),
            $result->data
        );

        return $result->data;
    }

    public function get_id(): mixed
    {
        return $this->id;
    }

    public function remind(string $name, $data): void
    {
        throw new \LogicException('Cannot call remind outside the actor.');
    }

    public function on_activation(): void
    {
        throw new \LogicException('Cannot call on_activation outside the actor.');
    }

    public function on_deactivation(): void
    {
        throw new \LogicException('Cannot call on_deactivation outside the actor.');
    }
}
