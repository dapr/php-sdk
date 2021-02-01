<?php

namespace Dapr\Serialization;

use Dapr\exceptions\DaprException;
use Dapr\Serialization\Attributes\AlwaysObject;
use Dapr\Serialization\Serializers\ISerialize;
use Exception;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use ReflectionException;
use stdClass;

class Serializer implements ISerializer
{
    public function __construct(protected SerializationConfig $config, protected LoggerInterface $logger)
    {
    }

    /**
     * @inheritDoc
     */
    public function as_json(mixed $value, int $flags = 0): string
    {
        return json_encode($this->as_array($value), $flags);
    }

    /**
     * @inheritDoc
     */
    public function as_array(mixed $value): mixed
    {
        switch (true) {
            case is_array($value):
                return array_map([$this, 'as_array'], $value);
            case is_object($value):
                if ($value instanceof Exception) {
                    return DaprException::serialize_to_array($value);
                }

                $type_name = get_class($value);
                if ($serializer = $this->get_serializer($type_name)) {
                    return $serializer->serialize($value, $this);
                }
                if($value instanceof ISerialize) {
                    return $value->serialize($value, $this);
                }

                $obj = [];
                if (class_exists($type_name)) {
                    try {
                        $reflection_class = new ReflectionClass($type_name);
                    } catch (ReflectionException) {
                    }
                }
                foreach ($value as $prop_name => $prop_value) {
                    if (is_array($prop_value)
                        && empty($prop_value)
                        && isset($reflection_class)
                        && $reflection_class->hasProperty($prop_name)) {
                        $attrs = $reflection_class->getProperty($prop_name)->getAttributes(AlwaysObject::class);
                        if (isset($attrs[0])) {
                            $obj[$prop_name] = new stdClass();
                        } else {
                            $obj[$prop_name] = [];
                        }
                    } else {
                        $obj[$prop_name] = $this->as_array($prop_value);
                    }
                }
                if (empty($obj) && isset($reflection_class)) {
                    $attrs = $reflection_class->getAttributes(AlwaysObject::class);
                    if (isset($attrs[0])) {
                        $obj = new stdClass();
                    }
                }

                return $obj;
            default:
                if ($serializer = $this->get_serializer(gettype($value))) {
                    return $serializer->serialize($value, $this);
                }

                return $value;
        }
    }

    private function get_serializer(string $type): ISerialize|null
    {
        return (new class($this->config, $type) extends SerializationConfig {
            public ISerialize|null $serializer;

            public function __construct(SerializationConfig $config, string $type)
            {
                $this->serializer = $config->serializers[$type] ?? null;
            }
        })->serializer;
    }
}
