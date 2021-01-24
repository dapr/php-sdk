<?php

namespace Dapr\Serialization;

require_once __DIR__.'/default_serializers.php';

use Dapr\exceptions\DaprException;
use Dapr\Runtime;
use Dapr\Serialization\Attributes\AlwaysObject;

final class Serializer
{
    /**
     * @var null|callable
     */
    private static $default_serializer = null;

    private static array $serializers = [];

    public static function register(callable $serializer, string ...$types): void
    {
        if (empty($types)) {
            Runtime::$logger?->debug('Set default serializer');
            self::$default_serializer = $serializer;
        } else {
            foreach ($types as $type) {
                Runtime::$logger?->debug('Registered serializer for {type}', ['type' => $type]);
                self::$serializers[$type] = $serializer;
            }
        }
    }

    public static function as_json(mixed $value, int $flags = 0): string
    {
        return json_encode(self::as_array($value), $flags);
    }

    public static function as_array(mixed $value): mixed
    {
        if (self::$default_serializer !== null) {
            $serializer = self::$default_serializer;

            return $serializer($value);
        }

        switch (true) {
            case is_array($value):
                foreach ($value as $key => &$item) {
                    $item = self::as_array($item);
                }

                return $value;
            case is_object($value):
                if ($value instanceof \Exception) {
                    return DaprException::serialize_to_array($value);
                }

                $type_name = get_class($value);
                if (isset(self::$serializers[$type_name])) {
                    $callback = self::$serializers[$type_name];

                    return $callback($value);
                }

                $obj = [];
                if (class_exists($type_name)) {
                    $reflection_class = new \ReflectionClass($type_name);
                }
                foreach ($value as $prop => $prop_value) {
                    if (is_array($prop_value)
                        && empty($prop_value)
                        && isset($reflection_class)
                        && $reflection_class->hasProperty($prop)) {
                        $attrs = $reflection_class->getProperty($prop)->getAttributes(AlwaysObject::class);
                        if (isset($attrs[0])) {
                            $obj[$prop] = new \stdClass();
                        } else {
                            $obj[$prop] = [];
                        }
                    } else {
                        $obj[$prop] = self::as_array($prop_value);
                    }
                }

                if (empty($obj) && isset($reflection_class)) {
                    $attrs = $reflection_class->getAttributes(AlwaysObject::class);
                    if (isset($attrs[0])) {
                        $obj = new \stdClass();
                    }
                }

                return $obj;
            default:
                return $value;
        }
    }
}
