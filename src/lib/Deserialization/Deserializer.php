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

    public static function register(callable $deserializer, ...$types)
    {
        if (empty($types)) {
            self::$default_deserializer = $deserializer;
        } else {
            foreach ($types as $type) {
                self::$deserializers[$type] = $deserializer;
            }
        }
    }

    public static function array(string $as, array $array): array
    {
        foreach ($array as $key => &$value) {
            $value = self::item($as, $value);
        }

        return $array;
    }

    public static function is_exception(array $array): bool
    {
        return isset($array['errorCode'], $array['message']);
    }

    public static function get_exception(array $array): \Exception
    {
        return DaprException::deserialize_from_array($array);
    }

    public static function item(string $as, array|string|int|float|null $array): mixed
    {
        if (self::$default_deserializer !== null) {
            $deserializer = self::$default_deserializer;
            $deserializer($as, $array);
        }

        if(isset(self::$deserializers[$as])) {
            $deserializer = self::$deserializers[$as];
            return $deserializer($array);
        }

        if ( ! class_exists($as)) {
            throw new \InvalidArgumentException("$as does not exist!");
        }

        if($array === null) return null;

        $reflection = new \ReflectionClass($as);
        $obj        = $reflection->newInstanceWithoutConstructor();
        foreach ($array as $prop_name => $prop_value) {
            if ($reflection->hasProperty($prop_name)) {
                $reflected_property = $reflection->getProperty($prop_name);
                $attr               = $reflected_property->getAttributes(ArrayOf::class);
                if (isset($attr[0]) && is_array($prop_value)) {
                    $type            = $attr[0]->newInstance()->type;
                    $obj->$prop_name = self::array($type, $prop_value);
                    continue;
                }

                $attr = $reflected_property->getAttributes(AsClass::class);
                if (isset($attr[0])) {
                    $type            = $attr[0]->newInstance()->type;
                    $obj->$prop_name = self::item($type, $prop_value);
                    continue;
                }

                $attr = $reflected_property->getAttributes(Union::class);
                if (isset($attr[0])) {
                    $type            = $attr[0]->newInstance()->discriminator;
                    $type            = $type($prop_value);
                    $obj->$prop_name = self::item($type, $prop_value);
                }
            }
            $obj->$prop_name = $prop_value;
        }

        return $obj;
    }
}
