<?php

namespace Dapr\Deserialization\Deserializers;

class DateTime implements IDeserialize
{

    public static function deserialize(mixed $value): \DateTime
    {
        return new \DateTime($value);
    }
}
