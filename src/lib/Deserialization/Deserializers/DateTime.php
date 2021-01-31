<?php

namespace Dapr\Deserialization\Deserializers;

use Dapr\Deserialization\IDeserializer;

class DateTime implements IDeserialize
{
    public static function deserialize(mixed $value, IDeserializer $deserializer): \DateTime
    {
        return new \DateTime($value);
    }
}
