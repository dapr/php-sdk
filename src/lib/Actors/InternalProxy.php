<?php

namespace Dapr\Actors;

/**
 * Class InternalProxy
 * @property mixed DAPR_TYPE The dapr actor type
 * @package Dapr
 */
class InternalProxy
{
    use Actor;

    /**
     * Proxies calls to the proxy
     *
     * @param string $name The name of the method to call
     * @param array $arguments Arguments to pass to the method
     *
     * @return false|mixed
     */
    public function __call(string $name, array $arguments)
    {
        return call_user_func_array($this->$name, $arguments);
    }
}
