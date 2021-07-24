<?php

namespace Dapr\Deserialization;

use Exception;
use Nette\PhpGenerator\Method;
use ReflectionMethod;
use ReflectionParameter;
use ReflectionProperty;

/**
 * Interface IDeserializer
 *
 * Provides a simple deserialization interface
 *
 * @package Dapr\Deserialization
 */
interface IDeserializer
{
    /**
     * Deserialize from an array of arrays into an array of objects
     *
     * @param string $as The type to deserialize to
     * @param array $array The array of items
     *
     * @return array The deserialized array
     */
    public function from_array_of(string $as, array $array): array;

    /**
     * Determine if a given item is a serialized exception
     *
     * @param mixed $item The item to check
     *
     * @return bool whether the item is an exception
     */
    public function is_exception(mixed $item): bool;

    /**
     * Deserialize a serialized exception
     *
     * @param array $array The array to deserialize
     *
     * @return Exception The deserialized exception
     */
    public function get_exception(array $array): Exception;

    /**
     * Given a parameter, detects the type to deserialize to
     *
     * @param ReflectionParameter $parameter The parameter to detect the type of
     * @param mixed $data The data to deserialize
     *
     * @return mixed The deserialized data
     */
    public function detect_from_parameter(ReflectionParameter $parameter, mixed $data): mixed;

    /**
     * Given a property, detects the type to deserialize to
     *
     * @param ReflectionProperty $property The property to detect the type of
     * @param mixed $data The data to deserialize
     *
     * @return mixed The deserialized data
     */
    public function detect_from_property(ReflectionProperty $property, mixed $data): mixed;

    /**
     * Return the detected type from the reflected property
     *
     * @param ReflectionProperty $property
     * @return string
     */
    public function detect_type_name_from_property(ReflectionProperty $property): string;

    /**
     * Deserializes based on a method return type (such as when returning from an actor).
     *
     * @param ReflectionMethod $method The method to detect the type of
     * @param mixed $data The data to deserialize
     *
     * @return mixed The deserialized
     */
    public function detect_from_method(ReflectionMethod $method, mixed $data): mixed;

    /**
     * Detect the deserialization method from introspecting the method
     *
     * @param Method $method The method
     * @param mixed $data The data to deserialize
     *
     * @return mixed The deserialized data
     */
    public function detect_from_generator_method(Method $method, mixed $data): mixed;

    /**
     * Deserializes a json string into a type
     *
     * @param string $as The type to deserialize to
     * @param string $json The json string to deserialize
     *
     * @return mixed The deserialized value
     */
    public function from_json(string $as, string $json): mixed;

    /**
     * Deserializes a value into another value.
     *
     * @param string $as The type to deserialize to
     * @param mixed $value The value to deserialize
     *
     * @return mixed The deserialized value
     */
    public function from_value(string $as, mixed $value): mixed;
}
