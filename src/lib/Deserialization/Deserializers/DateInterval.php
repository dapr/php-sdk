<?php

namespace Dapr\Deserialization\Deserializers;

use Dapr\Deserialization\IDeserializer;

class DateInterval implements IDeserialize
{

    public static function deserialize(mixed $value, IDeserializer $deserializer): \DateInterval
    {
        return new \DateInterval($value);
    }
}
