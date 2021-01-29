<?php

namespace Dapr\Actors\Internal;

use Dapr\Actors\ActorTrait;
use Dapr\Actors\IActor;

/**
 * Class InternalProxy
 * @package Dapr
 */
class InternalProxy
{
    use ActorTrait;

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
