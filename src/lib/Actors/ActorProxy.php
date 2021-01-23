<?php

namespace Dapr\Actors;

use Dapr\DaprClient;
use Dapr\Deserialization\Deserializer;
use Dapr\Runtime;
use Dapr\Serialization\Serializer;
use LogicException;
use ReflectionClass;
use ReflectionMethod;

/**
 * Class ActorProxy
 * @package Dapr
 */
abstract class ActorProxy
{
    public static int $mode = ProxyModes::GENERATED;

    public static function generate_proxy_class($interface)
    {
        Runtime::$logger?->info('Generating a proxy class for {i}', ['i' => $interface]);
        $reflected_interface = new ReflectionClass($interface);
        $type                = $reflected_interface->getAttributes(DaprType::class)[0]?->newInstance()->type;

        if (empty($type)) {
            Runtime::$logger?->critical('{i} is missing a DaprType attribute', ['i' => $interface]);
            throw new LogicException("$interface must have a DaprType attribute");
        }

        return self::_generate_proxy_class($reflected_interface, $interface, $type);
    }

    private static function _generate_proxy_class(
        ReflectionClass $reflected_interface,
        string $interface,
        string $type
    ): string {
        ['full' => $full_proxy_type, 'simple' => $proxy_type] = self::get_proxy_type($type);
        if (self::$mode === ProxyModes::GENERATED_CACHED) {
            $file = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$proxy_type.'.php';
            if (file_exists($file)) {
                require_once($file);
                if (class_exists($full_proxy_type)) {
                    return '';
                }
            }
        }

        $methods          = $reflected_interface->getMethods(ReflectionMethod::IS_PUBLIC);
        $rendered_methods = [];
        foreach ($methods as $method) {
            $method_name = $method->getName();
            switch ($method_name) {
                case 'get_id':
                    $rendered_methods[] = <<<METHOD
    public function get_id(): mixed {
        return \$this->id;
    }
METHOD;
                    break;
                case 'remind':
                case 'on_activation':
                case 'on_deactivation':
                    $rendered_methods[] = self::generate_failure_method($method);
                    break;
                case 'delete_timer':
                case 'create_timer':
                case 'delete_reminder':
                case 'get_reminder':
                case 'create_reminder':
                    break;
                default:
                    $rendered_methods[] = self::generate_proxy_method($method);
                    break;
            }
        }
        $rendered_methods = implode("\n", $rendered_methods);
        $class            = <<<CLASS
namespace Dapr\Proxies;
#[\Dapr\Actors\DaprType('$type')]
class $proxy_type extends \Dapr\Actors\InternalProxy implements \\$interface {
    public \$id;
    use \Dapr\Actors\Actor;

$rendered_methods
}
CLASS;
        if (self::$mode === ProxyModes::GENERATED_CACHED) {
            file_put_contents($file, "<?php\n\n".$class);
        }

        return $class;
    }

    private static function get_proxy_type(string $dapr_type)
    {
        $internal_type = preg_replace('/[^a-zA-Z0-9_]*/', '', $dapr_type);
        $proxy_type    = 'dapr_proxy_'.$internal_type;

        return ['full' => "\\Dapr\\Proxies\\$proxy_type", 'simple' => $proxy_type];
    }

    /**
     * Returns an actor proxy
     *
     * @param class-string<IActor> $interface
     * @param mixed $id The id to proxy for
     * @param string|null $override_type Allow overriding the Dapr type for a given interface
     *
     * @return object
     * @throws \ReflectionException
     */
    public static function get(string $interface, mixed $id, string|null $override_type): object
    {
        Runtime::$logger?->debug('Getting actor proxy for {i}||{id}', ['i' => $interface, 'id' => $id]);
        $reflected_interface = new ReflectionClass($interface);
        $type                = $override_type ?? ($reflected_interface->getAttributes(
                    DaprType::class
                )[0] ?? null)?->newInstance()->type;

        if (empty($type)) {
            Runtime::$logger?->critical('{i} is missing a DaprType attribute', ['i' => $interface]);
            throw new LogicException("$interface must have a DaprType attribute");
        }

        ['full' => $full_type] = self::get_proxy_type($type);
        switch (self::$mode) {
            case ProxyModes::GENERATED_CACHED:
            case ProxyModes::GENERATED:
            default:
                if ( ! class_exists($full_type)) {
                    eval(self::_generate_proxy_class($reflected_interface, $interface, $type));
                } else {
                    Runtime::$logger?->debug('Using already defined proxy class {i}', ['i' => $full_type]);
                }
                $proxy     = new $full_type();
                $proxy->id = $id;
                break;
            case ProxyModes::DYNAMIC:
                Runtime::$logger?->debug('Using InternalProxy to provide proxy');
                $proxy            = new InternalProxy();
                $proxy->DAPR_TYPE = $type;
                foreach ($reflected_interface->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                    $method_name = $method->getName();
                    switch ($method_name) {
                        case 'get_id':
                            $proxy->$method_name = function () use ($id) {
                                return $id;
                            };
                            break;
                        case 'remind':
                        case 'on_activation':
                        case 'on_deactivation':
                            $proxy->$method_name = function () use ($method_name) {
                                throw new LogicException("Cannot call $method_name from outside the actor.");
                            };
                            break;
                        case 'delete_timer':
                        case 'create_timer':
                        case 'delete_reminder':
                        case 'get_reminder':
                        case 'create_reminder':
                            break;
                        default:
                            $proxy->$method_name = function (...$params) use (
                                $type,
                                $id,
                                $method_name,
                                $reflected_interface
                            ) {
                                $result = DaprClient::post(
                                    DaprClient::get_api("/actors/$type/$id/method/$method_name"),
                                    Serializer::as_array($params)
                                );

                                $result->data = Deserializer::detect_from_parameter(
                                    $reflected_interface->getMethod($method_name),
                                    $result->data
                                );

                                return $result->data;
                            };
                            break;
                    }
                }
                break;
        }

        return $proxy;
    }

    private static function generate_failure_method(ReflectionMethod $method)
    {
        $signature = self::create_signature($method);

        return <<<METHOD
    public function $signature {
        throw new \LogicException('Cannot call {$method->getName()} outside the actor.');
    }
METHOD;
    }

    private static function generate_proxy_method(ReflectionMethod $method)
    {
        $signature = self::create_signature($method);
        $array     = self::params_to_array($method);
        $return    = $method->getReturnType();
        if ($return instanceof \ReflectionNamedType) {
            $returns = $return->getName() !== 'void';
        } else {
            $returns = true;
        }

        if ($returns) {
            $return = "return \$result->data;";
        } else {
            $return = '';
        }

        return <<<METHOD
    public function $signature {
        \$data = $array;
        // inline function: get name
        \$class = new \ReflectionClass(\$this);
        \$attributes = \$class->getAttributes(\Dapr\Actors\DaprType::class);
        if (!empty(\$attributes)) {
            \$type = \$attributes[0]->newInstance()->type;
        } else {
            \$type = \$class->getShortName();
        }
        // end function
        \$id = \$this->get_id(); 
        \$result = \Dapr\DaprClient::post(
            \Dapr\DaprClient::get_api("/actors/\$type/\$id/method/{$method->getName()}"),
            \Dapr\Serialization\Serializer::as_array(\$data)
        );
        \$result->data = \Dapr\Deserialization\Deserializer::detect_from_parameter(\$class->getMethod('{$method->getName(
        )}'), \$result->data);
        
        $return
    }
METHOD;
    }

    private static function params_to_array(ReflectionMethod $method)
    {
        $params = $method->getParameters();
        $array  = [];
        foreach ($params as $param) {
            $array[] = "'{$param->getName()}' => \${$param->getName()}";
        }

        return '['.implode(',', $array).']';
    }

    private static function render_param(\ReflectionParameter $param)
    {
        $name      = $param->getName();
        $type_name = self::render_type($param->getType());
        $default   = '';
        if ($param->isDefaultValueAvailable()) {
            $default = $param->getDefaultValue();
            if (is_string($default)) {
                $default = "'$default'";
            }
            $default = " = $default";
        }

        return "$type_name \$$name$default";
    }

    private static function render_type(\ReflectionType|null $type)
    {
        $type_name = '';
        if ($type instanceof \ReflectionNamedType) {
            $type_name = $type->isBuiltin() ? $type->getName() : '\\'.$type->getName();
            $type_name = ($type->allowsNull() ? '?' : '').$type_name;
        } elseif ($type instanceof \ReflectionUnionType) {
            $type_name = [];
            foreach ($type->getTypes() as $type) {
                $type_name[] = self::render_type($type);
            }
            $type_name = implode('|', $type_name);
        }

        return $type_name;
    }

    private static function create_signature(ReflectionMethod $method)
    {
        $params          = $method->getParameters();
        $return          = self::render_type($method->getReturnType());
        $method_name     = $method->getName();
        $rendered_params = [];
        foreach ($params as $param) {
            $rendered_params[] = self::render_param($param);
        }
        $rendered_params = implode(', ', $rendered_params);
        $return          = empty($return) ? '' : ": $return";

        return "$method_name($rendered_params)$return";
    }
}
