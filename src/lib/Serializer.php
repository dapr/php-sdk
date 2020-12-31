<?php

namespace Dapr;

use Dapr\exceptions\DaprException;

abstract class Serializer
{
    private const REM_DT = ['S0F', 'M0S', 'H0M', 'DT0H', 'M0D', 'P0Y', 'Y0M', 'P0M'];
    private const CLEAN_DT = ['S', 'M', 'H', 'DT', 'M', 'P', 'Y', 'P'];
    private const DEFAULT_DT = 'PT0S';
    private static array $types = [];

    /**
     * @var callable
     */
    private static $default_serializer = null;

    public static function register(callable $serializer, ?array $types)
    {
        if ($types === null) {
            self::$default_serializer = $serializer;
        } else {
            foreach ($types as $type) {
                self::$types[$type] = $serializer;
            }
        }
    }

    public static function as_json(mixed $item): mixed
    {
        if (self::$default_serializer !== null) {
            $serializer = self::$default_serializer;

            return $serializer($item);
        }

        switch (true) {
            case is_array($item):
                foreach ($item as &$value) {
                    $value = self::as_json($value);
                }

                return $item;
            case is_object($item):
                if($item instanceof \Exception) {
                    return DaprException::serialize_to_array($item);
                }

                $type_name = get_class($item);
                if (isset(self::$types[$type_name])) {
                    $callback = self::$types[$type_name];

                    return [
                        '$type' => $type_name,
                        '$obj'  => $callback($item),
                    ];
                }

                switch ($type_name) {
                    case \DateInterval::class:
                        return [
                            '$type' => $type_name,
                            '$obj'  => self::serialize_DateInterval($item),
                        ];
                    default:
                        $type = new \ReflectionClass($type_name);
                        $obj  = [];
                        foreach ($type->getProperties() as $property) {
                            $hidden = $property->isPrivate() || $property->isProtected();
                            $key    = $property->getName();
                            if ( ! ctype_print($key)) {
                                continue;
                            }
                            $property->setAccessible(true);
                            try {
                                $obj[$key] = self::as_json($property->getValue($item));
                            } catch(\Throwable $exception) {
                                // skip the variable
                            }
                            $property->setAccessible(! $hidden);
                        }
                        foreach ((array)$item as $property => $value) {
                            if ( ! ctype_print($property)) {
                                continue;
                            }
                            if ( ! $type->hasProperty($property)) {
                                $obj[$property] = self::as_json($value);
                            }
                        }
                        $instance = [
                            '$type' => $type_name,
                            '$obj'  => $obj,
                        ];

                        return $instance;
                }
            default:
                // never happens
                return $item;
        }
    }

    private static function serialize_DateInterval(\DateInterval $interval): string
    {
        return rtrim(
            str_replace(self::REM_DT, self::CLEAN_DT, $interval->format('P%yY%mM%dDT%hH%iM%sS%fF')),
            'PT'
        ) ?: self::DEFAULT_DT;
    }
}
