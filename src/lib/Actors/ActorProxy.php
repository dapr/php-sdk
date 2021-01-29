<?php

namespace Dapr\Actors;

use Dapr\Actors\Attributes\DaprType;
use Dapr\Actors\Internal\InternalProxy;
use Dapr\DaprClient;
use Dapr\Deserialization\Deserializer;
use Dapr\Runtime;
use Dapr\Serialization\Serializer;
use LogicException;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\Method;
use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\PhpNamespace;
use Nette\PhpGenerator\Type;
use ReflectionClass;
use ReflectionMethod;

/**
 * Class ActorProxy
 * @package Dapr
 */
abstract class ActorProxy
{
    public static int $mode = ProxyModes::GENERATED;

    public static function generate_proxy_class($interface): string
    {
        Runtime::$logger?->info('Generating a proxy class for {i}', ['i' => $interface]);
        $reflected_interface = new ReflectionClass($interface);
        $type                = $reflected_interface->getAttributes(DaprType::class)[0]?->newInstance()->type;

        if (empty($type)) {
            Runtime::$logger?->critical('{i} is missing a DaprType attribute', ['i' => $interface]);
            throw new LogicException("$interface must have a DaprType attribute");
        }

        $file = new PhpFile();
        $file->addComment("This file was automatically generated.");
        $file->addNamespace(self::_generate_proxy_class($reflected_interface, $interface, $type));

        return $file;
    }

    private static function _generate_proxy_class(
        ReflectionClass $reflected_interface,
        string $interface,
        string $type
    ): PhpNamespace|string {
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

        $original_interface = $interface;
        $interface          = ClassType::from($interface);
        $interface->addImplement($original_interface);
        $interface->addProperty('id')->setPublic()->setType(Type::STRING);
        $interface->setClass();
        $interface->setName($proxy_type);

        if ( ! $reflected_interface->isSubclassOf(IActor::class)) {
            $interface->addImplement(IActor::class);
        }

        $interface->addTrait(ActorTrait::class);

        $get_id = new Method('get_id');
        $get_id->setReturnType(Type::STRING);
        $get_id->setPublic();
        $get_id->addBody('return $this->id;');
        $interface->addMember($get_id);
        $usings = [];

        $methods = array_merge($interface->getMethods(), ClassType::from(IActor::class)->getMethods());
        foreach ($methods as $method) {
            switch ($method->getName()) {
                case 'remind':
                case 'on_activation':
                case 'on_deactivation':
                    $interface->addMember(self::generate_failure_method($method));
                    break;
                case 'create_reminder':
                case 'get_reminder':
                case 'delete_reminder':
                case 'create_timer':
                case 'delete_timer':
                    break;
                case 'get_id':
                    $get_id = new Method('get_id');
                    $get_id->setReturnType(Type::STRING);
                    $get_id->setPublic();
                    $get_id->addBody('return $this->id;');
                    $interface->addMember($get_id);
                    break;
                default:
                    $interface->removeMethod($method->getName());
                    $interface->addMember(self::generate_proxy_method($method, $type, $usings));
                    break;
            }
        }

        $php_file = new PhpFile();
        $php_file->addComment('This file was automatically generated.');
        $namespace = $php_file->addNamespace("Dapr\\Proxies");
        $namespace->add($interface);
        $namespace->addUse('\Swytch\Actors\Devices\IDeviceActor');
        $namespace->addUse('\Dapr\Actors\IActor');
        $namespace->addUse('\Dapr\Actors\Attributes\DaprType');
        $namespace->addUse('\Dapr\Actors\ActorTrait');
        foreach ($usings as $using) {
            if (class_exists($using)) {
                $namespace->addUse($using);
            }
        }

        if (self::$mode === ProxyModes::GENERATED_CACHED) {
            file_put_contents($file, $php_file);
        }

        return $namespace;
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
    public static function get(string $interface, mixed $id, string|null $override_type = null): object
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
                $methods          = $reflected_interface->getMethods(ReflectionMethod::IS_PUBLIC);
                if ( ! $reflected_interface->isSubclassOf(IActor::class)) {
                    $methods = array_merge(
                        $methods,
                        (new ReflectionClass(IActor::class))->getMethods(ReflectionMethod::IS_PUBLIC)
                    );
                }
                foreach ($methods as $method) {
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
                                if ( ! empty($params)) {
                                    $params = Serializer::as_array($params[0]);
                                }

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

    private static function generate_failure_method(Method $method): Method
    {
        $method->addBody('throw new \LogicException("Cannot call ? outside the actor");', [$method->getName()]);
        $method->setPublic();

        return $method;
    }

    private static function get_types(string|null $type): array
    {
        if ($type === null) {
            return [Type::VOID];
        }

        return explode('|', $type);
    }

    private static function generate_proxy_method(Method $method, string $dapr_type, array &$usings): Method
    {
        $params = array_values($method->getParameters());
        $method->setPublic();
        if ( ! empty($params)) {
            if (isset($params[1])) {
                throw new LogicException(
                    "Cannot have more than one parameter on a method.\nMethod: {$method->getName()}"
                );
            }
            if ($params[0]->isReference()) {
                throw new LogicException(
                    "Cannot pass references between actors/methods.\nMethod: {$method->getName()}"
                );
            }
            $usings = array_merge($usings, self::get_types($params[0]->getType()));
            $method->addBody('$data = $?;', [array_values($method->getParameters())[0]->getName()]);
        }
        $method->addBody('$type = ?;', [$dapr_type]);
        $method->addBody('$id = $this->get_id();');
        $method->addBody('$current_method = ?;', [$method->getName()]);
        $method->addBody('$result = \Dapr\DaprClient::post(');
        $method->addBody('  \Dapr\DaprClient::get_api("/actors/$type/$id/method/$current_method"),');
        if (empty($params)) {
            $method->addBody('  null);');
        } else {
            $method->addBody('  \Dapr\Serialization\Serializer::as_array($data)');
            $method->addBody(');');
        }
        $return_type = $method->getReturnType() ?? Type::VOID;
        if ($return_type !== Type::VOID) {
            $usings = array_merge($usings, self::get_types($return_type));
            $method->addBody(
                '$result->data = \Dapr\Deserialization\Deserializer::detect_from_parameter((new \ReflectionClass($this))->getMethod(?), $result->data);',
                [$method->getName()]
            );
            $method->addBody('return $result->data;');
        }

        return $method;
    }
}
