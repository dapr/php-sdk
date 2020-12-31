<?php

namespace Dapr\Actors;

use Dapr\DaprClient;
use Dapr\Serializer;
use LogicException;
use ReflectionClass;
use ReflectionClassConstant;
use ReflectionMethod;

/**
 * Class ActorProxy
 * @package Dapr
 */
abstract class ActorProxy
{
    /**
     * Returns an actor proxy
     *
     * @param string $interface
     * @param mixed $id The id to proxy for
     *
     * @return InternalProxy
     * @throws \ReflectionException
     */
    public static function get(string $interface, $id)
    {
        $reflected_interface = new ReflectionClass($interface);
        $proxy               = new InternalProxy();

        $type = (new ReflectionClassConstant($interface, 'DAPR_TYPE'))->getValue();

        if (empty($type)) {
            throw new LogicException("$interface must have a DAPR_TYPE constant");
        }

        $methods = $reflected_interface->getMethods(ReflectionMethod::IS_PUBLIC);

        $proxy->DAPR_TYPE = $type;
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
                    $proxy->$method_name = function (...$params) use ($type, $id, $method_name) {
                        $result = DaprClient::post(
                            DaprClient::get_api("/actors/$type/$id/method/$method_name"),
                            Serializer::as_json($params)
                        );
                        return $result->data;
                    };
                    break;
            }
        }

        return $proxy;
    }
}
