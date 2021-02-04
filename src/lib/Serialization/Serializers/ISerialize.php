<?php

namespace Dapr\Serialization\Serializers;

use Dapr\Serialization\ISerializer;

interface ISerialize
{
    public function serialize(mixed $value, ISerializer $serializer): mixed;
}
