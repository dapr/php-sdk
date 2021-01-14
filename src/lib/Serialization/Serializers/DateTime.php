<?php

namespace Dapr\Serialization\Serializers;

class DateTime implements ISerialize
{
    public static function serialize(mixed $value): mixed
    {
        if ($value instanceof \DateTime) {
            return $value->format(DATE_W3C);
        }
        throw new \InvalidArgumentException('Date time is not a date time');
    }
}
