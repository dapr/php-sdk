<?php

namespace Dapr;

abstract class Deserializer
{
    private static array $types = [];

    /**
     * @var callable
     */
    private static $default_deserializer = null;

    public static function register(callable $deserializer, ?array $types)
    {
        if ($types === null) {
            self::$default_deserializer = $deserializer;
        } else {
            foreach ($types as $type) {
                self::$types[$type] = $deserializer;
            }
        }
    }

    public static function maybe_deserialize(mixed $obj): mixed
    {
        if (self::$default_deserializer !== null) {
            $deserializer = self::$default_deserializer;

            return $deserializer($obj);
        }

        switch (true) {
            case is_array($obj) && isset($obj['$type']) && isset($obj['$obj']):

                if (isset(self::$types[$obj['$type']])) {
                    $deserializer = self::$types[$obj['$type']];

                    return $deserializer($obj['$obj']);
                }

                switch ($obj['$type']) {
                    case 'DateInterval':
                        return self::deserialize_DateInterval($obj);
                    default:
                        if ( ! class_exists($obj['$type'])) {
                            throw new \LogicException('Unknown type: '.$obj['$type']);
                        }
                        $type     = new \ReflectionClass($obj['$type']);
                        $instance = $type->newInstanceWithoutConstructor();
                        foreach ($obj['$obj'] as $property => $item) {
                            $item = self::maybe_deserialize($item);
                            if ($type->hasProperty($property)) {
                                $type->getProperty($property)->setValue($instance, $item);
                            } else {
                                $instance->$property = $item;
                            }
                        }

                        return $instance;
                }
            case is_array($obj):
                foreach ($obj as &$value) {
                    $value = self::maybe_deserialize($value);
                }

                return $obj;
            default:
                return $obj;
        }
    }

    private static function deserialize_DateInterval(array $obj): \DateInterval
    {
        return new \DateInterval($obj['$obj']);
    }
}
