<?php
namespace Dapr\Proxies;
#[\Dapr\Actors\Attributes\DaprType('TestActor')]
class dapr_proxy_TestActor extends \Dapr\Actors\Internal\InternalProxy implements \Fixtures\ITestActor,\Dapr\Actors\IActor {
    public $id;
    use \Dapr\Actors\ActorTrait;

    public function a_function( $value): bool {
        $data = [$value];
        // inline function: get name
        $class = new \ReflectionClass($this);
        $attributes = $class->getAttributes(\Dapr\Actors\Attributes\DaprType::class);
        if (!empty($attributes)) {
            $type = $attributes[0]->newInstance()->type;
        } else {
            $type = $class->getShortName();
        }
        // end function
        $id = $this->get_id(); 
        $result = \Dapr\DaprClient::post(
            \Dapr\DaprClient::get_api("/actors/$type/$id/method/a_function"),
            \Dapr\Serialization\Serializer::as_array($data[0] ?? null)
        );
        $result->data = \Dapr\Deserialization\Deserializer::detect_from_parameter($class->getMethod('a_function'), $result->data);
        
        return $result->data;
    }
    public function get_id(): mixed {
        return $this->id;
    }
    public function remind(string $name,  $data): void {
        throw new \LogicException('Cannot call remind outside the actor.');
    }
    public function on_activation(): void {
        throw new \LogicException('Cannot call on_activation outside the actor.');
    }
    public function on_deactivation(): void {
        throw new \LogicException('Cannot call on_deactivation outside the actor.');
    }
}