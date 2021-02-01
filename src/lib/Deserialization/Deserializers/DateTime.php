<?php

namespace Dapr\Deserialization\Deserializers;

use Dapr\Deserialization\IDeserializer;

class DateTime implements IDeserialize
{
    /**
     * @param mixed $value
     * @param IDeserializer $deserializer
     *
     * @return \DateTime
     * @throws \Exception
     */
    public static function deserialize(mixed $value, IDeserializer $deserializer): \DateTime
    {
        return new \DateTime($value);
    }
}
