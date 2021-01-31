<?php

namespace Dapr\Serialization\Serializers;

use Dapr\Serialization\ISerializer;

class ActorConfig implements ISerialize {

    public function serialize(mixed $value, ISerializer $serializer): mixed
    {
        if($value instanceof \Dapr\Actors\ActorConfig) {
            $return = [
                'entities' => $value->get_supported_actors(),
            ];
            if($a = $value->get_idle_timeout()) {
                $return['actorIdleTimeout'] = $serializer->as_array($a);
            }
            if($a = $value->get_scan_interval()) {
                $return['actorScanInterval'] = $serializer->as_array($a);
            }
            if($a = $value->get_drain_timeout()) {
                $return['drainOngoingCallTimeout'] = $serializer->as_array($a);
            }
            if($a = $value->drain_enabled()) {
                $return['drainRebalancedActors'] = $a;
            }
            return $return;
        }
        return null;
    }
}
