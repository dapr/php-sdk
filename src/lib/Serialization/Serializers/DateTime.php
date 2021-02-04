<?php

namespace Dapr\Serialization\Serializers;

use Dapr\Serialization\ISerializer;
use InvalidArgumentException;

class DateTime implements ISerialize
{
    public function serialize(mixed $value, ISerializer $serializer): string
    {
        if ($value instanceof \DateTime) {
            return $value->format(DATE_W3C);
        }
        throw new InvalidArgumentException('Date time is not a date time');
    }
}
