<?php

namespace Dapr\Client;

/**
 * Class AppId
 * @package Dapr\Client
 */
class AppId
{
    public function __construct(public string $id, public string $namespace = '')
    {
    }

    public function getAddress(): string
    {
        return empty($this->namespace) ? $this->id : "{$this->id}.{$this->namespace}";
    }
}
