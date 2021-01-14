<?php

namespace Dapr\Serialization\Serializers;

interface ISerialize
{
    public static function serialize(mixed $value): mixed;
}
