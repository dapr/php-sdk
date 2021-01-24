<?php

namespace Dapr\Deserialization;

require_once __DIR__.'/default_deserializers.php';

use Dapr\Deserialization\Attributes\ArrayOf;
use Dapr\Deserialization\Attributes\AsClass;
use Dapr\Deserialization\Attributes\Union;
use Dapr\exceptions\DaprException;

final class Deserializer
{
    /**
     * @var null|callable
     */
    private static $default_deserializer = null;

    private static array $deserializers = [];

    public static function register(callable $deserializer, string ...$types):void
    {
        if (empty($types)) {
            self::$default_deserializer = $deserializer;
        } else {
            foreach ($types as $type) {
                self::$deserializers[$type] = $deserializer;
            }
        }
    }

    public static function from_array_of(string $as, array $array): array
    {
        foreach ($array as $key => &$value) {
            $value = self::from_array($as, $value);
        }

        return $array;
    }

    public static function is_exception(mixed $array): bool
    {
        if (is_array($array)) {
            return isset($array['errorCode'], $array['message']);
        }

        return false;
    }

    public static function get_exception(array $array): \Exception
    {
        return DaprException::deserialize_from_array($array);
    }

    public static function detect_from_parameter(
        \ReflectionParameter|\ReflectionProperty|\ReflectionMethod $parameter,
        mixed $data
    ): mixed {
        // type is declared via attributes
        $attr = $parameter->getAttributes(ArrayOf::class);
        if ( ! empty($attr)) {
            return self::from_array_of($attr[0]->newInstance()->type, $data);
        }

        $attr = $parameter->getAttributes(AsClass::class);
        if ( ! empty($attr)) {
            return self::from_array($attr[0]->newInstance()->type, $data);
        }

        $attr = $parameter->getAttributes(Union::class);
        if ( ! empty($attr)) {
            $discriminator = $attr[0]->newInstance()->discriminator;
            $type          = $discriminator($data);

            return self::from_array($type, $data);
        }

        // type is embedded in parameter
        if ($parameter instanceof \ReflectionMethod) {
            $type = $parameter->getReturnType();
        } else {
            $type = $parameter->getType();
        }
        if ($type instanceof \ReflectionNamedType) {
            $type_name = $type->getName();

            return self::from_array($type_name, $data);
        } elseif ($type instanceof \ReflectionUnionType) {
            throw new \LogicException(
                'Union types must have a \Dapr\Deserialization\Attributes\Union attribute on '.$parameter->getDeclaringClass(
                ).'::'.$parameter->getDeclaringFunction()
            );
        }

        return $data;
    }

    public static function from_json(string $as, string $json): mixed
    {
        return self::from_array($as, json_decode($json, true));
    }

    public static function from_array(string $as, mixed $array): mixed
    {
        if (self::$default_deserializer !== null) {
            $deserializer = self::$default_deserializer;
            $deserializer($as, $array);
        }

        if (isset(self::$deserializers[$as])) {
            $deserializer = self::$deserializers[$as];

            return $deserializer($array);
        }

        if ( ! class_exists($as)) {
            return $array;
        }

        if ($array === null) {
            return null;
        }

        $reflection = new \ReflectionClass($as);
        $obj        = $reflection->newInstanceWithoutConstructor();
        foreach ($array as $prop_name => $prop_value) {
            if ($reflection->hasProperty($prop_name)) {
                $obj->$prop_name = self::detect_from_parameter($reflection->getProperty($prop_name), $prop_value);
                continue;
            }
            $obj->$prop_name = $prop_value;
        }

        return $obj;
    }
}
