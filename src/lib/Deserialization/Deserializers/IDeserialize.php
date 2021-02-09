<?php

namespace Dapr\Deserialization\Deserializers;

use Dapr\Deserialization\IDeserializer;

/**
 * Interface IDeserialize
 *
 * All deserializers should implement this interface
 *
 * @package Dapr\Deserialization\Deserializers
 */
interface IDeserialize
{
    public static function deserialize(mixed $value, IDeserializer $deserializer): mixed;
}
