<?php

namespace Dapr\Serialization;

interface ISerializer
{
    /**
     * Serialize a value to a json string
     *
     * @param mixed $value The value to serialize
     * @param int $flags Flags to be passed to json_serialize
     *
     * @return string The json string
     */
    public function as_json(mixed $value, int $flags = 0): string;

    /**
     * Serialize to an array/value that can be json serialized
     *
     * @param mixed $value The value to serialize
     *
     * @return mixed The resulting serialization
     */
    public function as_array(mixed $value): mixed;
}
