<?php

namespace Dapr\Deserialization;

use Dapr\DaprLogger;
use Dapr\Deserialization\Attributes\ArrayOf;
use Dapr\Deserialization\Attributes\AsClass;
use Dapr\Deserialization\Attributes\Union;
use Dapr\Deserialization\Deserializers\IDeserialize;
use Dapr\exceptions\DaprException;
use Exception;
use JetBrains\PhpStorm\Pure;
use LogicException;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;
use ReflectionType;

class Deserializer implements IDeserializer
{
    public function __construct(protected DeserializationConfig $config, protected DaprLogger $logger)
    {
    }

    /**
     * @inheritDoc
     */
    #[Pure] public function is_exception(mixed $item): bool
    {
        return is_array($item) && isset($item['errorCode'], $item['message']);
    }

    /**
     * @inheritDoc
     */
    public function get_exception(array $array): Exception
    {
        return DaprException::deserialize_from_array($array);
    }

    /**
     * @inheritDoc
     */
    public function detect_from_parameter(ReflectionParameter $parameter, mixed $data): mixed
    {
        if ($array_of = $this->is_array_of($parameter)) {
            return $this->from_array_of($array_of, $data);
        }

        if ($class_name = $this->is_class($parameter)) {
            return $this->from_value($class_name, $data);
        }

        if ($union_type = $this->get_union_type($parameter, $data)) {
            return $this->from_value($union_type, $data);
        }

        $type = $this->get_type_from_type($parameter->getType());

        return $this->from_value($type, $data);
    }

    private function is_array_of(ReflectionParameter|ReflectionMethod|ReflectionProperty $reflection): string|false
    {
        $attr = $reflection->getAttributes(ArrayOf::class);

        return isset($attr[0]) ? $attr[0]->newInstance()->type : false;
    }

    /**
     * @inheritDoc
     */
    public function from_array_of(string $as, array $array): array
    {
        return array_map(fn($item) => $this->from_value($as, $item), $array);
    }

    /**
     * @inheritDoc
     */
    public function from_value(string $as, mixed $value): mixed
    {
        if ($deserializer = $this->get_deserializer($as)) {
            return $deserializer->deserialize($value);
        }

        if ( ! class_exists($as)) {
            return $value;
        }

        if ($value === null) {
            return null;
        }

        try {
            $reflection = new ReflectionClass($as);
            $obj        = $reflection->newInstanceWithoutConstructor();
        } catch (ReflectionException $exception) {
            $this->logger->warning(
                'Failure trying to deserialize to {as}: {exception}',
                ['as' => $as, 'exception' => $exception]
            );

            return $value;
        }
        foreach ($value as $prop_name => $prop_value) {
            if ($reflection->hasProperty($prop_name)) {
                $obj->$prop_name = $this->detect_from_property($reflection->getProperty($prop_name), $prop_value);
                continue;
            }
            $obj->$prop_name = $prop_value;
        }

        return $obj;
    }

    private function get_deserializer(string $type): IDeserialize|null
    {
        return (new class($this->config, $type) extends DeserializationConfig {
            public IDeserialize|null $deserializer;
            #[Pure] public function __construct(DeserializationConfig $config, $type)
            {
                $this->deserializer = $config->deserializers[$type] ?? null;
            }
        })->deserializer;
    }

    /**
     * @inheritDoc
     */
    public function detect_from_property(ReflectionProperty $property, mixed $data): mixed
    {
        if ($array_of = $this->is_array_of($property)) {
            return $this->from_array_of($array_of, $data);
        }

        if ($class_name = $this->is_class($property)) {
            return $this->from_value($class_name, $data);
        }

        if ($union_type = $this->get_union_type($property, $data)) {
            return $this->from_value($union_type, $data);
        }

        $type = $this->get_type_from_type($property->getType());

        return $this->from_value($type, $data);
    }

    private function is_class(ReflectionProperty|ReflectionMethod|ReflectionParameter $reflection): string|false
    {
        $attr = $reflection->getAttributes(AsClass::class);

        return isset($attr[0]) ? $attr[0]->newInstance()->type : false;
    }

    private function get_union_type(
        ReflectionParameter|ReflectionMethod|ReflectionProperty $reflection,
        mixed $data
    ): string|false {
        $attr = $reflection->getAttributes(Union::class);
        if (isset($attr[0])) {
            $discriminator = $attr[0]->newInstance()->discriminator;

            return $discriminator($data);
        }

        return false;
    }

    private function get_type_from_type(ReflectionType $type): string
    {
        if ($type instanceof ReflectionNamedType) {
            return $type->getName();
        }

        throw new LogicException('Type is missing or is a union type without the union attribute.');
    }

    /**
     * @inheritDoc
     */
    public function detect_from_method(ReflectionMethod $method, mixed $data): mixed
    {
        if ($array_of = $this->is_array_of($method)) {
            return $this->from_array_of($array_of, $data);
        }

        if ($class_name = $this->is_class($method)) {
            return $this->from_value($class_name, $data);
        }

        if ($union_type = $this->get_union_type($method, $data)) {
            return $this->from_value($union_type, $data);
        }

        $type = $this->get_type_from_type($method->getReturnType());

        return $this->from_value($type, $data);
    }

    /**
     * @inheritDoc
     */
    public function from_json(string $as, string $json): mixed
    {
        return $this->from_value($as, json_decode($json, true));
    }
}
