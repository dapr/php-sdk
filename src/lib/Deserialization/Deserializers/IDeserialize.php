<?php

namespace Dapr\Deserialization\Deserializers;

interface IDeserialize {
    public static function deserialize(mixed $value): mixed;
}
