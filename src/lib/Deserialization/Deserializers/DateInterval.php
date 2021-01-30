<?php

namespace Dapr\Deserialization\Deserializers;

class DateInterval implements IDeserialize
{

    public static function deserialize(mixed $value): \DateInterval
    {
        return new \DateInterval($value);
    }
}
