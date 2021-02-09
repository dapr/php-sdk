<?php

namespace Dapr\Deserialization\Deserializers;

use Dapr\Deserialization\IDeserializer;

/**
 * Class DateTime
 * @package Dapr\Deserialization\Deserializers
 */
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
