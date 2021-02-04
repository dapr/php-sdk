<?php

namespace Dapr\Deserialization\Deserializers;

use Dapr\Deserialization\IDeserializer;

interface IDeserialize
{
    public static function deserialize(mixed $value, IDeserializer $deserializer): mixed;
}
