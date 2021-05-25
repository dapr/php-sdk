<?php

namespace Dapr\Client\V1;

/**
 * Class RegisteredComponents
 * @package Dapr\Client\V1
 */
class RegisteredComponents
{
    public function __construct(public string $name, public string $type, public string $version)
    {
    }
}
