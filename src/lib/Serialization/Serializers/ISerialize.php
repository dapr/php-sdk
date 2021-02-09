<?php

namespace Dapr\Serialization\Serializers;

use Dapr\Serialization\ISerializer;

/**
 * Interface ISerialize
 * @package Dapr\Serialization\Serializers
 */
interface ISerialize
{
    public function serialize(mixed $value, ISerializer $serializer): mixed;
}
