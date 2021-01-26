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

        $original_interface = $interface;
        $interface = ClassType::from($interface);
        $interface->addExtend($original_interface);
        $interface->setClass();
        $interface->setName($proxy_type);

        if ( ! $reflected_interface->isSubclassOf(IActor::class)) {
            $interface->addExtend(IActor::class);
        }

        $interface->addTrait(ActorTrait::class);

        $get_id = new Method('get_id');
        $get_id->setReturnType(Type::STRING);
        $get_id->setPublic();
        $get_id->addBody('return $this->id;');
        $interface->addMember($get_id);

        $actor_interface = ClassType::from(IActor::class);
        foreach($actor_interface->getMethods() as $method) {
            $interface->addMember(self::generate_proxy_method($method, $type));
        }
        $failure_methods = array_filter(
            $actor_interface->getMethods(),
            fn($method) => in_array($method->getName(), ['remind', 'on_activation', 'on_deactivation'], true)
        );
        foreach($failure_methods as $failure_method) {
            $interface->addMember(self::generate_failure_method($failure_method));
        }

        $php_file = new PhpFile();
        $namespace = $php_file->addNamespace("\\Dapr\\Proxies\\");
        $namespace->add($interface);

        if (self::$mode === ProxyModes::GENERATED_CACHED) {
            file_put_contents($file, $php_file);
        }

        return $php_file;
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
        $method->addBody('throw new \LogicException("Cannot call ? outside the actor', [$method->getName()]);
        $method->setPublic();
        return $method;
    }

    private static function generate_proxy_method(Method $method, string $dapr_type): Method
    {
        $method->addBody('$data = $?;', [$method->getParameters()[0]->getName()]);
        $method->addBody('$type = ?;', [$dapr_type]);
        $method->addBody('$id = $this->get_id();');
        $method->addBody('$result = \Dapr\DaprClient::post(');
        $method->addBody('  \Dapr\DaprClient::get_api("/actors/$type/$id/method/?', [$method->getName()]);
        $method->addBody('  \Dapr\Serialization\Serializer::as_array($data ?? null)');
        $method->addBody(');');
        if($method->getReturnType() !== null) {
            $method->addBody(
                '$result->data = \Dapr\Deserialization\Deserializer::detect_from_parameter((new ReflectionClass($this))->getMethod(?), $result->data);',
                [$method->getName()]
            );
            $method->addBody('return $result->data;');
        }
        return $method;
    }
}
